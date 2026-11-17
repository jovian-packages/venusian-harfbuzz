<?php

namespace Jovian\Typography\HarfBuzz;

use Surface\Contracts\Typography\FontShaper;
use Surface\Typography\Shapers\FontShaperDriver;

/** 'harfbuzz': text shaped by HarfBuzz, through ext-harfbuzz. */
class HarfBuzzFontShaperDriver extends FontShaperDriver
{
    private ?HarfBuzzFontShaper $shaper = null;

    public function driver(): string
    {
        return 'harfbuzz';
    }

    public function shaper(): FontShaper
    {
        return $this->shaper ??= new HarfBuzzFontShaper();
    }
}
