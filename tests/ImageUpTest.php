<?php

namespace QCod\ImageUp\Tests;

use DMS\PHPUnitExtensions\ArraySubset\ArraySubsetAsserts;
use Illuminate\Http\UploadedFile;
use QCod\ImageUp\Tests\Models\User;
use QCod\ImageUp\Tests\Models\ModelWithMutator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use QCod\ImageUp\Exceptions\InvalidUploadFieldException;

class ImageUpTest extends TestCase
{
    use ArraySubsetAsserts;

    protected $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->user = null;
    }

    /**
     * it_gets_image_field_options
     *
     * @test
     */
    public function it_gets_image_field_options()
    {
        $this->user = new ImageFieldOptionsModel();

        $this->assertArraySubset([
            'width' => 200,
            'height' => 200,
            'crop' => true,
            'folder' => 'avatar'
        ], $this->user->getUploadFieldOptions('avatar'));
    }

    /**
     * it_throws_exception_if_image_field_not_found
     *
     * @test
     */
    public function it_throws_exception_if_image_field_not_found()
    {
        $this->user = new User();

        $this->expectException(InvalidUploadFieldException::class);
        $this->user->getUploadFieldOptions('avatar');
    }

    /**
     * it sets image field with options
     *
     * @test
     */
    public function it_sets_image_field_with_options()
    {
        $this->user = new User();

        $fieldOption = ['avatar' => ['width' => 200]];
        $this->user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $this->user->getDefinedUploadFields());
    }

    /**
     * it sets image fields with mixed option and without options
     *
     * @test
     */
    public function it_sets_image_fields_with_mixed_option_and_without_options()
    {
        $this->user = new User();

        $fieldOption = ['avatar' => ['width' => 200], 'cover'];
        $this->user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $this->user->getDefinedUploadFields());
        $this->assertTrue($this->user->hasImageField('cover'));
        $this->assertTrue($this->user->hasImageField('avatar'));
    }

    /**
     * it sets image fields without any options
     *
     * @test
     */
    public function it_sets_image_fields_without_any_options()
    {
        $this->user = new User();

        $fieldOption = ['avatar', 'cover'];
        $this->user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $this->user->getDefinedUploadFields());
        $this->assertTrue($this->user->hasImageField('cover'));
        $this->assertTrue($this->user->hasImageField('avatar'));
    }

    /**
     * it returns first field if no key provided
     *
     * @test
     */
    public function it_returns_first_field_if_no_key_provided()
    {
        $this->user = new User();
        $fieldOption = [
            'avatar' => ['width' => 200],
            'logo' => ['width' => 400, 'height' => 400]
        ];
        $this->user->setImagesField($fieldOption);

        $this->assertArraySubset(['width' => 200], $this->user->getUploadFieldOptions());
    }

    /**
     * it returns field name of first field
     *
     * @test
     */
    public function it_returns_field_name_of_first_field()
    {
        $this->user = new User();
        $fieldOption = [
            'avatar' => ['width' => 200],
            'logo' => ['width' => 400, 'height' => 400]
        ];
        $this->user->setImagesField($fieldOption);

        $this->assertEquals('avatar', $this->user->getUploadFieldName());
        $this->assertEquals('logo', $this->user->getUploadFieldName('logo'));
    }

    /**
     * it returns first field without any options
     *
     * @test
     */
    public function it_returns_first_field_without_any_options()
    {
        $this->user = new User();
        $fieldOption = [
            'avatar',
            'logo' => ['width' => 300, 'height' => 300]
        ];
        $this->user->setImagesField($fieldOption);

        $this->assertEquals('avatar', $this->user->getUploadFieldName());
        $this->assertEquals('avatar', $this->user->getUploadFieldName('avatar'));
        $this->assertSame([], $this->user->getUploadFieldOptions());
        $this->assertSame([], $this->user->getUploadFieldOptions('avatar'));
    }

    /**
     * it uploads image and saves in db
     *
     * @test
     */
    public function it_uploads_image_and_saves_in_db()
    {
        $this->user = $this->createUser();
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->assertNull($this->user->getOriginal('avatar'));

        // it should upload first avatar image
        $this->user->uploadImage($file);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $file->hashName());
        $this->assertEquals('uploads/' . $file->hashName(), $this->user->fresh()->getOriginal('avatar'));
    }

    /**
     * it uploads image by field name
     *
     * @test
     */
    public function it_uploads_image_by_field_name()
    {
        $this->user = $this->createUser();
        $this->user->setImagesField(['cover' => ['width' => 100]]);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->assertNull($this->user->getOriginal('avatar'));

        // it should upload first avatar image
        $this->user->uploadImage($file, 'cover');

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $file->hashName());
        $this->assertEquals('uploads/' . $file->hashName(), $this->user->fresh()->getOriginal('cover'));
    }

    /**
     * it gives image url if image saved in db
     *
     * @test
     */
    public function it_gives_image_url_if_image_saved_in_db()
    {
        $this->user = new User();
        $this->user->setImagesField([
            'avatar' => ['placeholder' => '/images/cover-placeholder.png']
        ]);

        $this->user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
            'avatar' => 'uploads/my-avatar.png'
        ])->save();

        $this->assertEquals($this->user->getOriginal('avatar'), 'uploads/my-avatar.png');

        $this->assertEquals('/storage/uploads/my-avatar.png', $this->user->imageUrl());
        $this->assertEquals('/storage/uploads/my-avatar.png', $this->user->imageUrl('avatar'));
    }

    /**
     * it gives placeholder image url if file has no image and placeholder option is defined
     *
     * @test
     */
    public function it_gives_placeholder_image_url_if_file_has_no_image_and_placeholder_option_is_defined()
    {
        $this->user = new User();
        $this->user->setImagesField([
            'cover' => ['placeholder' => '/images/cover-placeholder.png']
        ]);

        $this->user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
            'avatar' => '/uploads/my-avatar.png'
        ])->save();

        $this->assertNull($this->user->getOriginal('cover'));
        $this->assertEquals('/images/cover-placeholder.png', $this->user->imageUrl());
        $this->assertEquals('/images/cover-placeholder.png', $this->user->imageUrl('cover'));
    }

    /**
     * it validate the uploaded file using provided rules
     *
     * @test
     */
    public function it_validate_the_uploaded_file_using_provided_rules()
    {
        $this->user = new User();
        $this->user->setImagesField([
            'avatar' => [
                'rules' => 'required|image'
            ]
        ]);

        Storage::fake('public');
        $doc = UploadedFile::fake()->create('document.pdf');

        // it should not upload image
        $this->expectException(ValidationException::class);
        $this->user->uploadImage($doc);
        $this->assertNull($this->user->getOriginal('avatar'));

        // it should upload image
        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));
    }

    /**
     * it uploads and resize image in proportion if crop is not set
     *
     * @test
     */
    public function it_uploads_and_resize_image_in_proportion_if_crop_is_not_set()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'width' => 200,
                'height' => 300
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 400, 500);
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));

        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertEquals(200, $imageWidth);
        $this->assertNotEquals(300, $imageHeight);
    }

    /**
     * it upload and resize image by given height
     *
     * @test
     */
    public function it_upload_and_resize_image_by_given_height()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'height' => 300
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 400, 500);
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));

        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertNotEquals(400, $imageWidth);
        $this->assertEquals(300, $imageHeight);
    }

    /**
     * it uses disk specified in field option
     *
     * @test
     */
    public function it_uses_disk_specified_in_field_option()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'width' => 300,
                'disk' => 'local'
            ]
        ]);

        Storage::fake('local');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('local')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));
    }

    /**
     * it uses path specified in field option
     *
     * @test
     */
    public function it_uses_path_specified_in_field_option()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'width' => 300,
                'path' => 'avatar'
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('avatar/' . $image->hashName());
        Storage::disk('public')->assertMissing('uploads/' . $image->hashName());
        $this->assertEquals('avatar/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));
    }

    /**
     * it auto uploads images if config is set do it
     *
     * @test
     */
    public function it_auto_uploads_images_if_config_is_set_do_it()
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('avatar.jpg')->size(100);

        $payload = [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret',
            'avatar' => $image
        ];

        $this->post('test/users', $payload)->assertStatus(200);

        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
    }

    /**
     * it auto upload images
     *
     * @test
     */
    public function it_auto_upload_images()
    {
        Storage::fake('public');

        $cover = UploadedFile::fake()->image('cover.jpg');
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret',
            'avatar' => $avatar,
            'cover' => $cover,
        ];

        $response = $this->post('/test/users', $data);
        $this->user = $response->original;

        $response->assertStatus(200);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $avatar->hashName());
        Storage::disk('public')->assertExists('uploads/' . $cover->hashName());


        $this->assertNotNull($this->user->getOriginal('avatar'));
        $this->assertNotNull($this->user->getOriginal('cover'));

        $this->assertEquals('uploads/' . $avatar->hashName(), $this->user->getOriginal('avatar'));
        $this->assertEquals('uploads/' . $cover->hashName(), $this->user->getOriginal('cover'));
    }

    /**
     * it dont auto upload files if disabled
     *
     * @test
     */
    public function it_dont_auto_upload_files_if_disabled()
    {
        Storage::fake('public');

        $cover = UploadedFile::fake()->image('cover.jpg');
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret',
            'avatar' => $avatar,
            'cover' => $cover,
        ];

        $response = $this->post('/test/users-auto-upload-disabled', $data);
        $this->user = $response->original;

        $response->assertStatus(200);

        // Assert the file was stored...
        Storage::disk('public')->assertMissing('uploads/' . $avatar->hashName());
        Storage::disk('public')->assertMissing('uploads/' . $cover->hashName());

        $this->assertNull($this->user->getOriginal('avatar'));
        $this->assertNull($this->user->getOriginal('cover'));
    }

    /**
     * it auto upload images without options
     *
     * @test
     */
    public function it_auto_upload_images_without_options()
    {
        Storage::fake('public');

        $cover = UploadedFile::fake()->image('cover.jpg');
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret',
            'avatar' => $avatar,
            'cover' => $cover,
        ];

        $response = $this->post('/test/users/uploads/images-without-options', $data);
        $this->user = $response->original;

        $response->assertStatus(201);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $avatar->hashName());
        Storage::disk('public')->assertExists('uploads/' . $cover->hashName());

        $this->assertNotNull($this->user->getOriginal('avatar'));
        $this->assertNotNull($this->user->getOriginal('cover'));

        $this->assertEquals('uploads/' . $avatar->hashName(), $this->user->getOriginal('avatar'));
        $this->assertEquals('uploads/' . $cover->hashName(), $this->user->getOriginal('cover'));
    }

    /**
     * it auto upload images with mixed options
     *
     * @test
     */
    public function it_auto_upload_images_with_mixed_options()
    {
        Storage::fake('public');

        $cover = UploadedFile::fake()->image('cover.jpg');
        $avatar = UploadedFile::fake()->image('avatar.jpg');

        $data = [
            'name' => 'Foo Bar',
            'email' => 'foo@bar.com',
            'password' => 'secret',
            'avatar' => $avatar,
            'cover' => $cover,
        ];

        $response = $this->post('/test/users/uploads/images-with-mixed-options', $data);
        $this->user = $response->original;

        $response->assertStatus(201);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $avatar->hashName());
        Storage::disk('public')->assertMissing('uploads/' . $cover->hashName());

        $this->assertNotNull($this->user->getOriginal('avatar'));
        $this->assertNull($this->user->getOriginal('cover'));

        $this->assertEquals('uploads/' . $avatar->hashName(), $this->user->getOriginal('avatar'));
        $this->assertNotEquals('uploads/' . $cover->hashName(), $this->user->getOriginal('cover'));
    }

    /**
     * it triggers before save hook from a class.
     *
     * @test
     */
    public function it_triggers_before_save_hook_from_a_class()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'width' => 100,
                'height' => 100,
                'before_save' => '\QCod\ImageUp\Tests\Hooks\ResizeToFiftyHook',
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->user->uploadImage($image);

        // The size of the image should be 50*50 defined in the hook instead of the ones defined on the user class
        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertEquals(50, $imageWidth);
        $this->assertEquals(50, $imageHeight);
    }

    /**
     * it triggers before save hook from a callback.
     *
     * @test
     */
    public function it_triggers_before_save_hook_from_a_callback()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'width' => 100,
                'height' => 100,
                'before_save' => function ($image) {
                    $image->resize(50, 50);
                },
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->user->uploadImage($image);

        // The size of the image should be 50*50 defined in the hook instead of the ones defined on the user class
        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertEquals(50, $imageWidth);
        $this->assertEquals(50, $imageHeight);
    }

    /**
     * it triggers after save hook from a class.
     *
     * @test
     */
    public function it_triggers_after_save_hook_from_a_class()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'after_save' => '\QCod\ImageUp\Tests\Hooks\CopyImageHook',
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->user->uploadImage($image);

        // Make sure that the copied image from the hook exists
        Storage::disk('public')->assertExists('uploads/copy_from_hook.jpg');

        // Make sure that the uploaded and copied images are the same
        $this->assertEquals(
            md5(Storage::disk('public')->get('uploads/' . $image->hashName())),
            md5(Storage::disk('public')->get('uploads/copy_from_hook.jpg'))
        );
    }

    /**
     * it triggers after save hook from a callback.
     *
     * @test
     */
    public function it_triggers_after_save_hook_from_a_callback()
    {
        $this->user = $this->createUser([], [
            'avatar' => [
                'after_save' => function ($image) {
                    Storage::disk('public')->put(
                        'uploads/copy_from_hook.jpg',
                        (string)$image->encode(null, 80),
                        'public'
                    );
                },
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->user->uploadImage($image);

        // Make sure that the copied image from the hook exists
        Storage::disk('public')->assertExists('uploads/copy_from_hook.jpg');

        // Make sure that the uploaded and copied images are the same
        $this->assertEquals(
            md5(Storage::disk('public')->get('uploads/' . $image->hashName())),
            md5(Storage::disk('public')->get('uploads/copy_from_hook.jpg'))
        );
    }

    /**
     * it gives correct value when model has mutator method
     *
     * test
     */
    public function it_gives_correct_value_when_model_has_mutator_method()
    {
        $this->user = new ModelWithMutator();

        $this->user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
            'avatar' => 'uploads/my-avatar.png'
        ])->save();

        $this->assertEquals($this->user->getOriginal('avatar'), 'uploads/my-avatar.png');
        $this->assertEquals('/storage/uploads/my-avatar.png', $this->user->imageUrl('avatar'));
        $this->assertEquals('/storage/uploads/my-avatar.png', $this->user->avatar);
    }

    /**
     * it gives correct value using path specified in field options when model has mutator method
     *
     * test
     */
    public function it_gives_correct_value_using_path_specified_in_field_option_when_model_has_mutator_method()
    {
        $this->user = new ModelWithMutator();
        $this->user->setImagesField([
            'avatar' => [
                'width' => 300,
                'path' => 'avatar'
            ]
        ]);

        $this->user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
        ])->save();

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('avatar/' . $image->hashName());
        Storage::disk('public')->assertMissing('uploads/' . $image->hashName());

        $this->assertEquals('avatar/' . $image->hashName(), $this->user->fresh()->getOriginal('avatar'));
        $this->assertEquals('/storage/avatar/' . $image->hashName(), $this->user->avatar);
    }

    /**
     * it can override file path and filename if method defined on model
     *
     * @test
     */
    public function it_can_override_file_path_and_filename_if_method_defined_on_model()
    {
        $this->user = new CustomFilenameModel([
            'name' => 'Saqueib',
            'email' => 'info@example.com',
            'password' => 'secret',
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($this->user->getOriginal('avatar'));
        $this->user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('avatar/custome-avatar.jpg');
        Storage::disk('public')->assertMissing('uploads/custome-avatar.jpg');
        $this->assertEquals('avatar/custome-avatar.jpg', $this->user->fresh()->getOriginal('avatar'));
    }
}

//class User extends \Illuminate\Foundation\Auth\User {
//
//}

class CustomFilenameModel extends User
{
    public static $imageFields = [
        'avatar' => [
            'width' => 300,
            'path' => 'avatar'
        ]
    ];

    protected function avatarUploadFilePath($file)
    {
        return 'custome-avatar.jpg';
    }
}

class ImageFieldOptionsModel extends User
{
    protected static $imageFields = ['avatar' => [
        'width' => 200,
        'height' => 200,
        'crop' => true,
        'folder' => 'avatar'
    ]];
}
