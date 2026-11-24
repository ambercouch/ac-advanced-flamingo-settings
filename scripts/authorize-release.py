#!/usr/bin/env python3
"""Fail closed unless an exact, existing release tag was explicitly approved."""
import json
import os
from pathlib import Path
import re
import subprocess
ROOT = Path(__file__).resolve().parents[1]


def validate(version, commit, confirmation, branch):
    if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', version):
        raise ValueError('Stable numeric release version required')
    if not re.fullmatch(r'[0-9a-f]{40}', commit):
        raise ValueError('Full commit SHA required')
    if branch != 'refs/heads/master-new':
        raise ValueError('Only master-new may authorise publication')
    if confirmation != 'publish-ac-advanced-flamingo-settings-' + version:
        raise ValueError('Explicit publication confirmation required')
    if version != json.loads((ROOT / 'release-manifest.json').read_text())['version']:
        raise ValueError('Version differs from the reviewed manifest')


def validate_target_manifest(version, manifest):
    if manifest.get('version') != version or manifest.get('slug') != 'ac-advanced-flamingo-settings':
        raise ValueError('Approved commit manifest does not match the release')


def main():
    version = os.environ.get('RELEASE_VERSION', '')
    commit = os.environ.get('RELEASE_COMMIT', '')
    validate(version, commit, os.environ.get('RELEASE_CONFIRMATION', ''), os.environ.get('RELEASE_BRANCH', ''))
    tagged = subprocess.check_output(['git', 'rev-parse', '--verify', 'refs/tags/' + version + '^{commit}'], cwd=ROOT, text=True).strip()
    if tagged != commit:
        raise ValueError('Approved tag and commit differ')
    target_manifest = json.loads(subprocess.check_output(['git', 'show', commit + ':release-manifest.json'], cwd=ROOT, text=True))
    validate_target_manifest(version, target_manifest)
    subprocess.run(['git', 'merge-base', '--is-ancestor', commit, 'origin/master-new'], cwd=ROOT, check=True)
    print('Exact release identity validated; protected environment approval still required.')


if __name__ == '__main__':
    main()
