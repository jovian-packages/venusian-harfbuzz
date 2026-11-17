---
type: Module
title: Shaper
description: HarfBuzzFontShaperDriver and HarfBuzzFontShaper, Typography's harfbuzz text shaper.
resource: src/HarfBuzzFontShaper.php
tags: [harfbuzz, typography, shaping]
status: draft
generated: { by: claude-fable/5.2, at: 2026-11-17T00:00:00-05:00 }
sources:
  - id: shaper
    resource: src/HarfBuzzFontShaper.php
    title: HarfBuzzFontShaper
  - id: driver
    resource: src/HarfBuzzFontShaperDriver.php
    title: HarfBuzzFontShaperDriver
  - id: provider
    resource: src/Providers/VenusianHarfBuzzServiceProvider.php
    title: VenusianHarfBuzzServiceProvider
---

# Driver

Registered as `harfbuzz` on `app('typography.shapers')`.[^provider] The driver answers one `HarfBuzzFontShaper`.[^driver]

# Fonts

One `hb_font_t` a `FontSource::key()` (path, index, coordinates), kept until the shaper is destroyed. Made by `hb_blob_create_from_file` → `hb_face_count` → `hb_face_create(blob, index)` → `hb_font_create`; the blob and face are destroyed straight after, since the font holds its face and the face its blob. `hb_face_count` 0 → "<path> is not a font HarfBuzz can read."; index ≥ count → "<path> has <n> faces; there is no face <i>." Both are `TypographyException`s. Variations: one `hb_variation_t` an entry (tag packed big-endian, value as given) to `hb_font_set_variations`.[^shaper]

# Shaping

`shape($text, $source, $size)`: sizes above 0 up to 32767, else refused. `hb_font_set_scale(size × 65536)` on both axes, so positions come back in 1/65536ths of a drawing unit and the shaper never needs the em. One buffer, reused: reset, `hb_buffer_add_utf8`, direction left to right, `hb_buffer_guess_segment_properties` for script and language, `hb_shape` with no features. The `ShapedRun` holds glyph numbers, clusters (the byte each glyph came from), advances and x/y offsets, each position ÷ 65536.[^shaper]

# Measured

HarfBuzz 15.1.0 (Homebrew), 10.2 (Debian 13), 8.3.0 (Ubuntu 24.04):

* A missing file, a file that is not a font: `hb_blob_create_from_file` answers the empty blob, `hb_face_count` 0, and shaping gives glyph 0 for every character.
* A single font at any face index is its one face. Only a collection has a face past its last, and that face has no glyphs.
* A variation value outside its axis is clamped; an axis tag the font lacks is ignored.
* SF-Pro.ttf at `wght` 650, size 14, "AV": glyphs 19 and 301, advances 8.35 and 9.39 (the pair is kerned through GPOS; unkerned 'A' is 9.56).

[^shaper]: HarfBuzzFontShaper
[^driver]: HarfBuzzFontShaperDriver
[^provider]: VenusianHarfBuzzServiceProvider
