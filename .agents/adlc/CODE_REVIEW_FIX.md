# Code Review Fix instructions

Check commands come only from `.agents/adlc/REPO_CHECKS.md`.

## Convention pointers

The canonical examples to copy (`docs/conventions.md` has the full tables and the Patterns Library):

- **Per-store batch:** loop stores, emulate, skip when disabled, `finally` stop emulation --
  `Model/Sync/Customers/Processor.php:107-167`.
- **API call:** `Model/Sync/Main.php::sync($method, $url, $data + ['entityLog' => '<flow>'], 'api', true)`
  -- `Model/Sync/Customers/Processor.php:388-392`.
- **Real-time dedupe:** compare the JSON payload with the session copy and return `[]` when equal --
  `Model/Sync/Customers/Data.php:172-179`.
- **Recursion guard on saves:** the `custSync` request param (`Observer/CustomerSaveAfter.php:90-97`)
  and the registry flags in `Plugin/Quote/Model/Quote.php`.
- **Log line:** `$logger->info(__('<What> - Magento Store ID: %1, ...', $storeId, ...))`, one placeholder
  per argument -- `Model/Sync/Customers/Processor.php:220-228`.
- **Nullable parameters:** `?Type $x = null` -- `Plugin/Quote/Model/Quote.php:21`.

## Stack specifics

- **Nothing can execute a fix here.** No tests, no linter, no CI, and the code needs a Magento install
  with `Yotpo_Core` (`REPO_CHECKS.md`). Keep each edit to the lines the finding names, and say in the
  report that the change is unexecuted.
- **Wiring is XML.** A class renamed or moved must be renamed in every `etc/**/*.xml` and layout XML
  that names it; a PHP search alone misses them.
- **Supported versions.** Do not use syntax newer than the code already uses. The newest in use is
  PHP 7.4 (typed properties in `CustomerData/CustomerBehaviour.php:14-16`, arrow functions in
  `Observer/SalesQuoteProductAddAfter.php`); PHP 8 syntax (`match`, enums, `readonly`, constructor
  promotion, named arguments) is not used anywhere. Keep `?Type` nullable parameters.
- **`Yotpo_Core` is out of reach.** A finding whose fix belongs in core (API client, config base class,
  catalog sync) is out of scope here. Report it.
- **No formatter.** Don't reformat lines the finding does not cover.

## Protected paths

`.claude/protected-files.json` is the source of truth. For this repo in particular:

- **Never write** (human-required): `etc/db_schema.xml`, `etc/db_schema_whitelist.json`, `Setup/**`,
  `composer.json`, `etc/module.xml`, `registration.php`, `Api/**`, `etc/csp_whitelist.xml`,
  `Controller/AbandonedCart/**`, `Model/AbandonedCart/**`, `Plugin/Customer/Account/LoginPost.php`,
  the license files, `.github/workflows/**`, `.agents/adlc/**`, `CODEOWNERS`,
  `.claude/protected-files.json`. A finding that needs one of these (a schema change, a version bump,
  a token or login change, a new CSP host) is out of scope. Report it; don't work around it.
- **Review-required:** everything else under `etc/`, `Model/`, `Plugin/`, `Observer/`, `Controller/`,
  `Block/`, `CustomerData/`, `Helper/`, `ViewModel/`, `view/`, plus `README.md`, `.gitignore`,
  `CLAUDE.md`, `AGENTS.md`.
- **Never** create a tag or a release, and never bump the module version as part of a fix.

## On-demand CI run

There is none. This repository has no CI workflow at all, so there is no `ci-on-demand.yaml` to call
and a push runs nothing. Do not add a workflow as part of a fix.
