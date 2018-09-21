## Laravel ImageUp

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qcod/laravel-imageup.svg)](https://packagist.org/packages/qcod/laravel-imageup)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/qcod/laravel-imageup/master.svg)](https://travis-ci.org/qcod/laravel-imageup)
[![Total Downloads](https://img.shields.io/packagist/dt/qcod/laravel-imageup.svg)](https://packagist.org/packages/qcod/laravel-imageup)

The `qcod/laravel-imageup` is a trait which gives you auto upload, resize and crop for image feature with tons of customization.

### Installation

You can install the package via composer:

```bash
$ composer require qcod/imageup
```

The package will automatically register itself. In case you need to manually register it you can by adding it in `config/app.php` providers array:

```php
QCod\ImageUp\ImageUpServiceProvider::class
```

You can optionally publish the config file with:

```bash
php artisan vendor:publish --provider="QCod\ImageUp\ImageUpServiceProvider" --tag="config"
```

It will create `config/imageup.php` with all the settings.

### Getting Started

To use this trait you just need to add `use HasImageUploads` on the Eloquent model and define all the fields which needs to store the images in database.

**Model**
```php
<?php
namespace App;

use QCod\ImageUp\HasImageUploads;
use Illuminate\Database\Eloquent\Model;

class User extends Model {
    use HasImageUploads;
    
    // assuming `users` table has 'cover', 'avatar' columns
    // mark all the columns as image fields 
    protected static $imageFields = [
        'cover', 'avatar'
    ];
}
```

Once you marked all the image fields in model it will auto upload the image whenever you save the model by hooking in `Model::saved()` event. 
It will also update the database with new stored file path and if finds old image it will be deleted once new image is uploaded.

> Image field should be as as `VARCHAR` in database table to store the path of uploaded image.

**In Controller**
```php
<?php
namespace App;
use App\Http\Controllers\Controller;

class UserController extends Controller {
    public function store(Request $request){
        return User::create($request->all());
    }
}

```
## Image Field options

ImageUp gives you tons of customization on how the upload and resize will be handled from defined field options, following are the things you can customize:

```php
<?php
namespace App;

use QCod\ImageUp\HasImageUploads;
use Illuminate\Database\Eloquent\Model;

class User extends Model {
    
    use HasImageUploads;
    
    // which disk to use for upload, can be override by field options 
    protected $imagesUploadDisk = 'local';
    
    // path in disk to use for upload, can be override by field options 
    protected $imagesUploadPath = 'uploads';
    
    // auto upload allowed 
    protected $autoUploadImages = true;
    
    // all the images fields for model
    protected static $imageFields = [
        'avatar' => [
            // width to resize image after upload
            'width' => 200,
            
            // height to resize image after upload
            'height' => 100,
            
            // set true to crop image with the given width/height and you can also pass arr [x,y] coordinate for crop.
            'crop' => true,
            
            // what disk you want to upload, default config('imageup.upload_disk')
            'disk' => 'public',
            
            // a folder path on the above disk, default config('imageup.upload_directory')
            'path' => 'avatars',
            
            // placeholder image if image field is empty
            'placeholder' => '/images/avatar-placeholder.svg',
            
            // validation rules when uploading image
            'rules' => 'image|max:2000',
            
            // override global auto upload setting coming from config('imageup.auto_upload_images')
            'auto_upload' => false,
            
            // if request file is don't have same name, default will be the field name
            'file_input' => 'photo'
        ],
        'cover' => [
            //...    
        ]
    ];
}
```

### Available methods

You are not limited to use auto upload image feature only. This trait will give you following methods which you can use to manually upload and resize image.

#### $model->uploadImage($imageFile, $field = null)

Upload image for given $field, if $field is null it will upload to first image option defined in array.

```php
$user = User::findOrFail($id);
$user->uploadImage(request()->file('cover'), 'cover');
```

#### $model->setImagesField($fieldsOptions)

You can also set the image fields dynamically by calling `$model->setImagesField($fieldsOptions)` with field options, it will replace fields defined on model property.

```php
$user = User::findOrFail($id);

$fieldOptions = [
    'cover' => [ 'width' => 1000 ],
    'avatar' => [ 'width' => 120, 'crop' => true ]    
];

// override image fields defined on  model 
$user->setImagesField($fieldOptions);
```

#### $model->hasImageField($field)

To check if field is defined as image field.

#### $model->deleteImage($filePath)

Delete any image if it exists.

#### $model->resizeImage($imageFile, $fieldOptions)

If you have image already you can call this method to resize it with the same options we have used for image fields.

```php
$user = User::findOrFail($id);

// resize image, it will give you resized image, you need to save it  
$imageFile = '/images/some-big-image.jpg';
$image = $user->resizeImage($imageFile, [ 'width' => 120, 'crop' => true ]);

// or you can use uploaded file
$imageFile = request()->file('avatar');
$image = $user->resizeImage($imageFile, [ 'width' => 120, 'crop' => true ]);
```

#### $model->cropTo($x, $y)->resizeImage($imageFile, $field = null)

You can use this `cropTo()` method to set the x and y coordinates of cropping. It will be very useful if you are getting coordinate from some sort of font-end image cropping library.

```php
$user = User::findOrFail($id);

// uploaded file from request
$imageFile = request()->file('avatar');

// coordinates from request
$coords = request()->only(['crop_x', 'crop_y']);

// resizing will give you intervention image back
$image = $user->cropTo($coords)
    ->resizeImage($imageFile, [ 'width' => 120, 'crop' => true ]);

// or you can do upload and resize like this, it will override field options crop setting
$user->cropTo($coords)
    ->uploadImage(request()->file('cover'), 'avatar');
```

#### $model->imageUrl($field)

Gives uploaded image url for given image field.

```php
$user = User::findOrFail($id);

// in your view 
<img src="{{ $user->imageUrl('cover') }}" alt="" />
// http://www.example.com/storage/uploads/iGqUEbCPTv7EuqkndE34CNitlJbFhuxEWmgN9JIh.jpeg
```

#### $model->imageTag($field, $attribute = '')

It gives you `<img />` tag for a field.

```html
{!! $model->imageTag('avatar') !!}
<!-- <img src="http://www.example.com/storage/uploads/iGqUEbCPTv7EuqkndE34CNitlJbFhuxEWmgN9JIh.jpeg" /> -->

{!! $model->imageTag('avatar', 'class="float-left mr-3"') !!}
<!-- <img src="http://www.example.com/storage/uploads/iGqUEbCPTv7EuqkndE34CNitlJbFhuxEWmgN9JIh.jpeg" class="float-left mr-3 /> -->
```

Config file

```php
<?php
// config file 
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

### Testing
The package contains some integration/smoke tests, set up with Orchestra. The tests can be run via phpunit.

```bash
$ composer test
```

### Contributing
Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email saquibwebk@gmail.com instead of using the issue tracker.

### Credits
- [Mohd Saqueib Ansari](https://github.com/saqueib)

### About QCode.in
QCode.in (https://www.qcode.in) is blog by [Saqueib](https://github.com/saqueib) which covers All about Full Stack Web Development.

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
