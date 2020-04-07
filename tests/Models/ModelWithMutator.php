<?php

namespace QCod\ImageUp\Tests\Models;

use Illuminate\Foundation\Auth\User;
use QCod\ImageUp\HasImageUploads;

class ModelWithMutator extends User
{
    use HasImageUploads;

    protected $guarded = [];

    protected $connection = 'testbench';
    public $table = 'users';

    public static $imageFields = [
        'avatar' => ['width' => 200]
    ];

    public function getAvatarAttribute($value)
    {
        return $this->imageUrl('avatar');
    }
}
