# Settings regression tests

Run only in a local WordPress environment with transactional core tables and no
persistent object cache. These CLI suites use real WordPress APIs; their database
fixtures are enclosed in transactions and rolled back, including on failure.

```sh
ddev wp --skip-plugins --skip-themes eval-file web/wp-content/plugins/ac-advanced-flamingo-settings/tests/divi-migration-regression.php
ddev wp --skip-plugins --skip-themes eval-file web/wp-content/plugins/ac-advanced-flamingo-settings/tests/import-duplicate-regression.php
```

The Divi suite needs Flamingo installed and the verified standalone checkout at
`development/acafs-divi-contact-form-for-flamingo/` beside the WordPress docroot.
Neither plugin is automatically installed or activated. It loads real capture
classes while inventory fixtures model missing/inactive states without moving
plugin files. The Settings 1.4.3 upgrade fixture uses its original saved option
name and value (see historical commit `0105678`); it does not run an old Settings
plugin concurrently with the current implementation.

Coverage includes never-enabled and disabled capture, preserved enabled upgrade
intent, omitted shared-form values, explicit disabling, missing/inactive plugins,
missing dependencies/handler, administrator permissions, persistent notices,
safe failure diagnostics and automatic recovery through real action and POST
fallback captures. Restoring a callback proves readiness; the separate standalone
integration suite and real website submissions validate capture end to end.

Runtime failures are cleared only on an observed capture with a persisted child
channel. A normal import or readiness probe must not clear the failure. No import
or export hierarchy-preservation behavior is changed by this feature.

## Channel hierarchy regression

```sh
ddev wp --skip-plugins --skip-themes eval-file web/wp-content/plugins/ac-advanced-flamingo-settings/tests/channel-hierarchy-regression.php
```

This transactional suite requires installed Flamingo and the existing Settings
vendor autoloader. It uses real JSON exports, taxonomy writes and the actual admin
background worker, exposing protected worker methods only in a test fixture. No
queue is dispatched, no plugin is activated and no email is sent. Temporary files
are deleted and database fixtures are rolled back, including on assertion failure.

Coverage includes child-only source assignments, multiple/deep paths, fresh and
existing destinations, unrelated/conflicting term IDs, same names and source slug
collisions under different parents, destination-generated slugs, repeated imports,
legacy JSON formats, invalid paths, parent/child creation errors, real relationship
INSERT failure/retry, concurrent term creation, legitimate quote/backslash/Unicode
names, safe admin result reporting and corrupt source hierarchy cycles.

The shared reader plus import_batch is the CLI/programmatic path. The admin queue
passes the same self-contained message records to that service. Queue transport
and completed success/error notices are exercised without making HTTP requests.
Hierarchy changes do not repair channels on previously imported duplicates.
