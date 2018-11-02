<?php

namespace QCod\ImageUp\Tests;

use QCod\ImageUp\HasFileUploads;
use Illuminate\Http\UploadedFile;
use QCod\ImageUp\Tests\Models\User;
use Illuminate\Support\Facades\Storage;

class FileUpTest extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
    }

    /**
     * it gets defined file fields
     *
     * @test
     */
    public function it_gets_defined_file_fields()
    {
        $user = new FileUploadModel();

        $this->assertTrue($user->hasFileField('resume'));
        $this->assertTrue($user->hasFileField('cover_letter'));
    }

    /**
     * it uploads file and saves in db
     *
     * @test
     */
    public function it_uploads_file_and_saves_in_db()
    {
        Storage::fake('public');
        $user = new FileUploadModel([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret',
        ]);

        $file = UploadedFile::fake()->create('resume.doc', 60);

        $this->assertNull($user->getOriginal('resume'));

        // it should upload first avatar file
        $user->uploadFile($file);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('resumes/' . $file->hashName());
        $this->assertEquals('resumes/' . $file->hashName(), $user->fresh()->getOriginal('resume'));
    }

    /**
     * it gives file url if file saved in db
     *
     * @test
     */
    public function it_gives_file_url_if_file_saved_in_db()
    {
        $user = FileUploadModel::create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => 'secret',
            'resume' => 'resumes/my-winning-cv.doc'
        ]);

        $this->assertEquals($user->getOriginal('resume'), 'resumes/my-winning-cv.doc');

        $this->assertEquals('/storage/resumes/my-winning-cv.doc', $user->fileUrl());
        $this->assertEquals('/storage/resumes/my-winning-cv.doc', $user->fileUrl('resume'));
    }

    /**
     * it auto upload files
     *
     * @test
     */
    public function it_auto_upload_files()
    {
        Storage::fake('public');

        $resume = UploadedFile::fake()->create('resume.doc');

        $data = [
            'name' => 'Saqueib',
            'email' => 'me@example.com',
            'password' => 'secret',
            'resume' => $resume
        ];

        $response = $this->post('/test/users-file', $data);
        $user = $response->original;

        $response->assertStatus(200);

        // Assert the file was stored...
        Storage::disk('public')->assertExists('resumes/' . $resume->hashName());
        Storage::disk('public')->assertExists('resumes/' . $resume->hashName());

        $this->assertNotNull($user->getOriginal('resume'));

        $this->assertEquals('resumes/' . $resume->hashName(), $user->getOriginal('resume'));
    }
}

class FileUploadModel extends User
{
    protected static $fileFields = [
        'resume' => [
            'path' => 'resumes'
        ],
        'cover_letter'
    ];
}
