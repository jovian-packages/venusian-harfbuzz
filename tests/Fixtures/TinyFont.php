<?php

declare(strict_types=1);

namespace Jovian\Typography\HarfBuzz\Tests\Fixtures;

/**
 * Writes a small TrueType font, table by table, so a test can state what a face holds and check
 * that the reader finds exactly that. Glyph 0 is the missing glyph, empty. Checksums are left at 0:
 * nothing reads them.
 */
final class TinyFont
{
    public int $unitsPerEm = 1000;

    /** @var array{int, int, int} 'hhea' ascender, descender, line gap */
    public array $hhea = [800, -200, 90];

    /** @var array{int, int, int} OS/2 typographic ascender, descender, line gap */
    public array $typo = [700, -300, 50];

    /** @var array{int, int} OS/2 Windows ascent, descent */
    public array $windows = [900, 250];

    public bool $useTypoMetrics = false;

    public string $family = 'Tiny';

    public ?string $typographicFamily = null;

    public int $weight = 400;

    public int $width = 5;

    public bool $italic = false;

    public bool $oblique = false;

    /** 'glyf' outlines, or a stand-in 'CFF ' table and no 'glyf'. */
    public bool $cff = false;

    /** Type 2 charstrings, one a glyph: written as a real CFF table in place of 'glyf'. */
    public ?array $charStrings = null;

    /** @var list<array{string, float, float, float}> variation axes ('fvar'): tag, minimum, default, maximum */
    public array $axes = [];

    /**
     * 'HVAR': regions and the advance each adds at its peak. A region is one normalised peak an axis
     * (-1..1; 0 leaves the axis out); it counts from 0 at the default up to 1 at the peak.
     *
     * @var list<array{list<float>, array<int, int>}> peak an axis, glyph => advance delta
     */
    public array $advanceDeltas = [];

    /**
     * 'gvar': regions and the move each gives a glyph's points at its peak: the glyph's own points in
     * order, then its four phantom points (origin, advance, top, bottom).
     *
     * @var list<array{list<float>, array<int, list<array{int, int}>>}> peak an axis, glyph => [dx, dy] a point
     */
    public array $pointDeltas = [];

    /** Long metrics in 'hmtx'; glyphs past them repeat the last advance. Null: every glyph. */
    public ?int $longMetrics = null;

    /** @var list<array{int, int|null, string}> advance, left side bearing (null: the glyph's left edge), 'glyf' bytes */
    private array $glyphs = [[500, null, '']];

    /** @var array<int, int> codepoint => glyph, written in format 4 (first plane) */
    private array $format4 = [];

    /** @var array<int, int> codepoint => glyph, written in format 12 */
    private array $format12 = [];

    /** @var list<array{int, list<array{int, int, int}>}> coverage, pairs [left, right, value] */
    private array $kern = [];

    /**
     * A simple glyph. Points are [x, y, on curve], in font units.
     *
     * @param  list<list<array{int, int, bool}>>  $contours
     */
    public function simple(int $advance, array $contours, ?int $bearing = null): int
    {
        $this->glyphs[] = [$advance, $bearing, self::simpleGlyph($contours)];

        return count($this->glyphs) - 1;
    }

    /**
     * A composite glyph. Each component: ['glyph' => n] and either 'x', 'y' (an offset) or 'match'
     * => [point placed so far, point of the component]; optionally 'scale' (one for both axes),
     * 'bytes' (one-byte arguments), 'metrics' (USE_MY_METRICS).
     *
     * @param  list<array<string, mixed>>  $components
     * @param  array{int, int, int, int}  $box  xMin, yMin, xMax, yMax as the header gives them
     */
    public function composite(int $advance, array $components, array $box, ?int $bearing = null): int
    {
        $data = pack('n5', 0xFFFF, ...array_map(fn (int $v) => $v & 0xFFFF, $box));
        foreach ($components as $i => $component) {
            $flags = 0;
            $xy = ! isset($component['match']);
            [$one, $two] = $xy ? [$component['x'] ?? 0, $component['y'] ?? 0] : $component['match'];
            $flags |= $xy ? 0x0002 : 0;
            $flags |= ($component['bytes'] ?? false) ? 0 : 0x0001;
            $flags |= isset($component['scale']) ? 0x0008 : 0;
            $flags |= ($component['metrics'] ?? false) ? 0x0200 : 0;
            $flags |= $i < count($components) - 1 ? 0x0020 : 0;
            $data .= pack('n2', $flags, $component['glyph']);
            $data .= ($flags & 0x0001) !== 0 ? pack('n2', $one & 0xFFFF, $two & 0xFFFF) : pack('C2', $one & 0xFF, $two & 0xFF);
            if (isset($component['scale'])) {
                $data .= pack('n', ((int) round($component['scale'] * 16384)) & 0xFFFF);
            }
        }
        $this->glyphs[] = [$advance, $bearing, $data];

        return count($this->glyphs) - 1;
    }

