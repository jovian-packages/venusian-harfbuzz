<?php

namespace Jovian\Typography\HarfBuzz\Providers;

use Jovian\Typography\HarfBuzz\HarfBuzzFontShaperDriver;
use Voyager\NutsAndBolts\ServiceProvider;

class VenusianHarfBuzzServiceProvider extends ServiceProvider
{
    public function register(): void
    {

    }

    public function boot(): void
    {
        $this->registerTypographyDrivers();
    }

    /** 'harfbuzz' on Typography's shapers: picked by typography.shaper.default (TOPSIDE_FONT_SHAPER=harfbuzz). */
    protected function registerTypographyDrivers(): void
    {
        app('typography.shapers')->extend('harfbuzz', fn () => new HarfBuzzFontShaperDriver());
    }
}
