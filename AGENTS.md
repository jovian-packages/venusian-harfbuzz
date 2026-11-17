# Agent guidance — jovian/venusian-harfbuzz

1. **The contract is Topside's** (`Surface\Contracts\Typography\FontShaper`, `Surface\Typography\Shapers\FontShaperDriver`). Ask before assuming a change to it.
2. **HarfBuzz's answer, not another shaper's.** Every run is what HarfBuzz makes of the text and the font. Nothing here is made to match Lustrous or any other shaper.
3. **Dependencies:** venusian/topside and ext-harfbuzz only. Never ext-freetype (hb-ft), another jovian package or Lustrous, not even in tests. No FFI.
4. **A source's variations are applied, never dropped.** A variable font's instance comes from `FontSource::$variations`; a shaper that ignored them would shape the default instance without saying so.
5. **Tests** write their own fonts (`tests/Fixtures/TinyFont.php`), so every expected value sits in the test. A test names the behaviour it protects and fails when that logic is broken.
6. **Version** 0.11.0, the framework's line. ext-harfbuzz must have `hb_face_count` and `hb_font_set_variations` (3b41cea or later).
