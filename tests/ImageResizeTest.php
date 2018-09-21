<?php

namespace QCod\ImageUp\Tests;

use QCod\ImageUp\HasImageUploads;
use QCod\ImageUp\Tests\Models\User;

class ImageResizeTest extends TestCase
{
    protected  $testImage = __DIR__ . '/images/test1200x1200.png';
    protected  $newImage;
    protected $user;

    protected function setUp()
    {
        parent::setUp();

        $this->user = new class extends User {
            use HasImageUploads;
        };

        $this->assertTrue(file_exists($this->testImage), 'Test image file do not exists.');
    }

    protected function tearDown()
    {
        parent::tearDown();

        if( $this->newImage ) {
            $this->newImage->destroy();
        }
    }


    /**
    * it resize image based on width given
    *
    * @test
    */
    function it_resize_image_based_on_width_given()
    {
        $this->newImage = $this->user->resizeImage($this->testImage, ['width' => 300]);

        $this->assertEquals(300, $this->newImage->width());
    }

    /**
    * it resize image by height
    *
    * @test
    */
    function it_resize_image_by_height()
    {
        $this->newImage = $this->user->resizeImage($this->testImage, ['height' => 200]);

        $this->assertEquals(200, $this->newImage->height());
    }

    /**
    * it crops image in given width and height
    *
    * @test
    */
    function it_crops_image_in_given_width_and_height()
    {
        $this->newImage = $this->user->resizeImage($this->testImage,
            [
                'width' => 150,
                'height' => 150,
                'crop' => true
            ]);

        $this->assertEquals(150, $this->newImage->width());
        $this->assertEquals(150, $this->newImage->height());
    }

    /**
     * it crops in x and y if crop is set to array of coordinates
     *
     * @test
     */
    function it_crops_in_x_and_y_if_crop_is_set_to_array_of_coordinates()
    {
        $this->newImage = $this->user->resizeImage($this->testImage,
            [
                'width' => 100,
                'height' => 100,
                'crop' => [25, 10]
            ]);

        $this->assertEquals(100, $this->newImage->width());
        $this->assertEquals(100, $this->newImage->height());
    }

    /**
    * it can override the crop x and y coordinates
    *
    * @test
    */
    function it_can_override_the_crop_x_and_y_coordinates()
    {
        $this->newImage = $this->user->cropTo(10, 0)->resizeImage($this->testImage,
            [
                'width' => 100,
                'height' => 100,
                'crop' => [25, 10]
            ]);

        $this->assertEquals(100, $this->newImage->width());
        $this->assertEquals(100, $this->newImage->height());
    }

    /**
    * it do not resize if width and height are not provided
    *
    * @test
    */
    function it_do_not_resize_if_width_and_height_are_not_provided()
    {
        $this->newImage = $this->user->resizeImage($this->testImage, []);

        $this->assertEquals(1200, $this->newImage->width());
        $this->assertEquals(1200, $this->newImage->height());
    }
}
