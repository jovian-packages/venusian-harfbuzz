<?php

namespace Jovian\Typography\HarfBuzz;

use hb_buffer_t;
use hb_font_t;
use hb_glyph_info_t;
use hb_glyph_position_t;
use hb_variation_t;
use Surface\Contracts\Typography\FontShaper;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\ShapedRun;
use Surface\Contracts\Typography\TypographyException;

/**
 * Shapes with HarfBuzz through ext-harfbuzz: HarfBuzz opens the file itself (blob, face, font), and
 * shapes with its OpenType tables (GSUB, GPOS, and the 'kern' table when the face has no GPOS). A
 * source that names an instance of a variable font is shaped at it: each source has its own font,
 * with the source's variation coordinates set.
 *
 * The run is laid out left to right, as the contract asks: the buffer's direction is set to LTR and
 * HarfBuzz guesses its script and language. The font's scale is the size in 1/65536ths, so HarfBuzz's
 * positions divided by 65536 are drawing units.
 */
final class HarfBuzzFontShaper implements FontShaper
{
    /** The scale is an int: sizes up to this, in drawing units. */
    public const float LARGEST_SIZE = 32767.0;

    /** @var array<string, hb_font_t> */
    private array $fonts = [];

    private ?hb_buffer_t $buffer = null;

    public function shape(string $text, FontSource $source, float $size): ShapedRun
    {
        if (! ($size > 0.0 && $size <= self::LARGEST_SIZE)) {
            throw new TypographyException(sprintf('HarfBuzz shapes at sizes above 0 up to %s; got %s.', self::LARGEST_SIZE, $size));
        }
        $font = $this->font($source);
        $scale = (int) round($size * 65536);
        hb_font_set_scale($font, $scale, $scale);

        $buffer = $this->buffer ??= hb_buffer_create();
        hb_buffer_reset($buffer);
        hb_buffer_add_utf8($buffer, $text, -1, 0, -1);
        hb_buffer_set_direction($buffer, HB_DIRECTION_LTR);
        hb_buffer_guess_segment_properties($buffer);
        hb_shape($font, $buffer, null, 0);

        $infos = hb_buffer_get_glyph_infos($buffer, $count);
        $positions = hb_buffer_get_glyph_positions($buffer, $count);

        return new ShapedRun(
            $source,
            $size,
            array_map(fn (hb_glyph_info_t $info): int => $info->codepoint, $infos),
            array_map(fn (hb_glyph_info_t $info): int => $info->cluster, $infos),
            array_map(fn (hb_glyph_position_t $at): float => $at->x_advance / 65536, $positions),
            array_map(fn (hb_glyph_position_t $at): float => $at->x_offset / 65536, $positions),
            array_map(fn (hb_glyph_position_t $at): float => $at->y_offset / 65536, $positions),
        );
    }

    /** Destroys the fonts and the buffer it made. */
    public function __destruct()
    {
        foreach ($this->fonts as $font) {
            hb_font_destroy($font);
        }
        if ($this->buffer !== null) {
            hb_buffer_destroy($this->buffer);
        }
    }

    /**
     * The source's font, made once: the font holds its face and the face its blob. HarfBuzz reads a
     * file it cannot open, or one that is not a font, as an empty face, and takes any face index of a
     * single font as its one face; hb_face_count() tells those apart.
     *
     * @throws TypographyException When the file is not a font HarfBuzz reads, or has no such face.
     */
    private function font(FontSource $source): hb_font_t
    {
        $key = $source->key();
        if (isset($this->fonts[$key])) {
            return $this->fonts[$key];
        }
        $blob = hb_blob_create_from_file($source->path);
        $faces = hb_face_count($blob);
        if ($faces === 0 || $source->index >= $faces) {
            hb_blob_destroy($blob);
            throw new TypographyException($faces === 0
                ? "{$source->path} is not a font HarfBuzz can read."
                : sprintf('%s has %d %s; there is no face %d.', $source->path, $faces, $faces === 1 ? 'face' : 'faces', $source->index));
        }
        $face = hb_face_create($blob, $source->index);
        hb_blob_destroy($blob);
        $font = hb_font_create($face);
        hb_face_destroy($face);
        if ($source->variations !== []) {
            // the instance of a variable font: HarfBuzz clamps each value to its axis and ignores an axis the font lacks
            $variations = [];
            foreach ($source->variations as $tag => $value) {
                $variation = new hb_variation_t();
                $variation->tag = unpack('N', $tag)[1];
                $variation->value = $value;
                $variations[] = $variation;
            }
            hb_font_set_variations($font, $variations, count($variations));
        }

        return $this->fonts[$key] = $font;
    }
}
