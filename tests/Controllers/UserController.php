<?php

namespace QCod\ImageUp\Tests\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use QCod\ImageUp\Tests\Models\User;

class UserController extends Controller
{
    public function store(Request $request)
    {
        $user = new User();
        $fieldOption = [
            'avatar' => ['width' => 200],
            'cover' => ['width' => 400, 'height' => 400]
        ];
        $user->setImagesField($fieldOption);

        $user->forceFill($request->all())->save();

        return $user;
    }
}
