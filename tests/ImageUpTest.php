<?php

namespace QCod\ImageUp\Tests;

use Illuminate\Http\UploadedFile;
use QCod\ImageUp\HasImageUploads;
use QCod\ImageUp\Tests\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use QCod\ImageUp\Exceptions\InvalidImageFieldException;

class ImageUpTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
    * it_gets_image_field_options
    *
    * @test
    */
    function it_gets_image_field_options()
    {
        $user = new class() extends User
        {
            use HasImageUploads;

            protected static $imageFields = ['avatar' => [
                'width' => 200,
                'height' => 200,
                'crop' => true,
                'folder' => 'avatar'
            ]];
        };

        $this->assertArraySubset([
            'width' => 200,
            'height' => 200,
            'crop' => true,
            'folder' => 'avatar'
        ], $user->getImageFieldOptions('avatar'));
    }

    /**
    * it_throws_exception_if_image_field_not_found
    *
    * @test
    */
    function it_throws_exception_if_image_field_not_found()
    {
        $user = new User();

        $this->expectException(InvalidImageFieldException::class);
        $user->getImageFieldOptions('avatar');
    }

    /**
     * it sets image field with options
     *
     * @test
     */
    function it_sets_image_field_with_options()
    {
        $user = new User();

        $fieldOption = ['avatar' => ['width' => 200]];
        $user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $user->getDefinedImageFields());
    }

    /**
    * it sets image fields with mixed option and without options
    *
    * @test
    */
    function it_sets_image_fields_with_mixed_option_and_without_options()
    {
        $user = new User();

        $fieldOption = ['avatar' => ['width' => 200], 'cover'];
        $user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $user->getDefinedImageFields());
        $this->assertTrue($user->hasImageField('cover'));
        $this->assertTrue($user->hasImageField('avatar'));
    }

    /**
    * it sets image fields without any options
    *
    * @test
    */
    function it_sets_image_fields_without_any_options()
    {
        $user = new User();

        $fieldOption = ['avatar', 'cover'];
        $user->setImagesField($fieldOption);

        $this->assertArraySubset($fieldOption, $user->getDefinedImageFields());
        $this->assertTrue($user->hasImageField('cover'));
        $this->assertTrue($user->hasImageField('avatar'));
    }

    /**
     * it returns first field if no key provided
     *
     * @test
     */
    function it_returns_first_field_if_no_key_provided()
    {
        $user = new User();
        $fieldOption = [
            'avatar' => ['width' => 200],
            'logo' => ['width' => 400, 'height' => 400]
        ];
        $user->setImagesField($fieldOption);

        $this->assertArraySubset(['width' => 200], $user->getImageFieldOptions());
    }

    /**
     * it returns field name of first field
     *
     * @test
     */
    function it_returns_field_name_of_first_field()
    {
        $user = new User();
        $fieldOption = [
            'avatar' => ['width' => 200],
            'logo' => ['width' => 400, 'height' => 400]
        ];
        $user->setImagesField($fieldOption);

        $this->assertEquals('avatar', $user->getImageFieldName());
        $this->assertEquals('logo', $user->getImageFieldName('logo'));
    }

    /**
     * it uploads image and saves in db
     *
     * @test
     */
    function it_uploads_image_and_saves_in_db()
    {
        $user = $this->createUser();
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->assertNull($user->avatar);

        // it should upload first avatar image
        $user->uploadImage($file);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $file->hashName());
        $this->assertEquals('uploads/' . $file->hashName(), $user->fresh()->avatar);
    }

    /**
     * it uploads image by field name
     *
     * @test
     */
    function it_uploads_image_by_field_name()
    {
        $user = $this->createUser();
        $user->setImagesField(['cover' => ['width' => 100]]);
        Storage::fake('public');
        $file = UploadedFile::fake()->image('avatar.jpg');

        $this->assertNull($user->avatar);

        // it should upload first avatar image
        $user->uploadImage($file, 'cover');

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $file->hashName());
        $this->assertEquals('uploads/' . $file->hashName(), $user->fresh()->cover);
    }

    /**
     * it gives image url if image saved in db
     *
     * @test
     */
    function it_gives_image_url_if_image_saved_in_db()
    {
        $user = new class extends User
        {
            use HasImageUploads;

            static $imageFields = [
                'avatar' => ['placeholder' => '/images/cover-placeholder.png']
            ];
        };
        $user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
            'avatar' => 'uploads/my-avatar.png'
        ])->save();

        $this->assertEquals($user->avatar, 'uploads/my-avatar.png');

        $this->assertEquals(asset('storage/uploads/my-avatar.png'), $user->imageUrl());
        $this->assertEquals(asset('storage/uploads/my-avatar.png'), $user->imageUrl('avatar'));
    }

    /**
     * it gives placeholder image url if file has no image and placeholder option is defined
     *
     * @test
     */
    function it_gives_placeholder_image_url_if_file_has_no_image_and_placeholder_option_is_defined()
    {
        $user = new class extends User
        {
            use HasImageUploads;

            static $imageFields = [
                'cover' => ['placeholder' => '/images/cover-placeholder.png']
            ];
        };
        $user->forceFill([
            'name' => 'John',
            'email' => 'John@email.com',
            'password' => 'secret',
            'avatar' => '/uploads/my-avatar.png'
        ])->save();

        $this->assertNull($user->cover);
        $this->assertEquals('/images/cover-placeholder.png', $user->imageUrl());
        $this->assertEquals('/images/cover-placeholder.png', $user->imageUrl('cover'));
    }

    /**
     * it validate the uploaded file using provided rules
     *
     * @test
     */
    function it_validate_the_uploaded_file_using_provided_rules()
    {
        $user = new class extends User
        {
            use HasImageUploads;

            static $imageFields = [
                'avatar' => [
                    'rules' => 'required|image'
                ]
            ];
        };

        Storage::fake('public');
        $doc = UploadedFile::fake()->create('document.pdf');

        // it should not upload image
        $this->expectException(ValidationException::class);
        $user->uploadImage($doc);
        $this->assertNull($user->avatar);

        // it should upload image
        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($user->avatar);
        $user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $user->fresh()->avatar);
    }

    /**
     * it uploads and resize image in proportion if crop is not set
     *
     * @test
     */
    function it_uploads_and_resize_image_in_proportion_if_crop_is_not_set()
    {
        $user = $this->createUser([], [
            'avatar' => [
                'width' => 200,
                'height' => 300
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 400, 500);
        $this->assertNull($user->avatar);
        $user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $user->fresh()->avatar);

        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertEquals(200, $imageWidth);
        $this->assertNotEquals(300, $imageHeight);
    }

    /**
     * it upload and resize image by given height
     *
     * @test
     */
    function it_upload_and_resize_image_by_given_height()
    {
        $user = $this->createUser([], [
            'avatar' => [
                'height' => 300
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg', 400, 500);
        $this->assertNull($user->avatar);
        $user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $user->fresh()->avatar);

        list($imageWidth, $imageHeight) = getimagesize(Storage::disk('public')->path('uploads/' . $image->hashName()));
        $this->assertNotEquals(400, $imageWidth);
        $this->assertEquals(300, $imageHeight);
    }

    /**
     * it uses disk specified in field option
     *
     * @test
     */
    function it_uses_disk_specified_in_field_option()
    {
        $user = $this->createUser([], [
            'avatar' => [
                'width' => 300,
                'disk' => 'local'
            ]
        ]);

        Storage::fake('local');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($user->avatar);
        $user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('local')->assertExists('uploads/' . $image->hashName());
        $this->assertEquals('uploads/' . $image->hashName(), $user->fresh()->avatar);
    }

    /**
     * it uses path specified in field option
     *
     * @test
     */
    function it_uses_path_specified_in_field_option()
    {
        $user = $this->createUser([], [
            'avatar' => [
                'width' => 300,
                'path' => 'avatar'
            ]
        ]);

        Storage::fake('public');

        $image = UploadedFile::fake()->image('avatar.jpg');
        $this->assertNull($user->avatar);
        $user->uploadImage($image);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('avatar/' . $image->hashName());
        Storage::disk('public')->assertMissing('uploads/' . $image->hashName());
        $this->assertEquals('avatar/' . $image->hashName(), $user->fresh()->avatar);
    }

    /**
    * it auto uploads images if config is set do it
    *
    * @test
    */
    function it_auto_uploads_images_if_config_is_set_do_it()
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('avatar.jpg')->size(100);

        $payload = [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret',
            'avatar' => $image
        ];

        $this->post('test/users', $payload)->assertStatus(201);

        Storage::disk('public')->assertExists('uploads/' . $image->hashName());
    }
}
