# Code Review Validation instructions

## Known false positives

Each of these looks like a finding but is deliberate or accepted in this repo:

- **"No tests" / "missing unit tests".** The repository has no test setup. Drop the finding unless the
  PR itself adds a test framework.
- **"`echo` in business code"** in `Model/Sync/Customers/Processor.php`. It prints progress for core's
  `yotpo:resync` CLI (`isCommandLineSync`), marked `// phpcs:ignore`.
- **"Errors logged at info level".** Every log line in the module is `info`; it is the convention.
- **"Exceptions are swallowed"** in processors and observers. Deliberate: a sync must never break the
  merchant's request or cron (`docs/conventions.md`, Error Handling). Keep only a finding where the
  failure is also not logged or not recorded in `yotpo_customers_sync`.
- **"Exact version constraint on `yotpo/module-yotpo-core`"** and the other `composer validate`
  warnings. Deliberate (ADR-0001).
- **"`ObjectManager::getInstance()`"** in `Plugin/Checkout/DefaultConfigProviderPlugin.php:69`.
  Pre-existing backward-compatibility fallback.
- **"Hard-coded URLs"** in `view/frontend/requirejs-config.js` and `etc/csp_whitelist.xml`. They are the
  Yotpo script hosts the module is meant to load.
- **The items under "Known inconsistencies" in `docs/troubleshooting.md`** on lines the PR did not
  change.

## Always keep

Never filter these out, even if they look minor or cosmetic:

- a committed credential, app key, secret, token or real customer data;
- any change to abandoned-cart token generation, lookup or checks, customer logout, or login redirects;
- unescaped or wrongly escaped output in a template or `<script>`;
- a new CSP or RequireJS script host;
- a schema column or table removal, an edited existing data patch, or a whitelist out of step with
  `etc/db_schema.xml`;
- an exception that can escape into a quote save, customer save or checkout page;
- a change to the SMS-consent value or when it is sent;
- `composer.json` `version` and `etc/module.xml` `setup_version` out of step;
- syntax or APIs that break on a supported PHP or Magento version.

## Domain notes

- There is no CI and no local check (`REPO_CHECKS.md`). A finding cannot be dismissed with "CI would
  catch it" or "the build would fail".
- Magento resolves classes through XML: a class that looks unused in PHP may be wired in `etc/**/*.xml`
  or layout XML. Search there before accepting a "dead code" or "unused class" finding.
- `etc/di.xml` preferences replace Magento and `Yotpo_Core` classes globally; a change to
  `Model/Metadata/Form/Checkbox.php`, `Model/Attribute/Data/Checkbox.php` or
  `Model/Sync/Customers/Processor.php` reaches callers outside this module.
- Plugins on `Magento\Quote\Model\Quote` run in every area (storefront, admin, REST, cron). A finding
  about "only on checkout" must be checked against all of them.
- Many base classes live in `yotpo/module-yotpo-core`, which is not in this repository. A "method does
  not exist" finding must be checked against core before it is kept.
