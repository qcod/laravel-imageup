<?php

namespace QCod\ImageUp;

use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Validation\Factory;
use QCod\ImageUp\Exceptions\InvalidImageFieldException;

trait HasImageUploads
{
    /**
     * All the images fields for model
     *
     * @var array
     */
    private $imagesFields = [];

    /**
     * Image Crop coordinates
     *
     * @var
     */
    private $cropCoordinates;

    /**
     * Field name for an image
     *
     * @var string
     */
    private $imageFieldName;

    /**
     * Image field options
     *
     * @var array
     */
    private $imageFieldOptions;

    /**
     * Boot up the trait
     */
    public static function bootHasImageUploads()
    {
        // hook up the events
        static::saved(function ($model) {
            $model->autoUpload();
        });

        // delete event
        static::deleted(function ($model) {
            $model->autoDeleteImage();
        });
    }

    /**
     * Get absolute Image url for a field
     *
     * @param null $field
     * @return mixed|string
     * @throws InvalidImageFieldException
     */
    public function imageUrl($field = null)
    {
        $this->imageFieldName = $this->getImageFieldName($field);
        $this->imageFieldOptions = $this->getImageFieldOptions($this->imageFieldName);

        // get the model attribute value
        $attributeValue = $this->getAttributeValue($this->imageFieldName);

        // check for placeholder defined in option
        $placeholderImage = array_get($this->imageFieldOptions, 'placeholder');

        return (empty($attributeValue) && $placeholderImage)
            ? $placeholderImage
            : $this->getStorageDisk()->url($attributeValue);
    }

    /**
     * Get html image tag for a field if image present
     *
     * @param null $field
     * @param string $attributes
     * @return string
     */
    public function imageTag($field = null, $attributes = '')
    {
        // if no field found just return empty string
        if (!$this->hasImageField($field)) {
            return '';
        }

        try {
            return '<img src="' . $this->imageUrl($field) . '" ' . $attributes . ' />';
        } catch (\Exception $exception) {
        }
    }

    /**
     * Upload and resize image
     *
     * @param $imageFile
     * @param null $field
     * @throws InvalidImageFieldException|\Exception
     */
    public function uploadImage($imageFile, $field = null)
    {
        $this->imageFieldName = $this->getImageFieldName($field);
        $this->imageFieldOptions = $this->getImageFieldOptions($this->imageFieldName);

        // validate it
        $this->validateImage($imageFile, $this->imageFieldName, $this->imageFieldOptions);

        // resize the image with given option
        $image = $this->resizeImage($imageFile, $this->imageFieldOptions);

        // save the uploaded file on disk
        $imagePath = $this->saveImage($imageFile, $image);

        // hold old image
        $currentImage = $this->getAttributeValue($this->imageFieldName);

        // update the model with field name
        $this->updateModel($imagePath, $this->imageFieldName);

        // delete old image
        $this->deleteImage($currentImage);
    }

    /**
     * Resize image based on options
     *
     * @param $imageFile
     * @param $imageFieldOptions array
     * @return \Intervention\Image\Image
     */
    public function resizeImage($imageFile, $imageFieldOptions)
    {
        $image = Image::make($imageFile);

        // check if resize needed
        if (!$this->needResizing($imageFieldOptions)) {
            return $image;
        }

        // resize it according to options
        $width = array_get($imageFieldOptions, 'width');
        $height = array_get($imageFieldOptions, 'height');
        $cropHeight = empty($height) ? $width : $height;
        $crop = $this->getCropOption($imageFieldOptions);

        // crop it if option is set to true
        if ($crop === true) {
            $image->fit($width, $cropHeight, function ($constraint) {
                $constraint->upsize();
            });

            return $image;
        }

        // crop with x,y coordinate array
        if (is_array($crop) && count($crop) == 2) {
            list($x, $y) = $crop;

            $image->crop($width, $cropHeight, $x, $y);

            return $image;
        }

        // or resize it with given width and height
        $image->resize($width, $height, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });

