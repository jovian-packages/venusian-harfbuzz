# venusian-harfbuzz

The `harfbuzz` text shaper for Topside's Typography. HarfBuzz turns text into positioned glyphs through
[ext-harfbuzz](https://github.com/php-io-extensions/harfbuzz): ligatures, kerning, mark placement and the scripts the
font's OpenType tables shape.

```bash
composer require jovian/venusian-harfbuzz
```

The service provider registers `harfbuzz` on Typography's shapers manager. Pick it in Topside's config:

```php
// config/typography.php
'shaper' => ['default' => env('TOPSIDE_FONT_SHAPER', 'harfbuzz')],
```

## What it does

`HarfBuzzFontShaper::shape()` answers a `ShapedRun`: glyph ids, the byte each came from, advances and offsets in drawing
units.

- **HarfBuzz opens the font itself** (`hb_blob_create_from_file`, `hb_face_create`, `hb_font_create`). ext-freetype is
  not needed.
- **One font a source**, kept for the shaper's life. A source that names an instance of a variable font
  (`FontSource::$variations`) is shaped at that instance (`hb_font_set_variations`): HarfBuzz clamps each value to its
  axis and ignores an axis the font lacks.
- **Size:** the font's scale is the size in 1/65536ths, so positions divided by 65536 are drawing units. Sizes above 0 up
  to 32767 are shaped; others are refused.
- **Direction:** the run is laid out left to right, as the contract asks. HarfBuzz guesses the script and language.
- **A source HarfBuzz cannot read is refused:** a missing file, a file that is not a font, or a face index the file does
  not have (`hb_face_count`). HarfBuzz alone would shape those as the missing glyph.

## Tests

`vendor/bin/pest`, with ext-harfbuzz loaded. The tests write their own fonts (`tests/Fixtures/TinyFont.php`: glyphs,
advances, a `kern` table, collections, and a variable font with `fvar` and `HVAR`), so every expected value is in the
test.

For local development against a Topside checkout, keep a `composer.dev.json` (gitignored) that adds a path repository
to it, and install with `COMPOSER=composer.dev.json composer install` on a PHP that loads ext-harfbuzz.

## License

MIT.
