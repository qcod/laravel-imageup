<?php

namespace QCod\ImageUp\Tests\Hooks;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;

class CopyImageHook
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