# jovian/venusian-harfbuzz

HarfBuzz for Venusian Topside's Typography: the `harfbuzz` text shaper. HarfBuzz turns text into positioned glyphs: ligatures, kerning, mark placement and the scripts a font's OpenType tables shape.

## Requirements

- Linux or macOS, PHP 8.4+.
- [ext-harfbuzz](https://github.com/php-io-extensions/harfbuzz) 0.11 with `hb_face_count` and `hb_font_set_variations`, built against HarfBuzz 8.3.0 or later. ext-freetype is not needed.

## Install

```bash
composer require jovian/venusian-harfbuzz
```

The provider registers `harfbuzz` on Typography's shapers manager. Name it in `config/typography.php`, or in the environment:

```php
'shaper' => ['default' => env('TOPSIDE_FONT_SHAPER', 'harfbuzz')],
```

## Usage

Nothing changes in application code. Text is set through Typography, and its glyphs and positions are HarfBuzz's:

```php
use Surface\Typography\Typography;

$text = Typography::set('Office affairs', Typography::font('body'), width: 320);   // 'ffi' and 'ff' as ligatures, where the font has them
```

A shaper on its own:

```php
use Surface\Contracts\Typography\FontSource;

$shaper = app('typography.shapers')->driver('harfbuzz')->shaper();
$run = $shaper->shape('AV', new FontSource('/Library/Fonts/SF-Pro.ttf', 0, ['wght' => 650]), 14.0);

$run->glyphs;      // [19, 301]
$run->advances;    // [8.35, 9.39] in drawing units at size 14: the pair is kerned
```

## Behaviour

- **HarfBuzz opens the font itself** (`hb_blob_create_from_file`, `hb_face_create`, `hb_font_create`).
- **One font a source,** kept for the shaper's life. A source is a file, a face index and, for a variable font, its variation coordinates.
- **Variable fonts.** A source's coordinates are set on its font (`hb_font_set_variations`). HarfBuzz clamps each value to its axis and ignores an axis the font lacks.
- **Size.** The font's scale is the size in 1/65536ths, so positions divided by 65536 are drawing units. Sizes above 0 up to 32767 are shaped; others are refused.
- **Direction.** A run is laid out left to right, as the contract asks. HarfBuzz guesses the script and language.
- **Refusals.** A `TypographyException` names a source HarfBuzz cannot read: a missing file, a file that is not a font, or a face index the file does not have (`hb_face_count`). HarfBuzz alone would shape those as the missing glyph.

## Testing

See [.okf/runbooks/testing.md](.okf/runbooks/testing.md). The tests write their own fonts, so they need no font installed.

## License

MIT. See [LICENSE](LICENSE).
