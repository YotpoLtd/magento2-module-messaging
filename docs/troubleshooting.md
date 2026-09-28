---
type: Troubleshooting
title: magento2-module-messaging Troubleshooting
timestamp: 2026-09-28T07:04:16Z
---

# Troubleshooting

Logs are in the merchant's `var/log/yotpo/`: `customers.log`, `checkout.log`, `subscription.log`
(`Model/Sync/*/Logger/Handler.php:20`). Admins can download them from the Yotpo config page.

## Common Issues

| Symptom | Cause | Fix |
|---------|-------|-----|
| Customers never reach Yotpo | Yotpo or customer sync is off for the store (`Model/Config.php:170-173`), or Magento cron does not run the group `yotpo_messaging_customers_sync` | Check `customers.log` for "Customer sync is disabled"; check the cron group and the frequency (`etc/crontab.xml`, default `*/2 * * * *`) |
| The same customers are never picked again | `synced_to_yotpo_customer` is already 1. The batch selects only null or 0 (`Model/Sync/Customers/Main.php:96-104`); errors also set it to 1 (`Model/Sync/Customers/Processor.php:216-219`) | Retriable failures are resent by the retry cron from `yotpo_customers_sync.should_retry`; others need core's reset (`yotpo:resetsync`) |
| A customer failed with code 500 and is not retried | An exception while syncing writes `response_code 500`, `should_retry 0` (`Model/Sync/Customers/Main.php:163-178`) | Read the exception in `customers.log`; fix the data; reset the sync |
| "Failed to process Customers ... Reason: %3" with a literal `%3` | The message has a `%3` placeholder but no third argument (`Model/Sync/Customers/Processor.php:147-153`) | Known. The exception text is not logged on that path |
| Checkout never synced | Checkout sync is off, the quote has no billing country, no customer id or email, or the payload equals the one in the session (`Model/Sync/Checkout/Data.php`, `Plugin/Quote/Model/AbstractCheckoutTrigger.php:61-75`) | Read `checkout.log` for "Did not sync Checkout" or "Failed to sync Checkout" |
| "products sync to Yotpo failed" in `checkout.log` | Core's catalog processor could not sync the line-item products, so the checkout is not sent (`Model/Sync/Checkout/Processor.php`) | Fix the product sync in `Yotpo_Core` first |
| Storefront checkout is slow | Checkout sync calls the Yotpo API synchronously on quote saves and address updates ([ADR-0002](adr/0002-checkout-sync-on-quote-events.md)) | Check API latency in `checkout.log`; turning off checkout sync removes the call |
| The abandoned-cart link lands on the home page with "Quote not found" | Unknown token, or the quote is inactive (already ordered) or deleted (`Controller/AbandonedCart/LoadCart.php`) | Expected for converted carts. Check `yotpo_abandoned_cart` for the token |
| The abandoned-cart link logs the shopper out | Another customer was logged in on that browser; `LoadCart` logs them out and asks for login | Expected behaviour |
| SMS consent checkbox missing on registration or account edit | Yotpo is disabled, or the EE template is used on Adobe Commerce (`Plugin/Customer/Form/Register.php:31-39`) | Check `yotpo_core/settings/active` and whether `Magento_CustomerCustomAttributes` is enabled |
| Subscription forms do not show | The forms were never synced (admin "Sync forms" button), or Yotpo is inactive for the store (`view/frontend/layout/default.xml` `ifconfig`) | Press "Sync forms" on the store scope; read `subscription.log` |
| Browser console CSP errors for Yotpo scripts | The script host is not in `etc/csp_whitelist.xml` | A new host needs a module release |
| `composer require` fails on `yotpo/module-yotpo-core` | The exact core version in `composer.json:12` is not installable next to the merchant's other Yotpo modules | Install matching versions of both modules ([ADR-0001](adr/0001-build-on-yotpo-core-module.md)) |

## CI Failures

There is no CI in this repository: no `.github/workflows/`, no Jenkins file, and nothing in the git
history ever added one. Nothing checks a PR automatically.

| Error Message | Meaning | Resolution |
|--------------|---------|-----------|
| (none) | No CI runs | Verify by hand in a Magento install (`README.md:21-45`) |

## Known inconsistencies (not fixed)

- `composer.json:10` allows PHP `~5.6.0`, but the code uses nullable types (`?AddressInterface`,
  `Plugin/Quote/Model/Quote.php:21`), which need PHP 7.1, and typed properties and arrow
  functions (`CustomerData/CustomerBehaviour.php:14-16`, `Observer/SalesQuoteProductAddAfter.php`),
  which need PHP 7.4.
- `README.md` still carries text from the Reviews extension: the manual install puts this repository
  under `app/code/Yotpo/Yotpo` (`README.md:36`), and Usage points to "Yotpo Product Reviews Software"
  (`README.md:51`). The module is `Yotpo_SmsBump` (`registration.php:4`), so the Magento path is
  `app/code/Yotpo/SmsBump`.
- `Model/Sync/Customers/Processor.php:256-257` reads the customer's store id and then overwrites it
  with the current store id.
- In `Model/Sync/Checkout/Data.php` the list of non-simple product types names
  `ProductTypeGrouped::TYPE_CODE` twice.
