## Laravel ImageUp

[![Latest Version on Packagist](https://img.shields.io/packagist/v/qcod/laravel-imageup.svg)](https://packagist.org/packages/qcod/laravel-imageup)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)
[![Build Status](https://img.shields.io/travis/qcod/laravel-imageup/master.svg)](https://travis-ci.org/qcod/laravel-imageup)
[![Total Downloads](https://img.shields.io/packagist/dt/qcod/laravel-imageup.svg)](https://packagist.org/packages/qcod/laravel-imageup)

The `qcod/laravel-imageup` is a trait which gives you auto upload, resize and crop for image feature with tons of customization.

### Installation

You can install the package via composer:

```bash
$ composer require qcod/laravel-imageup
```

The package will automatically register itself. In case you need to manually register it you can by adding it in `config/app.php` providers array:

```php
QCod\ImageUp\ImageUpServiceProvider::class
```

You can optionally publish the config file with:

```bash
php artisan vendor:publish --provider="QCod\ImageUp\ImageUpServiceProvider" --tag="config"
```

It will create [`config/imageup.php`](#config-file) with all the settings.

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
> Make sure to run `php artisan storage:link` to see the images from public storage disk

That's it, with above setup when ever you hit store method with post request and if `cover` or `avatar` named file is present on request() it will be auto uploaded.  

## Upload Field options

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
            'file_input' => 'photo',
            
            // if field (here "avatar") don't exist in database or you wan't this field in database
            'update_database' => false,
            
            // a hook that is triggered before the image is saved
            'before_save' => BlurFilter::class,
            
            // a hook that is triggered after the image is saved
            'after_save' => CreateWatermarkImage::class
        ],
        'cover' => [
            //...    
        ]
    ];
    
    // any other than image file type for upload
    protected static $fileFields = [
            'resume' => [
                // what disk you want to upload, default config('imageup.upload_disk')
                'disk' => 'public',
                
                // a folder path on the above disk, default config('imageup.upload_directory')
                'path' => 'docs',
                
                // validation rules when uploading file
                'rules' => 'mimes:doc,pdf,docx|max:1000',
                
                // override global auto upload setting coming from config('imageup.auto_upload_images')
                'auto_upload' => false,
                
                // if request file is don't have same name, default will be the field name
                'file_input' => 'cv',
                
                // a hook that is triggered before the file is saved
                'before_save' => HookForBeforeSave::class,
                
                // a hook that is triggered after the file is saved
                'after_save' => HookForAfterSave::class
            ],
            'cover_letter' => [
                //...    
            ]
        ];
}
```
### Customize filename

In some case you will need to customize the saved filename. By default it will be `$file->hashName()` generated hash.

You can do it by adding a method on the model with `{fieldName}UploadFilePath` naming convention:

```php
class User extends Model {
    use HasImageUploads;
    
    // assuming `users` table has 'cover', 'avatar' columns
    // mark all the columns as image fields 
    protected static $imageFields = [
        'cover', 'avatar'
    ];
    
    // override cover file name
    protected function coverUploadFilePath($file) {
        return $this->id . '-cover-image.jpg';
    }
}
```

Above will always save uploaded cover image as `uploads/1-cover-image.jpg`.

> Make sure to return only relative path from override method.
 
Request file will be passed as `$file` param in this method, so you can get the extension or original file name etc to build the filename.

```php
    // override cover file name
    protected function coverUploadFilePath($file) {
        return $this->id .'-'. $file->getClientOriginalName();
    }
    
    /** Some of methods on file */
    // $file->getClientOriginalExtension()
    // $file->getRealPath()
    // $file->getSize()
    // $file->getMimeType()
```

## Available methods

You are not limited to use auto upload image feature only. This trait will give you following methods which you can use to manually upload and resize image.

**Note:** Make sure you have disabled auto upload by setting `protected $autoUploadImages = false;` 
on model or dynamiclly by calling `$model->disableAutoUpload()`. You can also disable it for specifig field by calling `$model->setImagesField(['cover' => ['auto_upload' => false]);`
otherwise you will be not seeing your manual uploads, since it will be overwritten by auto upload upon model save.

#### $model->uploadImage($imageFile, $field = null) / $model->uploadFile($docFile, $field = null) 

Upload image/file for given $field, if $field is null it will upload to first image/file option defined in array.

```php
$user = User::findOrFail($id);
$user->uploadImage(request()->file('cover'), 'cover');
$user->uploadFile(request()->file('resume'), 'resume');
```

#### $model->setImagesField($fieldsOptions) / $model->setFilesField($fieldsOptions) 

You can also set the image/file fields dynamically by calling `$model->setImagesField($fieldsOptions) / $model->setFilesField($fieldsOptions)` with field options, it will replace fields defined on model property.

```php
$user = User::findOrFail($id);

$fieldOptions = [
    'cover' => [ 'width' => 1000 ],
    'avatar' => [ 'width' => 120, 'crop' => true ],    
];

// override image fields defined on  model 
$user->setImagesField($fieldOptions);

$fileFieldOption = [
    'resume' => ['path' => 'resumes']
];

// override file fields defined on  model
$user->setFilesField($fileFieldOption);
```

#### $model->hasImageField($field) / $model->hasFileField($field)

To check if field is defined as image/file field.

#### $model->deleteImage($filePath) / $model->deleteFile($filePath) 

Delete any image/file if it exists.

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

#### $model->imageUrl($field) / $model->fileUrl($field)

Gives uploaded file url for given image/file field.

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

### Hooks
Hooks allow you to apply different type of customizations or any other logic that you want to take place before or after the image is saved.

##### Definition types
You can define hooks by specifying a class name

```php
protected static $imageFields = [
    'avatar' => [
        'before_save' => BlurFilter::class,
    ],
    'cover' => [
        //...    
    ]
];
```

The hook class must have a method named `handle` that will be called when the hook is triggered.
An instance of the intervention image will be passed to the `handle` method.

```php
class BlurFilter {
    public function handle($image) {
        $image->blur(10);
    }
}
```

The class based hooks are resolved through laravel ioc container, which allows you to inject any dependencies through the constructor.

> Keep in mind you will be getting resized image in `before` and `after` save hook handler if you have defined field option with `width` or `height`. 
Sure you can get original image from `request()->file('avatar')` any time you want. 

The second type off hook definition is callback hooks.
```php
$user->setImagesField([
    'avatar' => [
        'before_save' => function($image) {
            $image->blur(10);
        },
    ],
    'cover' => [
        //...    
    ]
]);
```

The callback will receive the intervention image instance argument as well.

##### Hook types
There are two types of hooks a `before_save` and `after_save` hooks.

The `before_save` hook is called just before the image is saved to the disk.
Any changes made to the intervention image instance within the hook will be applied to the output image.

```php
$user->setImagesField([
    'avatar' => [
        'width' => 100,
        'height' => 100,
        'before_save' => function($image) {
            // The image will be 50 * 50, this will override the 100 * 100 
            $image->resize(50, 50);
        },
    ]
]);
```

The `after_save` hook is called right after the image was saved to the disk.

```php
$user->setImagesField([
    'logo' => [
        'after_save' => function($image) {
            // Create a watermark image and save it
        },
    ]
]);
```

### Config file

```php
<?php

return [

    /**
     * Default upload storage disk
     */
    'upload_disk' => 'public',

    /**
     * Default Image upload directory on the disc
     * eg. 'uploads' or 'user/avatar'
     */
    'upload_directory' => 'uploads',

    /**
     * Auto upload images from incoming Request if same named field or
     * file_input field on option present upon model update and create.
     * can be override in individual field options
     */
    'auto_upload_images' => true,

    /**
     * It will auto delete images once record is deleted from database
     */
    'auto_delete_images' => true,

    /**
     * Set an image quality
     */
    'resize_image_quality' => 80
];
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

If you discover any security related issues, please email saquibweb@gmail.com instead of using the issue tracker.

### Credits
- [Mohd Saqueib Ansari](https://github.com/saqueib)
- [Melek Rebai aka shadoWalker89](https://github.com/shadoWalker89)
- [João Roberto P. Borges](https://github.com/joaorobertopb)

### About QCode.in
QCode.in (https://www.qcode.in) is blog by [Saqueib](https://github.com/saqueib) which covers All about Full Stack Web Development.

### License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
