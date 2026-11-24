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
