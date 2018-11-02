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

    public function storeImagesWithoutOptions(Request $request)
    {
        $user = new User();

        $fieldOption = [
            'avatar',
            'cover'
        ];

        $user->setImagesField($fieldOption);
        $user->forceFill($request->all())->save();

        return $user;
    }

    public function storeImagesWithMixedOptions(Request $request)
    {
        $user = new User();

        $fieldOption = [
            'avatar',
            'cover' => [
                'width' => 400,
                'height' => 400,
                'auto_upload' => false
            ],
        ];

        $user->setImagesField($fieldOption);
        $user->forceFill($request->except('cover'))->save();

        return $user;
    }

    public function storeFileWithOption(Request $request)
    {
        $user = new User();

        $fieldOption = [
            'resume' => [
                'path' => 'resumes'
            ],
            'cover_letter'
        ];

        $user->setFilesField($fieldOption);
        $user->forceFill($request->except('cover'))->save();

        return $user;
    }
}
