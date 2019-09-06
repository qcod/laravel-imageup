<?php

namespace QCod\ImageUp;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Validation\Factory;
use QCod\ImageUp\Exceptions\InvalidUploadFieldException;

trait HasImageUploads
{
    /**
     * All the images fields for model
     *
     * @var array
     */
    private $imagesFields = [];

    /**
     * All the file fields for model
     *
     * @var array
     */
    private $filesFields = [];

    /**
     * Image crop coordinates
     *
     * @var
     */
    private $cropCoordinates;

    /**
     * Upload field name
     *
     * @var string
     */
    private $uploadFieldName;

    /**
     * Upload field options
     *
     * @var array
     */
    private $uploadFieldOptions;

    /**
     * Flag to disable auto upload
     *
     * @var bool
     */
    protected $disableAutoUpload = false;

    /**
     * Boot up the trait
     */
    public static function bootHasImageUploads()
    {
        // hook up the events
        static::saved(function ($model) {
            // check for autoupload disabled
            if (!$model->disableAutoUpload) {
                $model->autoUpload();
            }
        });

        // delete event
        static::deleted(function ($model) {
            $model->autoDeleteImage();
        });
    }

    /**
     * Get absolute url for a field
     *
     * @param null $field
     * @return mixed|string
     * @throws InvalidUploadFieldException
     */
    public function imageUrl($field = null)
    {
        $this->uploadFieldName = $this->getUploadFieldName($field);
        $this->uploadFieldOptions = $this->getUploadFieldOptions($this->uploadFieldName);

        // get the model attribute value
        $attributeValue = $this->getOriginal($this->uploadFieldName);

        // check for placeholder defined in option
        $placeholderImage = Arr::get($this->uploadFieldOptions, 'placeholder');

        return (empty($attributeValue) && $placeholderImage)
            ? $placeholderImage
            : $this->getStorageDisk()->url($attributeValue);
    }

