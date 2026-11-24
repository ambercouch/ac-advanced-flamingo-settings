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
composer.lock is mandatory; known dependency CI, PHPCS/PHPUnit/static-analysis/formatter configuration,
frontend build tooling, tests, caches and Git metadata are automatically excluded.
Runtime config/assets, licenses, Composer metadata and autoloaders are retained.
No manual vendor cleanup or --vendor-source override is required. `--stage /new/external/directory` copies runtime files only and refuses
existing or source directories. The legacy build/build-zip.sh delegates to this
validator; it no longer deletes its own tracked directory or uses git archive.
For an approved local candidate build, install the locked dependencies and use the
standard wrapper from a fresh build checkout (no vendor sanitation step):

```sh
COMPOSER_ROOT_VERSION=1.6.3 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
python3 tests/distribution.py
bash build/build-zip.sh --build-zip /new/output.zip
```

ZIP creation still requires explicit approval. Existing archives are never
overwritten. The manifest supplies a single plugin-directory prefix.

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

Make Divi 1.0.2 available first. Pause public form submissions, including direct
POST/AJAX traffic, and back up before changing plugins. Install/activate Divi1.0.2
and upgrade Settings1.6.3 while that pause remains in place. Preserve the saved
preference and verify exactly one correct record per successful browser test, no
records for invalid submissions and unchanged prior records. Resolve failures,
refresh cached form pages/nonces and repeat verification before restoring traffic.
Do not rely on the old handler during overlap or rollback; historical bundled
capture has known load-order and field-array defects. Make CLI beta.2 available
only after Settings1.6.3. No plugin is installed or activated automatically.

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

## Historical bundled implementation: observed defects

The real-browser baseline was commit
2fa911cbaa310d103e4e14cdce2e2fecf27c9e04, not a synthetic callback. Its plugin
header declares 1.4.3 while ACAFS_VERSION is 1.1.0. The 1.4.3 tag lacks the bundled
integration, so the version label alone cannot identify the installed code.

- Under ordinary alphabetical plugin loading, Settings initialises before Flamingo
  is available and registers no capture callback despite an enabled preference.
  The browser can show a success message while no Flamingo record is created.
- With Flamingo loaded first, the actual bundled callback registers, but passes
  Divi's array-valued processed fields into scalar email sanitisation. On the tested
  WordPress6.7/PHP8.3.33/Divi4.27.4 stack this throws a strlen/sanitize_email TypeError
  and no record is stored.
- The standalone correctly yields to a registered bundled callback to avoid double
  capture. This ownership guard does not repair or certify the legacy callback.

The obsolete implementation is unchanged. Keep public submissions paused throughout
installation/overlap/upgrade and any rollback. Preserve the saved preference, apply
Settings1.6.3 and Divi1.0.2 together, and verify real records and rejected submissions
with fresh nonces before restoring traffic. A successful page message, plugin
activation or absence of an admin notice alone is insufficient. The customer-facing
readme documents the step-by-step controlled procedure.

## Verified RC2 build record

Package: `ac-advanced-flamingo-settings-1.6.3-rc.2.zip`.

SHA-256: `cb2a04de78743e8f5d08a03d3426b66133872ec8d9f19026ff97010545052d6c`

Build from this preparation branch in a fresh directory using the approved
manifest and a new output path:

```sh
COMPOSER_ROOT_VERSION=1.6.3 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
python3 tests/distribution.py
bash build/build-zip.sh --build-zip /new/ac-advanced-flamingo-settings-1.6.3-rc.2.zip
```

No manual vendor cleanup or alternate vendor source was used. Packaged repository
files match the preparation source. Settings vendor files are generated by a fresh
production Composer install from composer.lock, rather than committed vendor files;
the tested RC2 production vendor tree is retained with the local build evidence.
Production runtime and licence bytes match the previous tested candidate. Only
readme.txt changed inside the RC2 archive. Preparation notes and development tests
are excluded by the manifest. ZIP bytes are reproducible with the same dependency
installation and build inputs.
