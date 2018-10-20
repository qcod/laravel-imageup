<?php

namespace QCod\ImageUp\Tests;

use Illuminate\Support\Facades\Route;
use Orchestra\Database\ConsoleServiceProvider;
use QCod\ImageUp\Tests\Models\User;
use QCod\ImageUp\ImageUpServiceProvider;
use Intervention\Image\ImageServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);


        // define some route to test auto upload
        Route::group(['namespace' => 'QCod\ImageUp\Tests\Controllers'], function () {
            Route::post('test/users', 'UserController@store');
            Route::put('test/users/{id}', 'UserController@update');
        });
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            ImageServiceProvider::class,
            ImageUpServiceProvider::class,
            ConsoleServiceProvider::class
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'Image' => \Intervention\Image\Facades\Image::class
        ];
    }

    /**
     * Create a user with image fields options
     *
     * @param array $attributes
     * @param null $imageFields
     * @return User
     */
    protected function createUser($attributes = [], $imageFields = null)
    {
        $user = new User();

        if (is_null($imageFields)) {
            $fieldOption = [
                'avatar' => ['width' => 200],
                'cover' => ['width' => 400, 'height' => 400]
            ];
            $user->setImagesField($fieldOption);
        } else {
            $user->setImagesField($imageFields);
        }

        $user->forceFill(array_merge($attributes, [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret'
        ]))->save();

        return $user;
    }
}
