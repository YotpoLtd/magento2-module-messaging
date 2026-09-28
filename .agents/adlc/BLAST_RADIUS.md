# Blast Radius profile

## entryPoints

Magento calls this module only through its XML wiring; nothing imports it by path. The manifests are
the entry points:

- `etc/di.xml`, `etc/frontend/di.xml`, `etc/adminhtml/di.xml`, `etc/webapi_rest/di.xml` -- plugins,
  preferences and constructor arguments (every class named there is a root)
- `etc/events.xml`, `etc/adminhtml/events.xml` -- observers
- `etc/crontab.xml` -- cron classes (`Model/Sync/Customers/Cron/*`)
- `etc/frontend/routes.xml`, `etc/adminhtml/routes.xml` -- controllers under `Controller/**`
  (frontName `yotpo_messaging` and `yotpo_smsbump`)
- `etc/frontend/sections.xml` with the `sectionSourceMap` in `etc/frontend/di.xml` -- `CustomerData/*`
- `etc/adminhtml/system.xml` -- backend models and frontend blocks for config fields
- `view/*/layout/*.xml`, `view/base/ui_component/*.xml` -- blocks, view models and templates
- `view/frontend/requirejs-config.js` -- JS components
- `Setup/Patch/Data/*` -- run by `setup:upgrade`
- `registration.php` -- Composer autoload `files`

## areas

- customer-sync: `Model/Sync/Customers/`, `Observer/CustomerSaveAfter.php`, `Observer/CustomerAddressUpdate.php`, `Model/YotpoCustomersSync*`, `Model/ResourceModel/`, `Api/`
- checkout-sync: `Model/Sync/Checkout/`, `Plugin/Quote/`, `Plugin/Checkout/DefaultConfigProviderPlugin.php`
- abandoned-cart: `Model/AbandonedCart/`, `Controller/AbandonedCart/`, `Plugin/Customer/Account/LoginPost.php`, `Plugin/Checkout/Account/DelegateCreatePlugin.php`
- sms-consent: `Setup/Patch/`, `Plugin/Customer/`, `Plugin/CustomAttributeManagement/`, `Block/Form/`, `Model/Metadata/`, `Model/Attribute/`, `Controller/SmsMarketing/`, `view/frontend/templates/customer/`, `view/frontend/web/`
- storefront-scripts: `Model/Sync/Subscription/`, `Controller/Adminhtml/SyncForms/`, `ViewModel/`, `Block/BrowseAbandonment.php`, `Block/KlaviyoSubscriptionIntegration.php`, `Observer/SalesQuoteProductAddAfter.php`, `CustomerData/`, `Model/Session*`, `view/frontend/templates/`, `view/frontend/layout/`
- admin-config: `Block/Adminhtml/`, `Observer/Config/`, `Model/Config/`, `etc/adminhtml/`
- core: `etc/`, `Model/Config.php`, `Model/Sync/Main.php`, `Model/Sync/Data/AbstractData.php`, `Helper/`, `Model/Logger/`, `composer.json`, `registration.php` -- read by every flow, so a change here is cross-cutting by construction

## imports

aliases: { "Yotpo\\SmsBump\\": "" }

PSR-4 maps the namespace to the repository root (`composer.json:19-21`), so
`Yotpo\SmsBump\Model\Sync\Customers\Processor` is `Model/Sync/Customers/Processor.php`. PHP files import
with `use` statements, often under an alias (`use Yotpo\SmsBump\Model\Config as YotpoMessagingConfig`),
so search for the class name, not the alias.

What a text search for `use` misses:
- XML wiring: classes named only in `etc/**/*.xml` and layout XML (plugins, observers, preferences,
  cron, blocks). Search the fully qualified class name in `etc/` and `view/`.
- DI preferences replace classes for every caller: Magento's `Customer\Model\Metadata\Form\Checkbox`
  and `Eav\Model\Attribute\Data\Checkbox`, and core's `Yotpo\Core\Model\Sync\Customers\Processor`
  (`etc/di.xml:81-88`). Their reach is every caller of the replaced class, in Magento and `Yotpo_Core`.