    public function map(int $codepoint, int $glyph, int $format = 4): self
    {
        if ($format === 4) {
            $this->format4[$codepoint] = $glyph;
        } else {
            $this->format12[$codepoint] = $glyph;
        }

        return $this;
    }

    /**
     * A format 0 'kern' subtable, horizontal; $override sets the bit that makes its values replace
     * those before it.
     *
     * @param  list<array{int, int, int}>  $pairs  [left, right, value]
     */
    public function kern(array $pairs, bool $override = false): self
    {
        $this->kern[] = [0x0001 | ($override ? 0x0008 : 0), $pairs];

        return $this;
    }

    public function write(string $path): string
    {
        file_put_contents($path, $this->bytes());

        return $path;
    }

    /** A collection of faces, in order. */
    public static function collection(string $path, self ...$faces): string
    {
        $fonts = array_map(fn (self $face) => $face->bytes(), $faces);
        $header = 12 + 4 * count($fonts);
        $offsets = [];
        $at = $header;
        foreach ($fonts as $font) {
            $offsets[] = $at;
            $at += strlen($font);
        }
        // each face's table offsets are from the start of the file: rewrite them
        $body = '';
        foreach ($fonts as $i => $font) {
            $tables = unpack('n', $font, 4)[1];
            for ($t = 0; $t < $tables; $t++) {
                $record = 12 + 16 * $t + 8;
                $font = substr_replace($font, pack('N', unpack('N', $font, $record)[1] + $offsets[$i]), $record, 4);
            }
            $body .= $font;
        }
        file_put_contents($path, 'ttcf'.pack('n2N', 1, 0, count($fonts)).pack('N*', ...$offsets).$body);

        return $path;
    }

    public function bytes(): string
    {
        $glyf = '';
        $loca = [];
        foreach ($this->glyphs as [, , $data]) {
            $loca[] = strlen($glyf);
            $glyf .= $data.str_repeat("\0", strlen($data) % 2);
        }
        $loca[] = strlen($glyf);

        $long = $this->longMetrics ?? count($this->glyphs);
        $hmtx = '';
        foreach ($this->glyphs as $g => [$advance, $bearing, $data]) {
            $bearing ??= $data === '' ? 0 : unpack('n', $data, 2)[1];
            $hmtx .= $g < $long ? pack('n2', $advance, $bearing & 0xFFFF) : pack('n', $bearing & 0xFFFF);
        }

        $tables = [
            'head' => pack('NNNNnn', 0x00010000, 0, 0, 0x5F0F3CF5, 0, $this->unitsPerEm).str_repeat("\0", 16)
                .pack('n4', 0, 0, 0, 0).pack('nnn', ($this->italic ? 0x02 : 0) | ($this->weight >= 700 ? 0x01 : 0), 8, 2).pack('nn', 1, 0),
            'hhea' => pack('N', 0x00010000).pack('n3', ...array_map(fn (int $v) => $v & 0xFFFF, $this->hhea)).str_repeat("\0", 24).pack('n', $long),
            'maxp' => pack('Nn', 0x00005000, count($this->glyphs)),
            'OS/2' => $this->os2(),
            'hmtx' => $hmtx,
            'cmap' => $this->cmap(),
            'name' => $this->name(),
        ];
        if ($this->charStrings !== null) {
            $tables['CFF '] = self::cffTable($this->charStrings);
        } elseif ($this->cff) {
            $tables['CFF '] = "\x01\x00\x04\x01";
        } else {
            $tables['loca'] = pack('N*', ...$loca);
            $tables['glyf'] = $glyf;
        }
        if ($this->kern !== []) {
            $tables['kern'] = $this->kernTable();
        }
        if ($this->axes !== []) {
            $tables['fvar'] = $this->fvarTable();
        }
        if ($this->advanceDeltas !== []) {
            $tables['HVAR'] = $this->hvarTable();
        }
        if ($this->pointDeltas !== []) {
            $tables['gvar'] = $this->gvarTable();
        }

        $count = count($tables);
        $directory = '';
        $data = '';
        $at = 12 + 16 * $count;
        foreach ($tables as $tag => $table) {
            $directory .= $tag.pack('N3', 0, $at + strlen($data), strlen($table));
            $data .= $table.str_repeat("\0", (4 - strlen($table) % 4) % 4);
        }

        $version = $this->charStrings !== null ? 0x4F54544F : 0x00010000;   // 'OTTO' for CFF outlines

        return pack('Nn4', $version, $count, 0, 0, 0).$directory.$data;
    }

