#!/usr/bin/env python3
"""Offline publication-policy checks; never creates a tag or dispatches a job."""
import sys
sys.dont_write_bytecode = True
import importlib.util
import json
from pathlib import Path
from unittest.mock import patch
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('authorise', ROOT / 'scripts/authorize-release.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
version = json.loads((ROOT / 'release-manifest.json').read_text())['version']
valid = (version, 'refs/tags/' + version, 'push')
module.validate(*valid)
for index, invalid in [
    (0, '1.6.3;echo bad'), (0, 'v' + version), (0, version + '-beta.1'),
    (0, version + '\n'), (0, '1.6.2'), (0, ''),
    (1, 'refs/heads/master'), (1, 'refs/heads/master-new'),
    (1, 'refs/heads/release/1.6.3-preparation'), (1, 'refs/heads/' + version),
    (1, 'refs/tags/1.6.2'), (2, 'workflow_dispatch'), (2, 'pull_request'), (2, 'delete'),
]:
    args = list(valid)
    args[index] = invalid
    try:
        module.validate(*args)
        raise AssertionError('Unsafe publication accepted')
    except ValueError:
        pass
print('PASS: matching stable tag accepted; branches, manual runs, invalid tags and version mismatches rejected')

module.validate_target_manifest(version, {'version': version, 'slug': 'ac-advanced-flamingo-settings'})
for manifest in ({'version': '1.6.2', 'slug': 'ac-advanced-flamingo-settings'}, {'version': version, 'slug': 'different'}, {}):
    try:
        module.validate_target_manifest(version, manifest)
        raise AssertionError('Mismatched release manifest accepted')
    except ValueError:
        pass
# Exercise the command entrypoint without creating tags or running Git mutations.
environment = dict(zip(('RELEASE_VERSION', 'RELEASE_REF', 'RELEASE_EVENT'), valid))
with patch.dict(module.os.environ, environment, clear=True):
    with patch.object(module.subprocess, 'check_output', side_effect=['a'*40, 'a'*40]) as git:
        module.main()
        assert git.call_args_list[0].args[0][-1] == 'refs/tags/' + version + '^{commit}'
        assert git.call_args_list[1].args[0] == ['git', 'rev-parse', 'HEAD']
    with patch.object(module.subprocess, 'check_output', side_effect=['a'*40, 'b'*40]):
        try:
            module.main()
            raise AssertionError('Tag/checkout mismatch accepted')
        except ValueError:
            pass
print('PASS: manifest identity and peeled tag/checkout equality validated without creating tags')

workflow = (ROOT / '.github/workflows/deploy-wordpress-org.yml').read_text()
assert "on:\n  push:\n    tags:\n      - '*'\npermissions:" in workflow
for forbidden in ('workflow_dispatch:', 'inputs.', 'ACAFS_WPORG_PUBLISH_ENABLED', 'environment:', 'refs/heads/', 'branches:'):
    assert forbidden not in workflow, 'Unexpected publication condition: ' + forbidden
condition = "if: github.event_name == 'push' && startsWith(github.ref, 'refs/tags/') && !github.event.deleted"
assert workflow.count(condition) == 2
assert 'needs: validate' in workflow
assert 'run: python3 scripts/authorize-release.py' in workflow
for required in (
    'RELEASE_VERSION: ${{ github.ref_name }}', 'RELEASE_REF: ${{ github.ref }}',
    'RELEASE_EVENT: ${{ github.event_name }}', 'persist-credentials: false',
    'ref: ${{ github.sha }}',
    'uses: 10up/action-wordpress-plugin-deploy@stable', 'generate-zip: false',
    'SLUG: ac-advanced-flamingo-settings', 'VERSION: ${{ github.ref_name }}',
    'SVN_USERNAME: ${{ secrets.SVN_USERNAME }}', 'SVN_PASSWORD: ${{ secrets.SVN_PASSWORD }}',
    'composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader',
    'python3 tests/distribution.py',
    'python3 scripts/build-dist.py --stage /tmp/acafs-wordpress-publish/ac-advanced-flamingo-settings',
    'BUILD_DIR: /tmp/acafs-wordpress-publish/ac-advanced-flamingo-settings',
):
    assert required in workflow, 'Missing publication safeguard/build step: ' + required
ci = (ROOT / '.github/workflows/ci.yml').read_text()
for forbidden in ('10up/action-wordpress-plugin-deploy', 'SVN_USERNAME:', 'SVN_PASSWORD:', 'svn commit'):
    assert forbidden not in ci, 'CI must never publish or access SVN credentials'
print('PASS: tag-only workflow, validated deployment dependency, existing SVN action/secrets and manifest-based production build; CI cannot publish')
