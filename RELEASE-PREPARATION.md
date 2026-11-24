# Settings 1.6.3 release preparation

Base master-new: 52d5f85812929b61c879f3f64655fe88c4940118.
Preparation-branch commits and pushes are authorised. Merging, default-branch
changes, tags, distribution ZIPs and publication remain separately gated.

## Requirements and checks

WordPress 6.7+, PHP 7.4+ and active Flamingo 2.6.3+ are the coordinated validation
floor. WP-CLI 2.12.0 is the tested minimum for the optional CLI add-on.
Readme “Tested up to” uses the validated WordPress 7.1 family, not an untested patch.
Run the real WordPress suites against isolated databases; the migration suite accepts
ACAFS_DIVI_TEST_PLUGIN_PATH for the standalone checkout. Keep full coding-standard,
compatibility, security and Plugin Check output with the release evidence. Known
false-positive exceptions must be narrow and explained at the relevant operation.

## Packaging without accidental archives

`python3 scripts/build-dist.py --check` and `python3 tests/distribution.py` never
create ZIPs. The manifest includes runtime code, assets, readme, licence and Composer
metadata plus locked production vendor code. A --no-dev vendor directory matching
composer.lock is mandatory; development files inside a vendor source archive are
excluded. `--stage /new/external/directory` copies runtime files only and refuses
existing or source directories. The legacy build/build-zip.sh delegates to this
validator; it no longer deletes its own tracked directory or uses git archive.
After separate approval only, use `--build-zip /new/output.zip`. Existing archives
are never overwritten. The manifest supplies a single plugin-directory prefix.

## Publication and branch controls

CI checks PRs targeting master-new and preparation/development pushes without
building ZIPs. WordPress.org publication has no push/tag trigger. It requires a
manual run from master-new, the exact approved version and 40-character SHA, a
matching existing release tag, ancestry in master-new, explicit confirmation,
ACAFS_WPORG_PUBLISH_ENABLED=true and the wordpress-org-production environment.
Configure REQUIRED REVIEWERS, prevent self-review, restrict branches and store SVN
credentials only in that environment before enabling the repository variable.
These external settings have NOT been changed by source preparation. A newly
created environment alone is not an approval gate; keep publication disabled until
its protection is verified. Do not create a tag or dispatch the workflow now.
The default branch remains master until separately approved and changed.

## Release order

Make Divi 1.0.2 available first; on sites upgrading bundled capture, install/activate
it while the old handler still owns capture. Upgrade Settings, preserve the existing
flag and verify exactly one complete record. Make CLI beta.2 available only after
Settings 1.6.3. No plugin is installed or activated automatically by this code.

## Outstanding release gates

The legacy master/tag workflow can still contain automatic publication triggers.
Changing this branch does not protect historical workflow revisions. Before any
release, move repository-level SVN credentials into the protected environment and
verify the legacy publication path cannot use credentials. No external settings
were changed during preparation.

PHPStan now passes at unchanged level 5 with all regression PHP files included.
See QUALITY-CHECKS.md for the WordPress-aware configuration, corrections and four
identifier-specific framework/negative-fixture exceptions.

The dependency audit found and patched development-only tooling advisories:
PHPCS 3.13.6, PHPCSUtils 1.2.3, PHPCSExtra 1.5.1 and WPCS 3.4.1. Locked
production packages are byte-for-byte unchanged; both production and full locked
audits are now clean. No development vendor code is included in the manifest.

Final Plugin Check has zero errors and one retained, genuine metadata-query
performance advisory. All eleven original warnings and their resolutions are
reviewed in QUALITY-CHECKS.md. The remaining bounded core-API query is not hidden.

## Publication audit before preparation-branch push (24 November 2026)

Audited all fetched remote branch/tag tips and reachable workflow history: six
unique YAML blobs across five workflow-changing commits, including five historical
publication versions. Every historical publication trigger is push.tags only
(`*` or `v*.*.*`), never a branch push. The proposed publisher is dispatch-only and
requires master-new; neither historical nor proposed publisher runs for this
preparation branch push. No workflow_run/create/check_suite publication chain was
found. Historical CI is PR-only; proposed CI only validates, with no publisher,
publication secrets or archive upload step. Push only the explicit branch ref;
never push --tags, --follow-tags or --mirror as part of preparation.

GitHub API confirms master and master-new are unprotected and there are no Settings
rulesets. Administrators must configure PR/review/status-check requirements and
force-push/deletion restrictions for authoritative/release branches, plus restricted
release-tag creation/update/deletion. No repository protection setting was changed.

Environment protection, SVN secret scope and the publication variable are NOT
verifiable through the connected API; do not describe them as configured. Before
any future tag, rerun or publication: disable the legacy publication path; remove
or rotate repository-level SVN credentials so old tags/runs cannot reuse them;
configure wordpress-org-production with required reviewers, prevent self-review,
no administrator bypass, selected branch master-new only, and environment-only
SVN secrets. Keep ACAFS_WPORG_PUBLISH_ENABLED explicitly false until these controls
are verified and a release is separately authorised. Historical workflows are not
made safe retroactively by changing a preparation branch. These future-publication
controls do not block the audited branch-only push.
