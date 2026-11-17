# Log

## 2026-11-17

* Created the bundle. README and SECURITY.md in the house style; `.github/workflows/tests.yml`. The suite runs in a workbench, never the repo root: `composer.dev.json` and the repo's own `vendor/` are gone. [testing](runbooks/testing.md)

## 2026-11-16

* **0.11.0**: the `harfbuzz` text shaper for Topside's Typography, on ext-harfbuzz alone. HarfBuzz opens the font itself; ext-freetype is not used. [shaper](architecture/shaper.md)
* **Unreadable sources are refused** through `hb_face_count`: HarfBuzz reads a missing file or a non-font as an empty face and shapes it as glyph 0, and takes any index of a single font as its one face. [shaper](architecture/shaper.md)
* **Variable fonts**: a source's coordinates go to `hb_font_set_variations`. Needs ext-harfbuzz with `hb_face_count` and `hb_font_set_variations`. 9 passed on Homebrew PHP 8.4 NTS and ZTS, the Pi (HarfBuzz 10.2) and Ubuntu 24.04 (8.3.0). [shaper](architecture/shaper.md)
