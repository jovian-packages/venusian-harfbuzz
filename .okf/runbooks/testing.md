---
type: Runbook
title: Testing
description: Pest suite on fonts the tests write; a workbench with a path repository to topside; Mac, Pi, gaming PC and CI.
resource: tests/
tags: [harfbuzz, pest]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-17T00:00:00-05:00 }
sources:
  - id: pest
    resource: tests/Pest.php
    title: tests/Pest.php
  - id: tinyfont
    resource: tests/Fixtures/TinyFont.php
    title: TinyFont
  - id: ci
    resource: .github/workflows/tests.yml
    title: tests.yml
---
# Steps
* No font installed is needed: `TinyFont` writes each test's font (glyphs and advances, a `kern` table, collections, and a variable font with `fvar`, `HVAR` and `gvar`), so every expected value sits in the test.[^tinyfont] The suite stops loudly without ext-harfbuzz.[^pest]
* Workbench, never the repo root: copy the package without `vendor/`, `.git` and lock files to a temporary directory; there, `composer config repositories.topside '{"type": "path", "url": "<dev>/venusian/topside"}'`, then `composer update` with a PHP that loads ext-harfbuzz (`php84 /opt/homebrew/bin/composer update`).
* Mac: `XDEBUG_MODE=off php84 -d memory_limit=128M vendor/bin/pest`, then `zhp`.
* Pi and gaming PC: ship a fresh clone of HEAD and of topside in the same layout (`~/typo-pkgs/jovian/venusian-harfbuzz`, `~/typo-pkgs/venusian/topside`), packed with `COPYFILE_DISABLE=1 tar --no-mac-metadata --no-xattrs`; build ext-harfbuzz there from its current source with its `install-debian-trixie.sh`; the same workbench, path repository relative.
* After: delete the workbench. The repo keeps no `vendor/` and no lock file.
* CI: `tests.yml` builds ext-harfbuzz from `php-io-extensions/harfbuzz` (branch `0.11.x`), clones `VenusianPHP/topside` beside the checkout as a path repository, and runs the suite on PHP 8.4 and 8.5, Ubuntu 24.04.[^ci]
* A test names the behaviour it protects. Break that logic once before trusting the test; when a break is tried on built code, check that an unbroken baseline passes in the same harness first.

[^pest]: tests/Pest.php
[^tinyfont]: TinyFont
[^ci]: tests.yml
