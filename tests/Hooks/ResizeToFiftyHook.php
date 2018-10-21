<?php

namespace QCod\ImageUp\Tests\Hooks;

class ResizeToFiftyHook
{
    public function handle($image)
    {
        $image->resize(50, 50);
    }
}