        return $image;
    }

    /**
     * Override Crop X and Y coordinates
     *
     * @param $x integer
     * @param $y integer
     * @return $this
     */
    public function cropTo($x, $y)
    {
        $this->cropCoordinates = [$x, $y];
        return $this;
    }

    /**
     * Setter for model image fields
     *
     * @param $fieldsOptions
     * @return $this
     */
    public function setImagesField($fieldsOptions)
    {
        if (isset(static::$imageFields)) {
            static::$imageFields = array_merge($this->getDefinedImageFields(), $fieldsOptions);
        } else {
            $this->imagesFields = $fieldsOptions;
        }

        return $this;
    }

    /**
     * Get image field options
     *
     * @param $field
     * @return array
     * @throws InvalidImageFieldException
     */
    public function getImageFieldOptions($field = null)
    {
        // get first option if no field provided
        if (is_null($field)) {
            $options = array_first($this->getDefinedImageFields());

            if (!$options) {
                throw new InvalidImageFieldException(
                    'No image fields are defined in $imageFields array on model.'
                );
            }

            return $options;
        }

        // check if provided filed defined
        if (!$this->hasImageField($field)) {
            throw new InvalidImageFieldException(
                'Image field `' . $field . '` is not defined in $imageFields array on model.'
            );
        }

        return array_get($this->getDefinedImageFields(), $field);
    }

    /**
     * Get all the image fields defined on model
     *
     * @return array
     */
    public function getDefinedImageFields()
    {
        return isset(static::$imageFields)
            ? static::$imageFields
            : $this->imagesFields;
    }

    /**
     * Get the image field name
     *
     * @param null $field
     * @return mixed|null
     */
    public function getImageFieldName($field = null)
    {
        if (!is_null($field)) {
            return $field;
        }

        // return first field name
        $fieldKey = array_keys($this->getDefinedImageFields());
        return array_first($fieldKey);
    }

    /**
     * Check if image filed is defined
     *
     * @param $field
     * @return bool
     */
    public function hasImageField($field)
    {
        // check for string key
        if (array_has($this->getDefinedImageFields(), $field)) {
            return true;
        }

        // check for value
        $found = false;
        foreach ($this->getDefinedImageFields() as $key => $val) {
            $found = (is_numeric($key) && $val === $field);

            if ($found) {
                break;
            }
        }

        return $found;
    }

    /**
     * Delete an Image
     *
     * @param $filePath
     */
    public function deleteImage($filePath)
    {
        if ($this->getStorageDisk()->exists($filePath)) {
            $this->getStorageDisk()->delete($filePath);
        }
    }

    /**
     * Get image upload path
     *
     * @return string
     */
    protected function getImageUploadPath()
    {
        // check for disk option
        if ($pathInOption = array_get($this->imageFieldOptions, 'path')) {
            return $pathInOption;
        }

        return property_exists($this, 'imagesUploadPath')
            ? trim($this->imagesUploadPath, '/')
            : trim(config('imageup.upload_directory', 'uploads'), '/');
    }

    /**
     * Get image upload disk
     *
     * @return string
     */
    protected function getImageUploadDisk()
    {
        // check for disk option
        if ($diskInOption = array_get($this->imageFieldOptions, 'disk')) {
            return $diskInOption;
        }

        return property_exists($this, 'imagesUploadDisk')
            ? $this->imagesUploadDisk
            : config('imageup.upload_disk', 'public');
    }

    /**
     * Check if auto upload is allowed
     *
     * @return boolean
     */
    protected function canAutoUploadImages()
    {
        return property_exists($this, 'autoUploadImages')
            ? $this->autoUploadImages
            : config('imageup.auto_upload_images', false);
    }

    /**
     * Get storage disk
     *
     * @return \Illuminate\Contracts\Filesystem\Filesystem
     */
    protected function getStorageDisk()
    {
        return Storage::disk($this->getImageUploadDisk());
    }

    /**
     * Validate image file with given rules in option
     *
     * @param $file
     * @param $fieldName
     * @param $imageOptions
     */
    protected function validateImage($file, $fieldName, $imageOptions)
    {
        if ($rules = array_get($imageOptions, 'rules')) {
            $this->validationFactory()->make(
                [$fieldName => $file],
                [$fieldName => $rules]
            )->validate();
        }
    }

    /**
     * Save the image to disk
     *
     * @param $imageFile
     * @param $image
     * @return string
     * @throws \Exception
     */
    protected function saveImage($imageFile, $image)
    {
        // Trigger before save hook
        $this->triggerBeforeSaveHook($image);

        $imageQuality = array_get(
            $this->imageFieldOptions,
            'resize_image_quality',
            config('imageup.resize_image_quality')
        );

        $imagePath = $this->getImageUploadPath() . '/' . $imageFile->hashName();

        $this->getStorageDisk()->put(
            $imagePath,
            (string)$image->encode(null, $imageQuality),
            'public'
        );

        // Trigger after save hook
        $this->triggerAfterSaveHook($image);

        // clean up
        $image->destroy();

        return $imagePath;
    }

    /**
     * update the model field
     *
     * @param $imagePath
     * @param $imageFieldName
     */
    protected function updateModel($imagePath, $imageFieldName)
    {
        $this->attributes[$imageFieldName] = $imagePath;

        $dispatcher = $this->getEventDispatcher();
        self::unsetEventDispatcher();
        $this->save();
        self::setEventDispatcher($dispatcher);
    }

    /**
     * Get the crop option
     *
     * @param $imageFieldOptions
     * @return array|boolean
     */
    protected function getCropOption($imageFieldOptions)
    {
        $crop = array_get($imageFieldOptions, 'crop', false);

        // check for crop override
        if (isset($this->cropCoordinates) && count($this->cropCoordinates) == 2) {
            $crop = $this->cropCoordinates;
        }

        return $crop;
    }

    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function validationFactory()
    {
        return app(Factory::class);
    }

    /**
     * Auto image upload handler
     *
     * @throws InvalidImageFieldException
     * @throws \Exception
     */
    protected function autoUpload()
    {
        foreach ($this->getDefinedImageFields() as $field => $options) {
            // check if global upload is allowed, then in override in option
            $autoUploadAllowed = array_get($options, 'auto_upload', $this->canAutoUploadImages());

            if (is_array($options) && count($options) && $autoUploadAllowed) {
                // get the input file name
                $requestFileName = array_get($options, 'file_input', $field);

                // if request has the file upload it
                if (request()->hasFile($requestFileName)) {
                    $this->uploadImage(
                        request()->file($requestFileName),
                        $field
                    );
                }
            }
        }
    }

    /**
     * Auto delete image handler
     */
    protected function autoDeleteImage()
    {
        if (config('imageup.auto_delete_images')) {
            foreach ($this->getDefinedImageFields() as $field => $options) {
                $field = is_numeric($field) ? $options : $field;
                $this->deleteImage($this->getAttributeValue($field));
            }
        }
    }

    /**
     * Check if image need resizing from options
     *
     * @param $imageFieldOptions
     * @return bool
     */
    protected function needResizing($imageFieldOptions)
    {
        return array_has($imageFieldOptions, 'width') || array_has($imageFieldOptions, 'height');
    }

    /**
     * This will try to trigger the hook depending on the user definition.
     *
     * @param $hook
     * @param $image
     *
     * @throws \Exception
     */
    protected function triggerHook($hook, $image)
    {
        if (is_callable($hook)) {
            $hook($image);
        }

        // We assume that the user is passing the hook class name
        if (is_string($hook)) {
            $instance = app($hook);
            $instance->handle($image);
        }
    }

    /**
     * Trigger user defined before save hook.
     *
     * @param $image
     *
     * @return $this
     * @throws \Exception
     */
    protected function triggerBeforeSaveHook($image)
    {
        if (isset($this->imageFieldOptions['before_save'])) {
            $this->triggerHook($this->imageFieldOptions['before_save'], $image);
        }

        return $this;
    }

    /**
     * Trigger user defined after save hook.
     *
     * @param $image
     *
     * @return $this
     * @throws \Exception
     */
    protected function triggerAfterSaveHook($image)
    {
        if (isset($this->imageFieldOptions['after_save'])) {
            $this->triggerHook($this->imageFieldOptions['after_save'], $image);
        }

        return $this;
    }
}
