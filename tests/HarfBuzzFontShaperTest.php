<?php

declare(strict_types=1);

use Jovian\Typography\HarfBuzz\HarfBuzzFontShaper;
use Jovian\Typography\HarfBuzz\HarfBuzzFontShaperDriver;
use Jovian\Typography\HarfBuzz\Tests\Fixtures\TinyFont;
use Surface\Contracts\Typography\FontSource;
use Surface\Contracts\Typography\TypographyException;

/*
 * The HarfBuzz shaper: each test writes a font (1000 units an em) holding known glyphs, advances and
 * kerning, and checks the run HarfBuzz makes of a text at a size.
 */

beforeEach(function (): void {
    $this->directory = fontDirectory();
    $this->shaper = new HarfBuzzFontShaper();
});

it('gives each character its glyph, its advance at the size, and the byte it starts at', function (): void {
    $font = new TinyFont();
    $a = $font->simple(500, []);
    $e = $font->simple(400, []);
    $font->map(0x41, $a)->map(0xE9, $e);
    $run = $this->shaper->shape("\u{e9}AA", new FontSource($font->write("{$this->directory}/face.ttf")), 20);

    expect([$run->glyphs, $run->clusters, $run->advances, $run->offsetsX, $run->offsetsY])
        ->toBe([[$e, $a, $a], [0, 2, 3], [8.0, 10.0, 10.0], [0.0, 0.0, 0.0], [0.0, 0.0, 0.0]])
        ->and($run->width)->toBe(28.0)
        ->and($run->size)->toBe(20.0);
});

it('applies a kerning pair: the second glyph lands closer by the pair\'s value at the size', function (): void {
    $font = new TinyFont();
    $a = $font->simple(500, []);
    $v = $font->simple(600, []);
    $font->map(0x41, $a)->map(0x56, $v)->kern([[$a, $v, -80]]);
    $run = $this->shaper->shape('AV', new FontSource($font->write("{$this->directory}/face.ttf")), 20);

    // HarfBuzz may split a pair's value between advance and offset: where V is drawn and where the pen ends are what count
    expect($run->advances[0] + $run->offsetsX[1])->toEqualWithDelta(10.0 - 1.6, 1e-4)
        ->and($run->width)->toEqualWithDelta(22.0 - 1.6, 1e-4);
});

it('lays the run out left to right, clusters rising, for right-to-left letters too', function (): void {
    $font = new TinyFont();
    $alef = $font->simple(500, []);
    $bet = $font->simple(600, []);
    $font->map(0x05D0, $alef)->map(0x05D1, $bet);
    $run = $this->shaper->shape("\u{05D0}\u{05D1}", new FontSource($font->write("{$this->directory}/face.ttf")), 10);

    expect([$run->glyphs, $run->clusters])->toBe([[$alef, $bet], [0, 2]]);
});

it('gives a default ignorable character no width and keeps its cluster', function (): void {
    $font = new TinyFont();
    $a = $font->simple(500, []);
    $font->map(0x41, $a)->map(0x20, $font->simple(250, []));
    $run = $this->shaper->shape("A\u{200B}A", new FontSource($font->write("{$this->directory}/face.ttf")), 10);

    expect($run->clusters)->toBe([0, 1, 4])
        ->and($run->advances[1])->toBe(0.0)
        ->and($run->width)->toBe(10.0);
});

it('refuses a size HarfBuzz\'s integer scale cannot hold', function (): void {
    $path = (new TinyFont())->write("{$this->directory}/face.ttf");

    expect(fn () => $this->shaper->shape('A', new FontSource($path), 0))->toThrow(TypographyException::class, 'sizes above 0')
        ->and(fn () => $this->shaper->shape('A', new FontSource($path), 32768))->toThrow(TypographyException::class, 'sizes above 0')
        ->and($this->shaper->shape('A', new FontSource($path), 32767)->size)->toBe(32767.0);
});

it('refuses a source that is missing, not a font, or a face the file does not have', function (): void {
    $path = (new TinyFont())->write("{$this->directory}/face.ttf");
    file_put_contents("{$this->directory}/text.ttf", 'not a font at all');

    expect(fn () => $this->shaper->shape('A', new FontSource("{$this->directory}/none.ttf"), 10))->toThrow(TypographyException::class, 'is not a font HarfBuzz can read')
        ->and(fn () => $this->shaper->shape('A', new FontSource("{$this->directory}/text.ttf"), 10))->toThrow(TypographyException::class, 'is not a font HarfBuzz can read')
        ->and(fn () => $this->shaper->shape('A', new FontSource($path, 1), 10))->toThrow(TypographyException::class, 'has 1 face; there is no face 1');
});

it('shapes a face of a collection by its index', function (): void {
    $narrow = new TinyFont();
    $narrow->map(0x41, $narrow->simple(300, []));
    $wide = new TinyFont();
    $wide->map(0x41, $wide->simple(700, []));
    $path = TinyFont::collection("{$this->directory}/pair.ttc", $narrow, $wide);

    expect($this->shaper->shape('A', new FontSource($path, 1), 10)->advances)->toBe([7.0])
        ->and($this->shaper->shape('A', new FontSource($path, 0), 10)->advances)->toBe([3.0]);
});

it('shapes a variable font at the instance its source names, each instance apart, the default with none', function (): void {
    $font = new TinyFont();
    $a = $font->simple(500, []);
    $font->map(0x41, $a);
    $font->axes = [['wght', 100.0, 400.0, 900.0]];
    $font->advanceDeltas = [[[1.0], [$a => 200]], [[-1.0], [$a => -100]]];   // 700 wide at wght 900, 400 at wght 100
    $path = $font->write("{$this->directory}/variable.ttf");
    $advance = fn (array $variations): float => $this->shaper->shape('A', new FontSource($path, 0, $variations), 10)->advances[0];

    expect([$advance(['wght' => 900]), $advance([]), $advance(['wght' => 650]), $advance(['wght' => 100]), $advance(['wght' => 900])])
        ->toBe([7.0, 5.0, 6.0, 4.0, 7.0])
        ->and($advance(['wght' => 5000]))->toBe(7.0)                    // clamped to the axis
        ->and($advance(['wdth' => 50, 'wght' => 650]))->toBe(6.0);      // an axis the font lacks is ignored
});

it('hands out one shaper a driver', function (): void {
    $driver = new HarfBuzzFontShaperDriver();

    expect($driver->shaper())->toBe($driver->shaper())
        ->and($driver->driver())->toBe('harfbuzz');
});
