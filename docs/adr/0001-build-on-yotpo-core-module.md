---
type: ADR
title: ADR-0001 Build on Yotpo_Core and pin it to an exact version
timestamp: 2026-09-28T07:04:16Z
---

# ADR-0001: Build on `Yotpo_Core` and pin it to an exact version
- **Status:** Accepted
- **Date:** 2021-07-13

## Context
Yotpo ships several Magento 2 modules. They all need the same Yotpo credentials, the same HTTP client
with retries, the same store and scope handling, the same admin section and the same catalog sync.
This module was added on top of `yotpo/module-yotpo-core` from its first commit (`8d26145`).
The record of why is not in the repository; this ADR is reconstructed from the code and history.

## Decision
- `Yotpo_SmsBump` loads after `Yotpo_Core` (`etc/module.xml:4-6`) and requires
  `yotpo/module-yotpo-core` at one exact version (`composer.json:12`, e.g. `4.3.10` since `63af7fa`).
- Shared behaviour is inherited, not copied: `Model/Config.php` extends core's config,
  `Model/Sync/Customers/Main.php` extends core's customers processor, services extend core's
  `AbstractJobs`, and the data patches extend core's attribute patches.
- API calls go through core's `Yotpo\Core\Model\Api\Request` via `Model/Sync/Main.php`.
- This module overrides core where it must, through DI (`etc/di.xml`: the preference for
  `Yotpo\Core\Model\Sync\Customers\Processor`, and the arguments for `RetryYotpoSync`,
  `CustomSystemMessage` and `Sync\Reset`).

## Alternatives Considered
- **A self-contained module**: not chosen. It would duplicate credentials, HTTP and admin
  configuration that other Yotpo modules share. Inferred from the structure, not recorded.
- **A version range for core**: not used. Every release pins one core version (`composer validate`
  warns about it). Inferred: the two modules change together, and the classes here depend on core
  internals.

## Consequences
- Most releases here are "bump version, require the new core" (commits `63af7fa`, `9f39f83`, `8084c3d`).
  A core release usually needs a release here.
- A change in a core class this module extends can break this module without any change here.
- `composer require` fails for merchants if the pinned core version is not published.
