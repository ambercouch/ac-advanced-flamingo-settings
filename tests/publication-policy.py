#!/usr/bin/env python3
"""Offline publication-policy checks; never creates a tag or dispatches a job."""
import sys
sys.dont_write_bytecode = True
import importlib.util
from pathlib import Path
ROOT = Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('authorise', ROOT / 'scripts/authorize-release.py')
module = importlib.util.module_from_spec(spec)
spec.loader.exec_module(module)
valid = ('1.6.3', 'a' * 40, 'publish-ac-advanced-flamingo-settings-1.6.3', 'refs/heads/master-new')
module.validate(*valid)
for index, invalid in [(0, '1.6.3;echo bad'), (1, 'HEAD'), (1, 'a'*40+'\n'), (2, 'yes'), (3, 'refs/heads/master'), (3, 'refs/heads/release/1.6.3-preparation'), (3, 'refs/tags/1.6.3'), (0, '1.6.2')]:
    args = list(valid)
    args[index] = invalid
    try:
        module.validate(*args)
        raise AssertionError('Unsafe authorisation accepted')
    except ValueError:
        pass
workflow = (ROOT / '.github/workflows/deploy-wordpress-org.yml').read_text()
assert 'workflow_dispatch:' in workflow and '    tags:' not in workflow and '\n  push:' not in workflow
assert "vars.ACAFS_WPORG_PUBLISH_ENABLED == 'true'" in workflow
assert 'name: wordpress-org-production' in workflow
assert 'persist-credentials: false' in workflow
assert 'RELEASE_COMMIT: ${{ inputs.commit }}' in workflow
assert 'run: python3 scripts/authorize-release.py' in workflow
print('PASS: manual-only, exact version/SHA, existing tag, ancestry, disabled-by-default and protected-environment publication policy')

module.validate_target_manifest("1.6.3", {"version": "1.6.3", "slug": "ac-advanced-flamingo-settings"})
for manifest in ({"version": "1.6.2", "slug": "ac-advanced-flamingo-settings"}, {"version": "1.6.3", "slug": "different"}, {}):
    try:
        module.validate_target_manifest("1.6.3", manifest)
        raise AssertionError("Mismatched approved commit accepted")
    except ValueError:
        pass
print("PASS: approved commit manifest must match release version and plugin slug")

assert "generate-zip: false" in workflow
assert "needs: authorise" in workflow
assert "github.event_name == 'workflow_dispatch'" in workflow
ci = (ROOT / '.github/workflows/ci.yml').read_text()
for forbidden in ('10up/action-wordpress-plugin-deploy', 'SVN_USERNAME:', 'SVN_PASSWORD:', 'svn commit'):
    assert forbidden not in ci, 'CI must never publish or access SVN credentials'
print('PASS: preparation-branch/tag dispatch rejection, CI without publisher/SVN credentials and no archive generation')
