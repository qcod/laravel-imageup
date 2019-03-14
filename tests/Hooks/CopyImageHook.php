<?php

namespace QCod\ImageUp\Tests\Hooks;

use Illuminate\Support\Facades\Storage;
use QCod\ImageUp\Contracts\Handler;

class CopyImageHook implements Handler
{
    public function handle($image)
    {
        Storage::disk('public')->put(
            'uploads/copy_from_hook.jpg',
            (string)$image->encode(null, 80),
            'public'
        );
    }
}
