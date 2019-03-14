<?php

namespace QCod\ImageUp\Tests\Hooks;

use QCod\ImageUp\Contracts\Handler;

class ResizeToFiftyHook implements Handler
{
    public function handle($image)
    {
        $image->resize(50, 50);
    }
}