- Templates are named as `Yotpo_SmsBump::<path>` strings (`Plugin/Customer/Form/*.php`, layout XML).
- JS is named by RequireJS id (`smsMarketingBase`, `Yotpo_SmsBump/js/...`) in `requirejs-config.js` and
  layout XML.
- Config is read by key through `Model/Config.php` `$smsBumpConfig` (`getConfig('<key>')`) and by path
  in `ifconfig` attributes.
- Session magic keys (`YotpoCheckoutData`, `YotpoCustomerData`, `YotpoSmsMarketing`,
  `yotpoQuoteToken`) are shared by string between classes.

## hotspots

- `etc/di.xml` -- global wiring, including the preferences over Magento and core classes
- `Model/Config.php` -- every flow reads its config and endpoints here
- `Model/Sync/Main.php` -- every API call
- `Plugin/Quote/Model/Quote.php` and `Plugin/Quote/Model/AbstractCheckoutTrigger.php` -- run on every quote save in every area
- `Model/Sync/Checkout/Processor.php`, `Model/Sync/Checkout/Data.php` -- inside storefront quote saves and the checkout page
- `view/frontend/layout/default.xml` -- head scripts on every storefront page
- `composer.json`, `etc/module.xml`, `registration.php` -- the package, its version and its dependencies

## generatedFiles

- `etc/db_schema_whitelist.json`

## tables

### criticality

- 1.0: the merchant's storefront and checkout -- `Plugin/Quote/**`, `Plugin/Checkout/**`, `Model/Sync/Checkout/**`, `Controller/AbandonedCart/**`, `Plugin/Customer/Account/LoginPost.php`, `view/frontend/layout/default.xml` and its templates, `etc/di.xml` preferences; install and upgrade -- `Setup/**`, `etc/db_schema.xml`, `composer.json`, `etc/module.xml`, `registration.php`
- 0.7: customer account forms and saves -- `Plugin/Customer/Form/*`, `Observer/CustomerSaveAfter.php`, `Observer/CustomerAddressUpdate.php`, the checkbox renderers, `Controller/SmsMarketing/*`, `view/frontend/templates/customer/**`
- 0.4: admin config pages and admin actions -- `etc/adminhtml/**`, `Block/Adminhtml/**`, `Controller/Adminhtml/**`, `Observer/Config/**`
- 0.2: cron and CLI batches -- `Model/Sync/Customers/Cron/*`, `Model/Sync/Customers/Services/*`, the batch path of `Model/Sync/Customers/Processor.php`; logging
- 0.1: never on a merchant's path -- `docs/**`, `*.md`, `.gitignore`, the agent files

### change-type

- 0.95: irreversible or trust-breaking -- `etc/db_schema.xml` column or table removals, a new or edited `Setup/Patch/**`, a version bump or core pin change in `composer.json` / `etc/module.xml`, the abandoned-cart token and `LoadCart` checks, customer logout or login redirects, a new host in `etc/csp_whitelist.xml`, output escaping in templates, the SMS-consent value sent to Yotpo, `.agents/adlc/**`, `CODEOWNERS`, `.claude/protected-files.json`
- 0.75: shared infrastructure -- `etc/di.xml` preferences and plugins, `Model/Config.php`, `Model/Sync/Main.php`, a new plugin or observer on a Magento core class, a new Composer dependency, PHP syntax that raises the minimum PHP version
- 0.50: logic on a hot path (checkout sync, quote plugins, customer save observers) or a payload field change; any refactor (there are no tests)
- 0.30: contained changes in one flow's `Data.php` or a batch/cron path
- 0.15: admin labels, default config values in `etc/config.xml`, log messages, CSS
- 0.05: comments, docs, formatting, dead-code removal -- `docs/**`, `*.md`
