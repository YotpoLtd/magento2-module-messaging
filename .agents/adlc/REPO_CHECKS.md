# Repository check instructions

## Environment

- **PHP and Composer.** `composer.json:10` allows `php ~5.6.0|^7.0|^8.0`; nothing pins one version
  (no `.php-version`, no `composer.lock`, no image). The code already needs PHP 7.4+ in places
  (`docs/troubleshooting.md`, "Known inconsistencies").
- **This repository is not buildable on its own.** It is a Magento 2 module: every class extends or
  uses Magento framework and `Yotpo_Core` classes, and there is no `vendor/`. The only documented build
  steps (`README.md:21-45`: `composer require yotpo/module-yotpo-messaging`, `php bin/magento
  setup:upgrade`, `setup:di:compile`, `setup:static-content:deploy`) run inside a full Magento 2
  install with a database. A missing Magento class or `bin/magento` means "no Magento install here",
  not broken code.
- TODO(ai-dlc): the agent image, and that it is not the runtime image. There is no agent image and
  no runtime image in this repository; the runtime is each merchant's own Magento.

## Always, on every changed file

**There is no formatter in this repository** -- no PHP-CS-Fixer, no EditorConfig, no Prettier. Do not
add one, and do not reformat lines as a side effect of a fix.

**There is no linter and no static analysis that runs.** No PHPCS or PHPStan config exists, and no CI
exists. `docs/Maintenance.md` describes only the PhpStorm "Php Inspections (EA Extended)" plugin,
which has no command line. The existing `// phpcs:ignore` and `@phpstan-ignore-next-line` comments are
leftovers of one-off clean-ups (commits `82fff97`, `4dca12a`); keep them.

```
none -- there is no lint command
```

Nothing can fix violations automatically, because nothing reports them. There is no linter config and
no suppression list.

- **A violation your own edit introduced is part of the finding you are fixing** --
  iterate until it is clean. Never report a finding fixed while its checks are red, and
  never weaken or silence a check to get a commit through.
- **Pre-existing violations in code you did not touch are out of scope.** Leave them;
  fixing them widens the diff past the finding.
- **Reverting is the last resort, not the first move.** Only when the retry budget in
  the `code-review-fixer` skill is spent -- the rule is unclear, or satisfying it would
  change behaviour -- back that finding's edit out and report it unfixed, with the
  check's own message as the reason.

## By kind of change

Run the narrowest thing that covers what you touched.

| Change touches | Run |
|---|---|
| `**/*.php`, `**/*.phtml` | TODO(ai-dlc): no traced check. The only documented compile step is `php bin/magento setup:di:compile` in a Magento install (`README.md:28`), which agents do not have. Until the team names a check (for example `php -l`), run nothing and say the change is unexecuted |
| `etc/**/*.xml`, `view/**/*.xml` | TODO(ai-dlc): no traced check. Magento validates these against their XSDs only in an install (`setup:upgrade`, `setup:di:compile`, `README.md:27-28`) |
| `view/frontend/web/**` (JS, LESS, Knockout templates) | TODO(ai-dlc): no traced check. `php bin/magento setup:static-content:deploy` (`README.md:29`) needs an install |
| `composer.json`, `etc/module.xml`, `registration.php` | nothing locally -- these are human-required. Merchants' `composer require` (`README.md:25`) is the only real check |
| Markdown, `docs/**` only | nothing |

A change spanning several areas runs each area's row, not the full build.

## Full validation

```
bash .agents/adlc/repo-validation.sh
```

The canonical pre-merge run, and the single definition of "everything passed" -- see the
script's header for the exit-code contract. Not part of the fix loop: run it once at the
end of a pass.

## Do not run

- **`bin/magento` against any real store, and `composer require` of this package into a Magento
  install** (`README.md:24-32`). They need a full Magento with a database, and `setup:upgrade`
  applies this module's schema and data patches, which cannot be undone.
- **Dependency re-resolution** unless a dependency actually changed -- it re-fetches the whole graph
  over the network every time. Here: `composer install`, `composer update` or `composer require` in
  this repository. There is no `composer.lock`, and `yotpo/module-yotpo-core` must resolve from Yotpo's
  published packages.
- **Anything that writes to the default branch**, publishes an artifact, pushes an image,
  or triggers a deploy. Here: `git tag` / `git push --tags` and GitHub releases. A version tag on
  `master` is the public release merchants install (`4.3.9` is the tag on `c5a7b15`). Also never bump
  `composer.json` `version` or `etc/module.xml` `setup_version` unless the PR is a release.
- **`git push --force`**, and any rebase to sync the branch.