    /**
     * Get absolute file url for a field
     *
     * @param null $field
     * @return mixed|string
     * @throws InvalidUploadFieldException
     */
    public function fileUrl($field = null)
    {
        return $this->imageUrl($field);
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
        if (!$this->hasImageField($field) || $this->hasFileField($field)) {
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
     * @throws InvalidUploadFieldException|\Exception
     */
    public function uploadImage($imageFile, $field = null)
    {
        $this->uploadFieldName = $this->getUploadFieldName($field);
        $this->uploadFieldOptions = $this->getUploadFieldOptions($this->uploadFieldName);

        // validate it
        $this->validateImage($imageFile, $this->uploadFieldName, $this->uploadFieldOptions);

        // handle upload
        $filePath = $this->hasFileField($this->uploadFieldName)
            ? $this->handleFileUpload($imageFile)
            : $this->handleImageUpload($imageFile);

        // hold old file
        $currentFile = $this->getOriginal($this->uploadFieldName);

        // update the model with field name
        $this->updateModel($filePath, $this->uploadFieldName);

        // delete old file
        if ($currentFile != $filePath) {
            $this->deleteImage($currentFile);
        }
    }

    /**
     * Upload a file
     *
     * @param $file
     * @param null $field
     * @throws InvalidUploadFieldException
     */
    public function uploadFile($file, $field = null)
    {
        $this->uploadImage($file, $field);
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
        $width = Arr::get($imageFieldOptions, 'width');
        $height = Arr::get($imageFieldOptions, 'height');
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
            static::$imageFields = array_merge($this->getDefinedUploadFields(), $fieldsOptions);
        } else {
            $this->imagesFields = $fieldsOptions;
        }

        return $this;
    }

    /**
     * Setter for model file fields
     *
     * @param $fieldsOptions
     * @return $this
     */
    public function setFilesField($fieldsOptions)
    {
        if (isset(static::$fileFields)) {
            static::$fileFields = array_merge($this->getDefinedUploadFields(), $fieldsOptions);
        } else {
            $this->filesFields = $fieldsOptions;
        }

        return $this;
    }

    /**
     * Get upload field options
     *
     * @param $field
     * @return array
     * @throws InvalidUploadFieldException
     */
    public function getUploadFieldOptions($field = null)
    {
        // get first option if no field provided
        if (is_null($field)) {
            $imagesFields = $this->getDefinedUploadFields();

            if (!$imagesFields) {
                throw new InvalidUploadFieldException(
                    'No upload fields are defined in $imageFields/$fileFields array on model.'
                );
            }

            $fieldKey = Arr::first(array_keys($imagesFields));
            $options = is_int($fieldKey) ? [] : Arr::first($imagesFields);

            return $options;
        }

        // check if provided filed defined
        if (!$this->hasImageField($field)) {
            throw new InvalidUploadFieldException(
                'Image/File field `' . $field . '` is not defined in $imageFields/$fileFields array on model.'
            );
        }

        return Arr::get($this->getDefinedUploadFields(), $field, []);
    }

    /**
     * Get all the image and file fields defined on model
     *
     * @return array
     */
    public function getDefinedUploadFields()
    {
        $fields = isset(static::$imageFields)
            ? static::$imageFields
            : $this->imagesFields;

        return array_merge($this->getDefinedFileFields(), $fields);
    }

    /**
     * Get all the file fields defined on model
     *
     * @return array
     */
    public function getDefinedFileFields()
    {
        return isset(static::$fileFields)
            ? static::$fileFields
            : $this->filesFields;
    }

    /**
     * Get the upload field name
     *
     * @param null $field
     * @return mixed|null
     */
    public function getUploadFieldName($field = null)
    {
        if (!is_null($field)) {
            return $field;
        }

        $imagesFields = $this->getDefinedUploadFields();
        $fieldKey = Arr::first(array_keys($imagesFields));

        // return first field name
        return is_int($fieldKey)
            ? $imagesFields[$fieldKey]
            : $fieldKey;
    }

    /**
     * Check if image filed is defined
     *
     * @param $field
     * @return bool
     */
    public function hasImageField($field)
    {
        return $this->hasUploadField($field, $this->getDefinedUploadFields());
    }

    /**
     * Check if file filed is defined
     *
     * @param $field
     * @return bool
     */
    public function hasFileField($field)
    {
        return $this->hasUploadField($field, $this->getDefinedFileFields());
    }

    /**
     * Check is upload field is defined
     *
     * @param $field
     * @param $definedField
     * @return bool
     */
    private function hasUploadField($field, $definedField)
    {
        // check for string key
        if (Arr::has($definedField, $field)) {
            return true;
        }

        // check for value
        $found = false;
        foreach ($definedField as $key => $val) {
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
        return $this->deleteUploadedFile($filePath);
    }

    /**
     * Delete a file
     *
     * @param $filePath
     */
    public function deleteFile($filePath)
    {
        return $this->deleteUploadedFile($filePath);
    }

    /**
     * Delete a file from disk
     *
     * @param $filePath
     */
    private function deleteUploadedFile($filePath)
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
        if ($pathInOption = Arr::get($this->uploadFieldOptions, 'path')) {
            return $pathInOption;
        }

        return property_exists($this, 'imagesUploadPath')
            ? trim($this->imagesUploadPath, '/')
            : trim(config('imageup.upload_directory', 'uploads'), '/');
    }

    /**
     * Get the full path to upload file
     *
     * @param $file
     * @return string
     */
    protected function getFileUploadPath($file)
    {
        // check if path override is defined for current file
        $pathOverrideMethod = Str::camel(strtolower($this->uploadFieldName) . 'UploadFilePath');

        if (method_exists($this, $pathOverrideMethod)) {
            return $this->getImageUploadPath() . '/' . $this->$pathOverrideMethod($file);
        }

        return $this->getImageUploadPath() . '/' . $file->hashName();
    }

    /**
     * Get image upload disk
     *
     * @return string
     */
    protected function getImageUploadDisk()
    {
        // check for disk option
        if ($diskInOption = Arr::get($this->uploadFieldOptions, 'disk')) {
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
        if ($rules = Arr::get($imageOptions, 'rules')) {
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

        $imageQuality = Arr::get(
            $this->uploadFieldOptions,
            'resize_image_quality',
            config('imageup.resize_image_quality')
        );

        $imagePath = $this->getFileUploadPath($imageFile);

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
        $crop = Arr::get($imageFieldOptions, 'crop', false);

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
     * @throws InvalidUploadFieldException
     * @throws \Exception
     */
    protected function autoUpload()
    {
        foreach ($this->getDefinedUploadFields() as $key => $val) {
            $field = is_int($key) ? $val : $key;
            $options = Arr::wrap($val);

            // check if global upload is allowed, then in override in option
            $autoUploadAllowed = Arr::get($options, 'auto_upload', $this->canAutoUploadImages());

            if (!$autoUploadAllowed) {
                continue;
            }

            // get the input file name
            $requestFileName = Arr::get($options, 'file_input', $field);

            // if request has the file upload it
            if (request()->hasFile($requestFileName)) {
                $this->uploadImage(
                    request()->file($requestFileName),
                    $field
                );
            }
        }
    }

    /**
     * Auto delete image handler
     */
    protected function autoDeleteImage()
    {
        if (config('imageup.auto_delete_images')) {
            foreach ($this->getDefinedUploadFields() as $field => $options) {
                $field = is_numeric($field) ? $options : $field;
                $this->deleteImage($this->getOriginal($field));
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
        return Arr::has($imageFieldOptions, 'width') || Arr::has($imageFieldOptions, 'height');
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
        if (isset($this->uploadFieldOptions['before_save'])) {
            $this->triggerHook($this->uploadFieldOptions['before_save'], $image);
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
        if (isset($this->uploadFieldOptions['after_save'])) {
            $this->triggerHook($this->uploadFieldOptions['after_save'], $image);
        }

        return $this;
    }

    /**
     * Process image upload
     *
     * @param $imageFile
     * @return string
     * @throws \Exception
     */
    protected function handleImageUpload($imageFile)
    {
        // resize the image with given option
        $image = $this->resizeImage($imageFile, $this->uploadFieldOptions);

        // save the uploaded file on disk
        return $this->saveImage($imageFile, $image);
    }

    /**
     * Process file upload
     *
     * @param $file
     * @return string
     * @throws \Exception
     */
    public function handleFileUpload($file)
    {
        // Trigger before save hook
        $this->triggerBeforeSaveHook($file);

        $filePath = $this->getFileUploadPath($file);

        $this->getStorageDisk()->put($filePath, file_get_contents($file), 'public');

        // Trigger after save hook
        $this->triggerAfterSaveHook($file);

        return $filePath;
    }

    /**
     * Disable auto upload
     *
     * @return $this
     */
    public function disableAutoUpload()
    {
        $this->disableAutoUpload = true;
        return $this;
    }

    /**
     * Enable auto upload
     *
     * @return $this
     */
    public function enableAutoUpload()
    {
        $this->disableAutoUpload = false;
        return $this;
    }
}