    private static function f2dot14(float $value): string
    {
        return pack('n', ((int) round($value * 16384)) & 0xFFFF);
    }

    /** 'fvar' 1.0: the axes, no named instances. */
    private function fvarTable(): string
    {
        $fixed = static fn (float $value): string => pack('N', ((int) round($value * 65536)) & 0xFFFFFFFF);
        $count = count($this->axes);
        $table = pack('n7', 1, 0, 16, 2, $count, 20, 0).pack('n', 4 * $count + 4);
        foreach ($this->axes as $i => [$tag, $minimum, $default, $maximum]) {
            $table .= $tag.$fixed($minimum).$fixed($default).$fixed($maximum).pack('n2', 0, 256 + $i);
        }

        return $table;
    }

    /** 'HVAR' 1.0: one item variation data holding a 16-bit advance delta a glyph a region; glyphs map to items by number. */
    private function hvarTable(): string
    {
        $axes = count($this->axes);
        $regions = count($this->advanceDeltas);
        $list = pack('n2', $axes, $regions);
        foreach ($this->advanceDeltas as [$peaks]) {
            foreach ($peaks as $peak) {
                $list .= self::f2dot14(min(0.0, $peak)).self::f2dot14($peak).self::f2dot14(max(0.0, $peak));
            }
        }
        $data = pack('n3', count($this->glyphs), $regions, $regions).pack('n*', ...range(0, $regions - 1));
        foreach (array_keys($this->glyphs) as $glyph) {
            foreach ($this->advanceDeltas as [, $deltas]) {
                $data .= pack('n', ($deltas[$glyph] ?? 0) & 0xFFFF);
            }
        }
        $store = pack('nNnN', 1, 12, 1, 12 + strlen($list)).$list.$data;

        return pack('n2N4', 1, 0, 20, 0, 0, 0).$store;
    }

    /** 'gvar' 1.0, long offsets: a tuple a region a glyph, its peak embedded, moving every point, deltas as words. */
    private function gvarTable(): string
    {
        $axes = count($this->axes);
        $packed = static function (array $values): string {
            $bytes = '';
            foreach (array_chunk($values, 64) as $chunk) {
                $bytes .= chr(0x40 | (count($chunk) - 1)).pack('n*', ...array_map(fn (int $v) => $v & 0xFFFF, $chunk));
            }

            return $bytes;
        };
        $offsets = [0];
        $all = '';
        foreach (array_keys($this->glyphs) as $glyph) {
            $headers = '';
            $serialized = '';
            $tuples = 0;
            foreach ($this->pointDeltas as [$peaks, $moves]) {
                if (! isset($moves[$glyph])) {
                    continue;
                }
                $deltas = "\0".$packed(array_column($moves[$glyph], 0)).$packed(array_column($moves[$glyph], 1));   // 0: every point
                $headers .= pack('n2', strlen($deltas), 0x8000 | 0x2000).implode('', array_map(self::f2dot14(...), $peaks));
                $serialized .= $deltas;
                $tuples++;
            }
            if ($tuples > 0) {
                $all .= pack('n2', $tuples, 4 + strlen($headers)).$headers.$serialized;
            }
            $offsets[] = strlen($all);
        }
        $header = 20 + 4 * count($offsets);

        return pack('n4Nn2N', 1, 0, $axes, 0, $header, count($this->glyphs), 1, $header).pack('N*', ...$offsets).$all;
    }

