#!/usr/bin/env python3
"""Manifest, metadata, staging and path-safety checks. Never builds a ZIP."""
import sys
sys.dont_write_bytecode = True
import importlib.util
from pathlib import Path
import tempfile
import json
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
