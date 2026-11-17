# Agent guidance — jovian/venusian-harfbuzz

1. **Read [`.okf/index.md`](.okf/index.md) first.** Open only the concepts the change touches.
2. **The contract is Topside's** (`Surface\Contracts\Typography\FontShaper`, `Surface\Typography\Shapers\FontShaperDriver`). Ask before assuming a change to it.
3. **HarfBuzz's answer, not another shaper's.** Every run is what HarfBuzz makes of the text and the font. Nothing here is made to match Lustrous or any other shaper.
4. **Dependencies:** venusian/topside and ext-harfbuzz only. Never ext-freetype (hb-ft), another jovian package or Lustrous, not even in tests. No FFI.
5. **A source's variations are applied, never dropped.** A variable font's instance comes from `FontSource::$variations`; a shaper that ignored them would shape the default instance without saying so.
6. **Tests** write their own fonts (`tests/Fixtures/TinyFont.php`), so every expected value sits in the test. A test names the behaviour it protects and fails when that logic is broken. Run them as [`.okf/runbooks/testing.md`](.okf/runbooks/testing.md) says: in a workbench, never the repo root.
7. **Leave nothing behind.** No `vendor/`, lock file, `composer.dev.json` or scratch file stays in the repo after a run; `.gitignore` hiding it does not make it gone.
8. **House files travel with the code.** A change that alters behaviour updates `README.md`, the `.okf` concept it touches (bump `generated.at`), `.okf/log.md`, and `SECURITY.md` when what the package reads or writes changes. `.github/workflows/tests.yml` runs the suite on GitHub.
9. **Version** 0.11.0, the framework's line. ext-harfbuzz must have `hb_face_count` and `hb_font_set_variations`.
