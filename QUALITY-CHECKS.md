# Settings 1.6.3 quality checks

These checks do not authorise a commit, release package, tag or deployment.

## Reproducible analysis

Install the locked development dependencies in an isolated checkout, then run:

```sh
composer install --no-interaction --prefer-dist
composer stan
composer lint
composer compat
```

Production sites must continue using `composer install --no-dev`. Runtime packages
are unchanged. The new packages are development-only: WordPress 6.7.4 symbols,
WP-CLI 2.12.0 symbols, the WordPress PHPStan extension and PHPCompatibility.
PHPStan stays at level 5, targets PHP 7.4 and scans the original complete `.` scope,
including all regression PHP files. Only the existing vendor/build exclusions
remain. No baseline, broad ignoreErrors, relaxed PHPDoc checks or lowered level is
used. CI now runs `composer compat` as well as `composer stan` and `composer lint`.

The original configuration produced 890 findings, primarily unknown WordPress
symbols. Adding WordPress-aware analysis and integration contracts exposed 11
specific findings in the full repository. Corrections were:

- Model the optional integration error action parameter as mixed; keep its runtime
  WP_Error validation. Integrations can supply an unexpected value.
- Remove redundant empty-path/private-array tests after array filtering and the
  private property's guaranteed array initialization. Keep external input checks.
- Access the always-present MIME type key directly; false still becomes an empty
  string, preserving the existing fallback MIME behavior.
- Remove the unreachable zero-ID branch after `wp_insert_post(..., true)`: its
  contract returns a positive ID or WP_Error, and the WP_Error path still counts
  failed insertions. Real database-insert failure regressions remain passing.
- Resolve the dynamic plugin-basename constant using an analysis-only bootstrap.
  Do not boot WordPress or execute plugin hooks during analysis.
- Supply scan-only optional CF7, Flamingo and standalone Divi contracts, confined
  to tests/phpstan/integration-contracts.stub. These declare only public signatures
  used here, with mixed/array values where third-party input is not constrained.
  They contain no runtime implementation and never enter the distribution. Update
  these contracts alongside any change to a consumed integration API.

Four precise PHPStan exceptions remain, each identifier-specific and explained:

1. Three `notIdentical.alwaysFalse` sites in the channel resolver: PHPStan retains
   the literal empty last_error assigned before a taxonomy API call, but the API
   can mutate wpdb global state through database queries. Actual SQL-error tests
   exercise export, path resolution and legacy resolution. A disposable mutation
   removing the guards fails the export regression, proving the guard is needed.
2. One `arguments.count` in the migration regression intentionally registers a
   two-argument callback where three are required. This is the negative fixture
   verifying the readiness monitor rejects a handler lacking validation context.

The analysis-only integration contracts and bootstrap are never required by the
plugin, and source tests still execute the real dependency classes.

## Review of all eleven original Plugin Check warnings

| Original warning | Resolution |
| --- | --- |
| Download InputNotSanitized (1) | The value is unslashed, URL-decoded and then sanitized. A scalar-string guard now rejects array input before decoding. A precise sniff exception documents the nested-expression false positive; capability, nonce and realpath containment checks remain intact. |
| Meta-key discovery DirectQuery (1) | No core API provides this distinct-key query. It is prepared and already object-cached. Escape the literal `_field_` prefix with esc_like so underscores cannot match unrelated keys. Document the necessary SQL at its call site. |
| Import duplicate DirectQuery/NoCaching (2) | Prepared exact title/content lookup must observe writes from earlier batches. Stale application query caching could create duplicates. Retain the fresh query with precise, explained exceptions. |
| Export count DirectQuery/NoCaching (2) | Prepared scalar count must reflect the current database, not a stale export snapshot. Document the necessary fresh query. |
| Export count and row UnescapedDBParameter (2) | Both values are passed to wpdb::prepare with separate parameters. SQL fragments are internally constructed from fixed clauses and placeholders; no request text is interpolated. Document this detector limitation at the two calls. Do not add esc_sql around an already prepared query. |
| Export row DirectQuery/NoCaching (2) | Complete raw post rows and keyset pagination are required by the existing portable JSON format. Prepared fresh queries avoid stale rows. Document the exact exceptions. |
| Address-book meta_query performance (1) | Real advisory, intentionally retained without suppression. Core get_posts is used to find the newest inbound message by Flamingo's `_from_email` metadata. Limit to one ID, omit found-row counting and unnecessary term caching. The query can still be costly on very large metadata tables; a custom index/schema or new lookup cache/invalidation design is outside this quality-only change. |

Final Plugin Check result: **zero errors, one retained performance advisory**.
No legitimate finding was hidden, and the performance warning remains visible.

Run all Plugin Check checks against the source without creating a package:

```sh
wp plugin check ac-advanced-flamingo-settings --mode=update --format=json \
  --exclude-directories=.github,tests,scripts,build \
  --exclude-files=.distignore,.gitattributes,.gitignore,phpcs.xml.dist,phpstan.neon,composer.lock,release-manifest.json,RELEASE-PREPARATION.md,QUALITY-CHECKS.md
```

These exclusions are development files already absent from release-manifest.json;
no runtime code or check category is excluded. PHPStan and WPCS still scan the
regression PHP files. A diagnostic scan of the entire development checkout also
reports development-only files as unsuitable distribution contents; it is not a
scan of the intended runtime file set.

## Regression gates

Use disposable WordPress databases with real Flamingo and source dependencies.
Run channel-hierarchy-regression.php (40), divi-migration-regression.php (32),
standalone tests/integration.php (34), import-duplicate-regression.php,
upload-security-regression.php (16) and code-quality-regression.php (11).
The latter verifies literal field-prefix discovery/cache, the newest-message ID
lookup, real taxonomy SQL failures, mixed error events and array-shaped download
input. Fixtures are rolled back; HTTP download fixtures are removed afterward.
Run the CLI bootstrap cases and real import/export integration suites as consumers
of the Settings services. Run distribution/publication policy tests without
creating ZIPs. Preserve legacy JSON tests and source-ID-independent hierarchy tests.

WordPress 6.7: PHP 7.4.33, 8.2.33, 8.3.33, 8.4.24 and 8.5.9.
WordPress 7.1.3: PHP 8.3.33. Flamingo 2.6.3; WP-CLI 2.12.0.
WP-CLI's bundled React Promise emits a PHP 8.5 semicolon-switch deprecation; this
is outside the plugin and is not hidden by the tests.

References: [PHPStan identifier-specific exceptions](https://phpstan.org/user-guide/ignoring-errors),
[WordPress extension](https://github.com/szepeviktor/phpstan-wordpress).
