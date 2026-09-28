---
type: ADR
title: ADR-0002 Sync checkouts synchronously from quote and checkout events, skipping unchanged payloads
timestamp: 2026-09-28T07:04:16Z
---

# ADR-0002: Sync checkouts synchronously from quote and checkout events, skipping unchanged payloads
- **Status:** Accepted
- **Date:** 2022-03-27

## Context
Yotpo needs the shopper's checkout (contact, addresses, line items) as early as possible to send
abandoned-cart messages. The first version synced from a JS billing-address mixin, a custom
controller and a `CartTotalRepository` plugin. Commit `a358e7a` ("Improve checkout sync triggers")
replaced them. The reasons are not recorded; this ADR is reconstructed from that commit and the code.

## Decision
- Every trigger calls `Model/Sync/Checkout/Processor.php::process()` through
  `Plugin/Quote/Model/AbstractCheckoutTrigger.php::checkoutSync()`, which needs a saved quote with a
  billing country:
  - `Quote::setBillingAddress`, `setShippingAddress` and `save` (`Plugin/Quote/Model/Quote.php`,
    global area);
  - the REST `ShippingAddressManagement::assign` and `BillingAddressManagement::assign`
    (`etc/webapi_rest/di.xml`).
- The checkout page (`Plugin/Checkout/DefaultConfigProviderPlugin.php`) and the consent save at the
  payment step (`Controller/SmsMarketing/SaveCustomerAttribute.php`) also call the processor.
- The call is synchronous. To avoid a request per trigger, `Model/Sync/Checkout/Data.php` keeps the
  last payload (without `checkout_date`) in the checkout session and returns nothing when it has not
  changed.
- Line-item products are synced first through core's catalog processor; a failed product sync
  cancels the checkout sync.

## Alternatives Considered
- **Client-side trigger (the old JS mixin and controller)**: replaced by `a358e7a`. Server-side hooks
  also cover REST and headless checkouts. Inferred, not recorded.
- **Queue or cron**: not used. It would delay the checkout that abandoned-cart messages depend on.
  Inferred, not recorded.

## Consequences
- Quote saves and address updates on the storefront wait for the Yotpo API (and the product sync)
  when checkout sync is enabled. A slow API slows checkout.
- The dedupe lives in the session: a new session, or a changed payload field, sends again.
- The first sync of a quote creates its abandoned-cart token (`Model/AbandonedCart/Data.php`).
