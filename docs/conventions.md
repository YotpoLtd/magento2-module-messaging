---
type: Conventions
title: magento2-module-messaging Conventions
timestamp: 2026-09-28T07:04:16Z
---

# Coding Conventions

No linter, formatter or static analysis runs in this repository, and there is no CI. The code follows
the Magento 2 coding standard by hand; the `// phpcs:ignore` and `@phpstan-ignore-next-line`
comments (37 of them) come from one-off PHPCS/PHPStan clean-ups (commits `82fff97`, `4dca12a`) [2].
`docs/Maintenance.md` describes PhpStorm's Php Inspections plugin. Match the file you are editing.

## File Organization
Standard Magento 2 module layout, PSR-4 `Yotpo\SmsBump\` at the repository root (`composer.json:19-21`):
- One class per file; the path is the namespace (`Model/Sync/Customers/Processor.php` →
  `Yotpo\SmsBump\Model\Sync\Customers\Processor`).
- A sync flow is a folder under `Model/Sync/<Entity>/` with `Processor.php` (orchestration),
  `Data.php` (payload), `Logger.php` and `Logger/Handler.php` (its log file). Canonical example:
  `Model/Sync/Checkout/`.
- Wiring is XML only: global `etc/di.xml` and `etc/events.xml`; area files in `etc/frontend/`,
  `etc/adminhtml/`, `etc/webapi_rest/`.
- Plugins mirror the class they intercept: `Plugin/Quote/Model/Quote.php` wraps
  `Magento\Quote\Model\Quote`.
- Adobe Commerce (EE) variants of templates end in `-ee.phtml`, picked at runtime when
  `Magento_CustomerCustomAttributes` is enabled (`Plugin/Customer/Form/Register.php:36-38`).

## Naming
| Thing | Convention | Example |
|-------|-----------|---------|
| Variables, properties | camelCase, descriptive | `$isCustomerAccountShared` (`Model/Sync/Customers/Processor.php:200`) |
| Methods | camelCase, verb first | `insertOrUpdateCustomerSyncData` (`Model/Sync/Customers/Main.php:186`) |
| Classes | PascalCase; import aliases prefixed with the owner | `use Yotpo\Core\Model\Config as CoreConfig` (`Model/Config.php:17`) |
| Constants | SCREAMING_SNAKE on the class | `YOTPO_CUSTOM_ATTRIBUTE_SMS_MARKETING` (`Model/Config.php:27`) |
| DI names (plugins, observers) | `yotpo_smsbump_<target>` | `yotpo_smsbump_customer_save_after` (`etc/events.xml`) |
| Config paths | under core's section `yotpo_core/...` | `yotpo_core/sync_settings/customers_sync/enable` (`Model/Config.php:67`) |
| Tables, attributes | `yotpo_` prefix, snake_case | `yotpo_customers_sync`, `yotpo_accepts_sms_marketing` |

## Error Handling
- Pattern: a sync never breaks the merchant's request or cron. Processors catch `Exception`, log it,
  and continue with the next store or customer (`Model/Sync/Customers/Processor.php:113-165`,
  `:205-229`).
- A failed customer is recorded, not retried forever: `response_code 500`, `should_retry 0`
  (`Model/Sync/Customers/Main.php:163-178`). Retriable HTTP codes set `should_retry 1`
  (`Model/Sync/Customers/Main.php:140-155`).
- Storefront endpoints return a status instead of throwing (`Controller/SmsMarketing/SaveCustomerAttribute.php:106-111`,
  `Controller/AbandonedCart/LoadCart.php` redirects home with a message).
- Never: let an API or data error escape into a customer save, quote save or checkout page.

## Logging
- Each flow has its own log file under `var/log/yotpo/`: `customers.log`, `checkout.log`,
  `subscription.log` (`Model/Sync/*/Logger/Handler.php:20`). The files are also registered with core's
  API logger (`etc/di.xml:3-62`), so API calls log there too.
- Levels: everything is `info`, including failures (`Model/Sync/Checkout/Processor.php`).
- Format: a translated sentence with store id, store name, entity id and the reason:
  `__('Failed to sync ... - Magento Store ID: %1, ...', $storeId, ...)`
  (`Model/Sync/Customers/Processor.php:220-228`).
- Admins download the logs from the config page (`Block/Adminhtml/System/Config/Form/Field/Link/*`).

## Testing
- There are no tests: no `Test/` directory, no PHPUnit config, no CI.
- Changes are verified by hand in a Magento 2 install with `Yotpo_Core` (`README.md:21-45`).
- Canonical example: none.

## Do's and Don'ts
| Do (with file:line ref) | Don't | Why |
|--------------------------|-------|-----|
| Hook in with a plugin or observer in the right area's XML (`etc/frontend/di.xml:23-39`) | Edit or copy a Magento or `Yotpo_Core` class | The module must survive Magento and core upgrades |
| Call the API only through `Model/Sync/Main.php::sync()` with an `entityLog` (`Model/Sync/Customers/Processor.php:388-392`) | Call core's `Request` or an HTTP client directly | Loses the per-flow log and core's retry |
| Guard a real-time sync with the `custSync` request param (`Observer/CustomerSaveAfter.php:90-97`) | Save a customer from inside a sync without the guard | `customer_save_after` fires again and syncs twice |
| Wrap store emulation in `try`/`finally` (`Model/Sync/Customers/Processor.php:113-165`) | Return early inside an emulated block without `stopEnvironmentEmulation()` | The rest of the request runs in the wrong store |
| Add a new data patch for attribute changes (`Setup/Patch/Data/`) | Edit an existing patch | Applied patches never run again on merchant installs |
| Change `etc/db_schema.xml` and regenerate `etc/db_schema_whitelist.json` together | Drop or rename a column | Declarative schema drops data on `setup:upgrade` |
| Use `?Type $x = null` for nullable parameters (`Plugin/Quote/Model/Quote.php:21`) | Implicitly nullable typed parameters | Deprecated in PHP 8.4 (commits `a0aaf58`, `bce6d6f`) |
| Escape template output with the escaper (`view/frontend/templates/sync_forms.phtml:20-21`) | Print request or API data raw | It runs on every merchant storefront |

## Anti-Patterns

What agents should NEVER do in this repo:
| Anti-Pattern | Why It's Dangerous | See Also |
|--------------|-------------------|----------|
| Bumping `composer.json` `version` without `etc/module.xml` `setup_version` (or the reverse) | Merchants get a module whose declared versions disagree | commit `63af7fa` |
| Changing the `yotpo/module-yotpo-core` pin to a version that is not released | `composer require` fails for every merchant | [ADR-0001](adr/0001-build-on-yotpo-core-module.md) |
| Creating a git tag or GitHub release | A tag is a public release that merchants install | ARCHITECTURE.md "Release" |
| Adding work (an extra API call, a heavy query) to the checkout sync path | It runs synchronously in quote saves on the storefront | [ADR-0002](adr/0002-checkout-sync-on-quote-events.md) |
| Adding a host to `etc/csp_whitelist.xml` | It allows scripts from that host on every merchant storefront | `etc/csp_whitelist.xml` |
| Changing the abandoned-cart token or the checks in `LoadCart` | The token restores a shopper's cart and can log out the current customer | `Controller/AbandonedCart/LoadCart.php` |
| Syntax newer than the PHP versions merchants run | Fatal error on install for those merchants | `composer.json:10` |

## Patterns Library

Common patterns. Reference these BEFORE inventing your own:

### Per-store batch sync
- **When to use:** a cron or CLI job over all stores.
- **Canonical implementation:** `Model/Sync/Customers/Processor.php:107-167`.
- **How it works:** loop over `getAllStoreIds(false)`, emulate the store, skip when the feature is off
  for that store, process a limited batch, and stop emulation in `finally`.

### Real-time sync with session dedupe
- **When to use:** a sync triggered by a storefront event that can fire several times.
- **Canonical implementation:** `Model/Sync/Customers/Data.php:172-179`, `Model/Sync/Checkout/Data.php:202-216`.
- **How it works:** build the payload, compare its JSON with the copy kept in the session, return
  `[]` when equal, otherwise store the new copy and send.

### Sync bookkeeping row
- **When to use:** recording an entity's sync result for retries.
- **Canonical implementation:** `Model/Sync/Customers/Main.php:140-189`.
- **How it works:** build a row with `response_code`, `should_retry` and `synced_to_yotpo`, and
  `insertOnDuplicate` it on the `(customer_id, store_id)` unique key.

### New config value
- **When to use:** a new admin setting.
- **Canonical implementation:** `Model/Config.php:61-106`, `etc/adminhtml/system.xml`, `etc/config.xml`.
- **How it works:** add the field under core's `yotpo_core` section, a default in `config.xml`, and a
  key → path entry in `$smsBumpConfig`; read it with `getConfig('<key>')`.

# Citations

[1] `README.md` and `docs/Maintenance.md` (repository docs)
[2] Commits `82fff97` "Fix coding standard issues - PHPCS,PHPSTAN" and `4dca12a` "Fix PHPSTAN errors" (git history)
[3] Commits `a0aaf58` and `bce6d6f`, PHP 8.4 nullable parameters (git history)
[4] Commit `a358e7a`, "Improve checkout sync triggers" (git history)
