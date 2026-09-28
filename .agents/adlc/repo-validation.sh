#!/usr/bin/env bash
#
# Canonical full validation for this repository. Scaffold from
# `yotpo-common:generate-harness-docs`; the owning team writes the commands.
#
# Contract -- do not change it, the pipeline depends on it:
#   - run from the repository root, no arguments
#   - echo each command before running it
#   - exit 0  every check passed
#   - exit 1  a check failed
#   - exit 2  not configured / cannot validate -- NOT a pass
#   - never commit, never push, never mutate git state
#
# Exit 2 exists so "cannot validate" is never read as "failed" (hides a broken
# environment) or as "passed" (an unconfigured repo would earn ready-for-merge).
#
# KNOWN DIVERGENCE FROM CI -- list here anything CI runs that this
# script cannot; such a PR can pass this script, earn the label, and still fail
# CI. If there is no divergence, say so -- do not delete the section.
#
# - There is no CI in this repository (no .github/workflows/, never had one),
#   so there is nothing to diverge from. The only real build is in a merchant's
#   Magento 2 install (README.md:21-45: composer require, setup:upgrade,
#   setup:di:compile, setup:static-content:deploy), which needs Magento, a
#   database and yotpo/module-yotpo-core. This script never runs those.
#
# TODO(ai-dlc): no check command traces to a CI step, script or doc that can
# run without a Magento install, so this script validates nothing and exits 2.
# The team should pick the checks (candidates: `php -l` on every *.php/*.phtml
# on the lowest supported PHP, `xmllint --noout` on etc/**/*.xml, and
# `composer validate --no-check-publish`), document them, and then run them
# below. Delete the exit-2 block once real commands are here.

set -euo pipefail

if [ ! -f "./composer.json" ] || [ ! -f "./registration.php" ] || [ ! -f "./etc/module.xml" ]; then
  echo "repo-validation: not at repo root ($(pwd)) -- run from the repository root" >&2
  exit 2
fi

echo "repo-validation: not configured -- no traced check exists for this repository." >&2
echo "  Nothing was validated and nothing may claim to have passed." >&2
exit 2
