#!/usr/bin/env python3
"""Validate a release manifest; staging and ZIP creation require explicit flags."""
import argparse
from fnmatch import fnmatchcase
import hashlib
import json
from pathlib import Path
import re
import shutil
from zipfile import ZIP_STORED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]

# Known development/tooling paths only. Runtime config formats and unrelated
# dotfiles are retained; licenses, Composer metadata and loader files are required.
VENDOR_DEVELOPMENT_DIRECTORIES = frozenset({
    '.git', '.github', '.circleci', '.gitlab', '.buildkite', '.devcontainer',
    '.idea', '.vscode', '.husky', '.phpunit.cache', '.pytest_cache', '.mypy_cache',
    '.ruff_cache', '.tox', '.nox', '.venv', '__pycache__', 'node_modules', 'vendor-bin',
    'test', 'tests', 'Test', 'Tests', '__tests__', 'spec', 'specs', 'coverage',
    'htmlcov', 'benchmark', 'benchmarks', 'bin',
})
VENDOR_DEVELOPMENT_FILES = (
    '.git', '.gitignore', '.gitattributes', '.gitmodules', '.gitconfig',
    '.editorconfig', '.travis.yml', '.travis.yaml', '.gitlab-ci.yml', '.gitlab-ci.yaml',
    '.scrutinizer.yml', '.coveralls.yml', '.styleci.yml', 'appveyor.yml', '.appveyor.yml',
    'azure-pipelines.yml', 'azure-pipelines.yaml', 'codecov.yml', '.codecov.yml',
    '.phpcs*.xml*', 'phpcs*.xml*', '.phpcs-cache', '.phpcs-cache.*',
    'phpunit*.xml*', '.phpunit*.xml*', '.phpunit.result.cache',
    'phpstan*.neon', 'phpstan*.neon.dist', 'psalm.xml', 'psalm.xml.dist',
    'psalm-baseline.xml', '.php-cs-fixer*.php', 'php-cs-fixer*.php',
    '.php-cs-fixer.cache', '.php_cs', '.php_cs.dist', 'rector.php', 'rector-*.php',
    'composer.lock', 'package.json', 'package-lock.json', 'npm-shrinkwrap.json',
    'yarn.lock', 'pnpm-lock.yaml', 'bower.json', 'bower.lock',
    'Makefile', 'Makefile.*', 'makefile', 'GNUmakefile', 'build.xml',
    'gulpfile.*', 'Gruntfile.*', 'webpack.config.*', 'vite.config.*',
    'tsconfig*.json', 'jsconfig.json', '.eslint*', '.prettier*', '.stylelint*',
    'CONTRIBUTING', 'CONTRIBUTING.*',
)


def is_vendor_development_path(relative):
    """Identify vendor tooling without excluding runtime assets or config by type."""
    return bool(relative.parts) and relative.parts[0] == 'vendor' and (
        any(part in VENDOR_DEVELOPMENT_DIRECTORIES for part in relative.parts[1:])
        or any(fnmatchcase(relative.name, pattern) for pattern in VENDOR_DEVELOPMENT_FILES)
    )


