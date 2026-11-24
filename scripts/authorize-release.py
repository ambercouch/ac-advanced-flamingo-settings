#!/usr/bin/env python3
"""Validate a pushed stable release tag before automatic SVN publication."""
import json
import os
from pathlib import Path
import re
import subprocess
ROOT = Path(__file__).resolve().parents[1]


def validate(version, ref, event):
    if not re.fullmatch(r'[0-9]+\.[0-9]+\.[0-9]+', version):
        raise ValueError('Stable numeric release version required')
    if event != 'push' or ref != 'refs/tags/' + version:
        raise ValueError('Only a matching pushed release tag may publish')
    validate_target_manifest(version, json.loads((ROOT / 'release-manifest.json').read_text()))


def validate_target_manifest(version, manifest):
    if manifest.get('version') != version or manifest.get('slug') != 'ac-advanced-flamingo-settings':
        raise ValueError('Release tag and manifest differ')


def main():
    version = os.environ.get('RELEASE_VERSION', '')
    validate(version, os.environ.get('RELEASE_REF', ''), os.environ.get('RELEASE_EVENT', ''))
    # Peel annotated tags; lightweight and annotated release tags are supported.
    tagged = subprocess.check_output(['git', 'rev-parse', '--verify', 'refs/tags/' + version + '^{commit}'], cwd=ROOT, text=True).strip()
    checked_out = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    if tagged != checked_out:
        raise ValueError('Release tag and checked-out commit differ')
    print('Pushed release tag, checked-out commit and manifest validated.')


if __name__ == '__main__':
    main()
