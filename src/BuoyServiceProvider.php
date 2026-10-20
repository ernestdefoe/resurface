<?php

namespace Ernestdefoe\Buoy;

use Ernestdefoe\Buoy\Listener\KeepBump;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\AbstractServiceProvider;

class BuoyServiceProvider extends AbstractServiceProvider
{
    public function boot(): void
    {
        Discussion::saving(fn (Discussion $discussion) => (new KeepBump())($discussion));
    }
}
