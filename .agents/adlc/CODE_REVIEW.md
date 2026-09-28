# Code Review instructions

## Also read

- `ARCHITECTURE.md` -- module map, the customer and checkout sync flows, the abandoned-cart link, the
  release flow.
- `docs/conventions.md` -- layout, error handling, logging, Do's and Don'ts, the Anti-Patterns table.
- `docs/troubleshooting.md` -- known failure modes and the known inconsistencies (not to be reported).
- `docs/adr/` -- when the change touches the `Yotpo_Core` dependency or pin (0001) or checkout-sync
  triggers (0002).
- `.claude/protected-files.json` -- the criticality tiers; `human-required` is authoritative.

## Risk areas

This module runs inside **merchants' Magento stores**, on Magento and PHP versions nobody here can
test. There are no tests and no CI. A mistake ships in the next tagged release to every merchant
who upgrades.

- **Checkout path** -- the quote plugins run on every quote save and address change, in every area,
  and call the Yotpo API synchronously (ADR-0002). An exception, a slow call or a save loop there breaks
  or slows checkout.
- **Abandoned-cart link** -- the token in `yotpo_abandoned_cart` is what lets anyone holding the link
  restore that cart; `LoadCart` can log the current customer out, and `LoginPost` redirects after
  login. Any change to how the token is made, looked up or checked, or to who gets logged in or out, is
  security-relevant.
- **Customer data and consent** -- the payloads send email, phone, address and the SMS-consent flag
  to Yotpo. A wrong `accepts_sms_marketing` value is a compliance problem, not a cosmetic one.
- **Storefront output** -- `sync_forms.phtml`, `browse_abandonment.phtml` and the Klaviyo template
  print data from the Yotpo API and config into `<script>` on every page. CSP hosts
  (`etc/csp_whitelist.xml`) decide which script hosts are allowed.
- **Global DI preferences** -- `etc/di.xml` replaces two Magento checkbox classes and core's customers
  processor for every caller.
- **Schema and data patches** -- run once on `setup:upgrade` in every merchant store; no rollback.
- **Compatibility** -- PHP syntax and Magento APIs must work on the versions merchants run
  (`composer.json:10-11`); the last fixes were PHP 8.4 nullable parameters.
- **Release files** -- `composer.json` `version`, `etc/module.xml` `setup_version` and the exact
  `yotpo/module-yotpo-core` pin move together (ADR-0001).

## Always check

| Condition | Severity |
|---|---|
| A committed credential, app key, secret, token or real customer data. Report the file and line only; never quote the value | critical |
| A change to abandoned-cart token generation or lookup, `LoadCart` checks, customer logout, or login redirects | critical |
| Request, API or config data printed into HTML or `<script>` without the right escaper (`escapeHtml`, `escapeHtmlAttr`, `escapeJs`, `escapeUrl`) | critical |
| A new or changed host in `etc/csp_whitelist.xml` or `requirejs-config.js` `paths` | critical |
| A column or table dropped or renamed in `etc/db_schema.xml`, a whitelist not updated with it, or an edited existing data patch | critical |
| An exception that can escape from a plugin, observer or controller into a quote save, customer save or checkout page | critical |
| A change to the SMS-consent value (`accepts_sms_marketing`, `yotpo_accepts_sms_marketing`) or when it is sent | critical |
| `composer.json` `version` and `etc/module.xml` `setup_version` out of step, or the core pin changed without a matching core release | major |
| New work on the checkout-sync path (another API call, a collection load per line item, a save inside a quote plugin) | major |
| A real-time sync that saves a customer or quote without the `custSync` / registry guards, risking a loop or double sync | major |
| PHP syntax or a Magento API not available on the supported versions | major |
| A direct API call that bypasses `Model/Sync/Main.php` | major |
| Store emulation started without `stopEnvironmentEmulation()` in `finally` | major |
| A new config path outside `yotpo_core/...`, or read without a `Model/Config.php` key | minor |
| A failure logged without store id, entity id and reason | minor |

## Do not report

- **The known inconsistencies in `docs/troubleshooting.md`** (the PHP 5.6 constraint vs 7.x syntax, the
  README Reviews text, the missing `%3` log argument, the overwritten store id, the duplicated product
  type), unless the PR touches those lines.
- **Missing unit tests.** The repository has no test setup; report only when the PR adds a test
  framework halfway.
- **`// phpcs:ignore`, `@phpstan-ignore-next-line` and `echo` in CLI paths**
  (`Model/Sync/Customers/Processor.php`). Pre-existing; flag only new ones.
- **`ObjectManager::getInstance()` fallback** in `Plugin/Checkout/DefaultConfigProviderPlugin.php:69`.
  Pre-existing.
- **Everything logged at `info`**, including failures. That is the module's convention.
- **Style and formatting.** No formatter or linter runs.
- **The session key `yotpoQuoteToken` holding a quote id.** Known naming; flag only new code that
  confuses the two.
- **`composer validate` warnings** about the `version` field, the unbound `magento/framework` constraint
  and the exact core pin. Deliberate (ADR-0001).
- **`AGENTS.md`**, unless a PR replaces the symlink to `CLAUDE.md` with a real file.