def release_files(root=ROOT, vendor_source=None):
    manifest = json.loads((root / 'release-manifest.json').read_text())
    slug = manifest['slug']
    if not re.fullmatch(r'[a-z0-9]+(?:-[a-z0-9]+)*', slug):
        raise ValueError('Invalid plugin slug')
    files = {}

    def collect(source, relative):
        relative = Path(relative)
        if relative.is_absolute() or '..' in relative.parts or source.is_symlink():
            raise ValueError('Unsafe manifest source: ' + str(relative))
        if is_vendor_development_path(relative):
            return
        if source.is_dir():
            for child in sorted(source.iterdir()):
                collect(child, relative / child.name)
        elif source.is_file():
            if relative.as_posix() in files:
                raise ValueError('Duplicate manifest file')
            files[relative.as_posix()] = source
        else:
            raise ValueError('Missing manifest source: ' + str(relative))

    for name in manifest['files'] + manifest.get('directories', []):
        if Path(name).is_absolute() or '..' in Path(name).parts:
            raise ValueError('Unsafe manifest path')
        collect(root / name, name)
    if manifest.get('production_vendor'):
        vendor = Path(vendor_source) if vendor_source else root / 'vendor'
        installed = json.loads((vendor / 'composer/installed.json').read_text())
        if not isinstance(installed, dict) or installed.get('dev', True):
            raise ValueError('Use a production-only Composer vendor directory (--no-dev)')
        actual = {p['name']: p['version'] for p in installed['packages']}
        lock = json.loads((root / 'composer.lock').read_text())
        expected = {p['name']: p['version'] for p in lock['packages']}
        if actual != expected:
            raise ValueError('Production dependencies differ from composer.lock')
        if not (vendor / 'autoload.php').is_file():
            raise ValueError('Production Composer autoloader missing')
        collect(vendor, 'vendor')
    plugin = (root / manifest['entrypoint']).read_text()
    version = re.search(r'^\s*\* Version:\s*(\S+)', plugin, re.M).group(1)
    if version != manifest['version']:
        raise ValueError('Manifest/header version mismatch')
    if not re.search(r"define\(\s*'" + re.escape(manifest['version_constant']) + r"',\s*'" + re.escape(version) + r"'\s*\)", plugin):
        raise ValueError('Header/constant version mismatch')
    readme = (root / 'readme.txt').read_text()
    if 'Stable tag: ' + version not in readme or '= ' + version + ' =' not in readme:
        raise ValueError('Readme release metadata mismatch')
    for name in files:
        parts = Path(name).parts
        if any(p in {'.git', '.github', 'tests', '__pycache__', 'node_modules', 'build'} for p in parts) or name.endswith('.zip'):
            raise ValueError('Development/generated file in distribution: ' + name)
    return manifest, dict(sorted(files.items()))


def stage(files, target, root=ROOT):
    target = Path(target).resolve()
    root = root.resolve()
    if target == root or root in target.parents or target in root.parents:
        raise ValueError('Stage outside the source checkout')
    # Never remove or overwrite an existing directory, including build scripts.
    target.mkdir(parents=True, exist_ok=False)
    for name, source in files.items():
        destination = target / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, destination)
        destination.chmod(0o644)


def build_zip(manifest, files, destination):
    destination = Path(destination)
    destination.parent.mkdir(parents=True, exist_ok=True)
    with ZipFile(destination, 'x', compression=ZIP_STORED) as archive:
        for name, source in files.items():
            entry = ZipInfo(manifest['slug'] + '/' + name, (2020, 1, 1, 0, 0, 0))
            entry.create_system = 3
            entry.external_attr = 0o100644 << 16
            archive.writestr(entry, source.read_bytes())


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    modes = parser.add_mutually_exclusive_group()
    modes.add_argument('--check', action='store_true', help='Validate only; never creates an archive (default)')
    modes.add_argument('--stage', type=Path, help='Copy validated runtime files to a NEW external directory')
    modes.add_argument('--build-zip', type=Path, metavar='OUTPUT', help='Explicitly create a NEW distribution ZIP')
    parser.add_argument('--vendor-source', type=Path)
    args = parser.parse_args()
    manifest, files = release_files(vendor_source=args.vendor_source)
    digest = hashlib.sha256()
    for name, source in files.items():
        digest.update(name.encode() + b'\0' + source.read_bytes())
    if args.stage:
        stage(files, args.stage)
    elif args.build_zip:
        build_zip(manifest, files, args.build_zip)
    print(json.dumps({'slug': manifest['slug'], 'version': manifest['version'], 'files': len(files),
                      'content_sha256': digest.hexdigest(), 'archive_created': bool(args.build_zip)}, sort_keys=True))


if __name__ == '__main__':
    main()