    /**
     * A CFF table holding one Type 2 charstring a glyph: header, Name, Top DICT, String and Global
     * Subrs INDEXes, the CharStrings INDEX and a Private DICT (nominalWidthX 0). The charset is the
     * default one.
     *
     * @param  list<string>  $charStrings
     */
    private static function cffTable(array $charStrings): string
    {
        $index = static function (array $items): string {
            if ($items === []) {
                return "\0\0";
            }
            $offsets = [1];
            foreach ($items as $item) {
                $offsets[] = end($offsets) + strlen($item);
            }

            return pack('nC', count($items), 4).pack('N*', ...$offsets).implode('', $items);
        };
        $int = static fn (int $value): string => "\x1D".pack('N', $value & 0xFFFFFFFF);
        $private = "\x8B\x15";   // nominalWidthX 0
        $head = "\x01\x00\x04\x04".$index(['Tiny']);
        $tail = $index([]).$index([]);
        $topSize = strlen($index([str_repeat("\0", 17)]));   // CharStrings and Private, 5-byte operands
        $charStringsAt = strlen($head) + $topSize + strlen($tail);
        $charStringsIndex = $index($charStrings);
        $top = $int($charStringsAt)."\x11".$int(strlen($private)).$int($charStringsAt + strlen($charStringsIndex))."\x12";

        return $head.$index([$top]).$tail.$charStringsIndex.$private;
    }

    /** @param  list<list<array{int, int, bool}>>  $contours */
    private static function simpleGlyph(array $contours): string
    {
        if ($contours === []) {
            return '';   // a glyph that draws nothing has no data
        }
        $points = array_merge(...$contours);
        $xs = array_column($points, 0);
        $ys = array_column($points, 1);
        $ends = [];
        $end = -1;
        foreach ($contours as $contour) {
            $ends[] = $end += count($contour);
        }
        $flags = [];
        $xBytes = '';
        $yBytes = '';
        [$px, $py] = [0, 0];
        foreach ($points as [$x, $y, $on]) {
            [$xFlag, $xMore] = self::coordinate($x - $px, 0x02, 0x10);
            [$yFlag, $yMore] = self::coordinate($y - $py, 0x04, 0x20);
            $flags[] = ($on ? 0x01 : 0) | $xFlag | $yFlag;
            $xBytes .= $xMore;
            $yBytes .= $yMore;
            [$px, $py] = [$x, $y];
        }
        // runs of the same flag written once, with REPEAT_FLAG and a count
        $packed = '';
        for ($i = 0, $n = count($flags); $i < $n;) {
            $run = 1;
            while ($i + $run < $n && $flags[$i + $run] === $flags[$i] && $run < 256) {
                $run++;
            }
            $packed .= $run > 1 ? chr($flags[$i] | 0x08).chr($run - 1) : chr($flags[$i]);
            $i += $run;
        }

        return pack('n5', count($contours), min($xs) & 0xFFFF, min($ys) & 0xFFFF, max($xs) & 0xFFFF, max($ys) & 0xFFFF)
            .pack('n*', ...$ends).pack('n', 0).$packed.$xBytes.$yBytes;
    }

    /**
     * One coordinate's delta: none when 0 (the same flag), a byte and a sign in the flags when short,
     * else a signed word.
     *
     * @return array{int, string}
     */
    private static function coordinate(int $delta, int $short, int $same): array
    {
        return match (true) {
            $delta === 0 => [$same, ''],
            abs($delta) < 256 => [$short | ($delta > 0 ? $same : 0), chr(abs($delta))],
            default => [0, pack('n', $delta & 0xFFFF)],
        };
    }

    private function os2(): string
    {
        $selection = ($this->italic ? 0x0001 : 0) | ($this->useTypoMetrics ? 0x0080 : 0) | ($this->oblique ? 0x0200 : 0);

        return pack('n', 4).pack('n', 500).pack('n2', $this->weight, $this->width).str_repeat("\0", 54)
            .pack('n', $selection).pack('n2', 0x20, 0xFFFF)
            .pack('n3', ...array_map(fn (int $v) => $v & 0xFFFF, $this->typo)).pack('n2', ...$this->windows)
            .str_repeat("\0", 18);
    }

