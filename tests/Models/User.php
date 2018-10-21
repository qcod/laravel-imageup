<?php

namespace QCod\ImageUp\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use QCod\ImageUp\HasImageUploads;

class User extends Model
{
    use HasImageUploads;

    protected $guarded = [];

    protected $connection = 'testbench';
    public $table = 'users';
}
