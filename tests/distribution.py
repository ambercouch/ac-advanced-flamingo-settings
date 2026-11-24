#!/usr/bin/env python3
"""Manifest, metadata, staging and path-safety checks. Never builds a ZIP."""
import sys
sys.dont_write_bytecode = True
import importlib.util
from pathlib import Path
import tempfile
import json
import shutil
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('release_builder', ROOT / 'scripts/build-dist.py')
builder = importlib.util.module_from_spec(spec)
spec.loader.exec_module(builder)
manifest, files = builder.release_files()
assert manifest['entrypoint'] in files and 'readme.txt' in files
EXPECTED_FILES = {'gpl-2.0.txt', 'ac-advanced-flamingo-settings.php', 'composer.json', 'readme.txt'}
EXPECTED_DIRS = {'includes', 'assets', 'lib'}
assert set(manifest['files']) == EXPECTED_FILES
assert set(manifest.get('directories', [])) == EXPECTED_DIRS
assert all(not n.endswith('.zip') for n in files)
assert not any(n.startswith(('tests/', 'scripts/', '.git', 'build/')) for n in files)
with tempfile.TemporaryDirectory(prefix='acafs-stage-test-') as temp:
    target = Path(temp) / manifest['slug']
    builder.stage(files, target)
    assert set(p.relative_to(target).as_posix() for p in target.rglob('*') if p.is_file()) == set(files)
    for name, source in files.items():
        assert (target / name).read_bytes() == source.read_bytes()
    try:
        builder.stage(files, target)
        raise AssertionError('Existing stage was overwritten')
    except FileExistsError:
        pass
try:
    builder.stage(files, ROOT)
    raise AssertionError('Source directory was accepted as a staging target')
except ValueError:
    pass
print('PASS: release metadata, runtime manifest, source bytes and safe staging; no ZIP built')

with tempfile.TemporaryDirectory(prefix='acafs-manifest-security-') as temp:
    fake = Path(temp)
    for unsafe in ('../escape.php', '/absolute.php'):
        (fake / 'release-manifest.json').write_text(json.dumps({'slug': 'test-plugin', 'files': [unsafe]}))
        try:
            builder.release_files(root=fake)
            raise AssertionError('Unsafe manifest path accepted')
        except ValueError:
            pass
    (fake / 'linked.php').symlink_to(ROOT / manifest['entrypoint'])
    (fake / 'release-manifest.json').write_text(json.dumps({'slug': 'test-plugin', 'files': ['linked.php']}))
    try:
        builder.release_files(root=fake)
        raise AssertionError('Symlink source accepted')
    except ValueError:
        pass
print('PASS: traversal, absolute-path and symlink manifest sources rejected')

# Use an untouched production dependency copy; never clean ROOT/vendor for tests.
EXCLUDED_DEPENDENCY_FILES = (
    '.circleci/config.yml', '.github/workflows/ci.yml', '.gitlab/pipeline.yml',
    '.buildkite/pipeline.yml', '.devcontainer/devcontainer.json', '.vscode/settings.json',
    '.idea/workspace.xml', '.git/config', 'tests/ExampleTest.php', 'Tests/ExampleTest.php',
    'test/fixture.json', 'spec/example.php', 'coverage/index.html',
    'node_modules/example/index.js', '__pycache__/example.pyc', 'bin/phpcs',
    'vendor-bin/phpstan/composer.json', '.phpcs.xml', '.phpcs.xml.dist',
    'phpcs.xml', 'phpcs.xml.dist', 'phpcs-ci.xml', 'phpunit.xml', 'phpunit.xml.dist',
    '.phpunit.result.cache', 'phpstan.neon', 'phpstan.neon.dist', 'phpstan-baseline.neon',
    'psalm.xml', '.php-cs-fixer.dist.php', 'rector.php', '.travis.yml', '.gitlab-ci.yml',
    'azure-pipelines.yml', 'appveyor.yml', 'codecov.yml', '.editorconfig', '.gitmodules',
    'composer.lock', 'package.json', 'package-lock.json', 'yarn.lock', 'pnpm-lock.yaml',
    'Makefile', 'gulpfile.js', 'webpack.config.js', 'vite.config.js', 'tsconfig.json',
    '.eslintrc.json', '.prettierrc', 'CONTRIBUTING.md',
)
RETAINED_DEPENDENCY_FILES = (
    '.htaccess', 'resources/runtime.yml', 'resources/runtime.yaml',
    'resources/runtime.xml', 'resources/runtime.json', 'resources/runtime.neon',
    'src/Runtime.php', 'dist/runtime.min.js',
)
with tempfile.TemporaryDirectory(prefix='acafs-vendor-policy-') as temp:
    vendor = Path(temp) / 'vendor'
    shutil.copytree(ROOT / 'vendor', vendor)
    package = vendor / 'deliciousbrains/wp-background-processing'
    baseline = {name: source.read_bytes() for name, source in files.items()}
    for name in EXCLUDED_DEPENDENCY_FILES + RETAINED_DEPENDENCY_FILES:
        destination = package / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text('fixture: ' + name)
    _, filtered = builder.release_files(vendor_source=vendor)
    for name in EXCLUDED_DEPENDENCY_FILES:
        assert 'vendor/deliciousbrains/wp-background-processing/' + name not in filtered, 'Development dependency file shipped: ' + name
    for name in RETAINED_DEPENDENCY_FILES:
        key = 'vendor/deliciousbrains/wp-background-processing/' + name
        assert filtered[key].read_text() == 'fixture: ' + name, 'Runtime dependency file removed: ' + name
    for name, payload in baseline.items():
        assert filtered[name].read_bytes() == payload, 'Existing runtime/license/autoloader bytes changed: ' + name
    assert set(filtered) == set(files) | {'vendor/deliciousbrains/wp-background-processing/' + name for name in RETAINED_DEPENDENCY_FILES}
    # Security checks still run before excluding a known CI directory.
    ci = package / '.buildkite'
    shutil.rmtree(ci)
    ci.symlink_to(ROOT / 'includes', target_is_directory=True)
    try:
        builder.release_files(vendor_source=vendor)
        raise AssertionError('Excluded CI symlink source accepted')
    except ValueError:
        pass
print('PASS: dependency CI/tooling excluded; runtime config/assets, licenses and loader bytes retained; CI symlink rejected')