    private function cmap(): string
    {
        $subtables = [];
        if ($this->format12 !== []) {
            $subtables[] = [3, 10, $this->format12Table()];
        }
        if ($this->format4 !== []) {
            $subtables[] = [3, 1, $this->format4Table()];
        }
        $header = pack('n2', 0, count($subtables));
        $at = 4 + 8 * count($subtables);
        $body = '';
        foreach ($subtables as [$platform, $encoding, $table]) {
            $header .= pack('n2N', $platform, $encoding, $at + strlen($body));
            $body .= $table;
        }

        return $header.$body;
    }

    /** Format 12: one group a run of consecutive codepoints with consecutive glyphs. */
    private function format12Table(): string
    {
        ksort($this->format12);
        $groups = [];
        foreach ($this->format12 as $codepoint => $glyph) {
            $last = array_key_last($groups);
            if (! is_null($last) && $groups[$last][1] === $codepoint - 1 && $groups[$last][2] + ($codepoint - $groups[$last][0]) === $glyph) {
                $groups[$last][1] = $codepoint;
            } else {
                $groups[] = [$codepoint, $codepoint, $glyph];
            }
        }
        $body = '';
        foreach ($groups as $group) {
            $body .= pack('N3', ...$group);
        }

        return pack('nnNNN', 12, 0, 16 + strlen($body), 0, count($groups)).$body;
    }

    /**
     * Format 4: one segment a run of consecutive codepoints, mapped by a delta when its glyphs are
     * consecutive and through the glyph array when they are not, then the closing 0xFFFF segment.
     */
    private function format4Table(): string
    {
        ksort($this->format4);
        $runs = [];
        foreach ($this->format4 as $codepoint => $glyph) {
            $last = array_key_last($runs);
            if (! is_null($last) && array_key_last($runs[$last]) === $codepoint - 1) {
                $runs[$last][$codepoint] = $glyph;
            } else {
                $runs[] = [$codepoint => $glyph];
            }
        }
        $runs[] = [0xFFFF => 0];
        $segments = count($runs);
        [$ends, $starts, $deltas, $ranges, $array] = ['', '', '', [], []];
        foreach ($runs as $s => $run) {
            $first = array_key_first($run);
            $ends .= pack('n', array_key_last($run));
            $starts .= pack('n', $first);
            $consecutive = array_values($run) === range($run[$first], $run[$first] + count($run) - 1);
            if ($consecutive || $first === 0xFFFF) {
                $deltas .= pack('n', ($run[$first] - $first) & 0xFFFF);
                $ranges[$s] = null;
            } else {
                $deltas .= pack('n', 0);
                $ranges[$s] = count($array);
                array_push($array, ...array_values($run));
            }
        }
        $rangeBytes = '';
        foreach ($ranges as $s => $index) {
            // bytes from this idRangeOffset entry to the glyph array entry it points at
            $rangeBytes .= pack('n', is_null($index) ? 0 : 2 * ($segments - $s) + 2 * $index);
        }
        $body = $ends.pack('n', 0).$starts.$deltas.$rangeBytes.pack('n*', ...$array);

        return pack('n7', 4, 14 + strlen($body), 0, 2 * $segments, 0, 0, 0).$body;
    }

    private function name(): string
    {
        $records = [[1, $this->family], [2, $this->italic ? 'Italic' : 'Regular']];
        if (! is_null($this->typographicFamily)) {
            $records[] = [16, $this->typographicFamily];
        }
        $strings = '';
        $table = '';
        foreach ($records as [$id, $text]) {
            $utf16 = mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
            $table .= pack('n6', 3, 1, 0x409, $id, strlen($utf16), strlen($strings));
            $strings .= $utf16;
        }

        return pack('n3', 0, count($records), 6 + 12 * count($records)).$table.$strings;
    }

    private function kernTable(): string
    {
        $table = pack('n2', 0, count($this->kern));
        foreach ($this->kern as [$coverage, $pairs]) {
            usort($pairs, fn (array $a, array $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
            $body = pack('n4', count($pairs), 0, 0, 0);
            foreach ($pairs as [$left, $right, $value]) {
                $body .= pack('n3', $left, $right, $value & 0xFFFF);
            }
            $table .= pack('n3', 0, 6 + strlen($body), $coverage).$body;
        }

        return $table;
    }
}
