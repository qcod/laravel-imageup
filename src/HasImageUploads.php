<?php

namespace QCod\ImageUp;

use Exception;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Contracts\Validation\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use QCod\ImageUp\Exceptions\InvalidUploadFieldException;

trait HasImageUploads
{
    /**
     * Flag to disable auto upload
     *
     * @var bool
     */
    protected $disableAutoUpload = false;
    /**
     * All the images fields for model
     *
     * @var string[]
     */
    private $imagesFields = [];
    /**
     * All the file fields for model
     *
     * @var string[]
     */
    private $filesFields = [];
    /**
     * Image crop coordinates
     *
     * @var int[]
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
     * Boot up the trait
     */
    public static function bootHasImageUploads(): void
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
     * Get absolute file url for a field
     *
     * @param  string|null  $field
     *
     * @return mixed|string
     * @throws InvalidUploadFieldException
     */
    public function fileUrl(?string $field = null): string
    {
        return $this->imageUrl($field);
    }

    /**
     * Get absolute url for a field
     *
     * @param  string|null  $field
     *
     * @return mixed|string
     * @throws InvalidUploadFieldException
     */
    public function imageUrl(?string $field = null): string
    {
        $this->uploadFieldName = $this->getUploadFieldName($field);
        $this->uploadFieldOptions = $this->getUploadFieldOptions($this->uploadFieldName);

        // get the model attribute value
        if (Arr::get($this->uploadFieldOptions, 'update_database', true)) {
            $attributeValue = $this->getOriginal($this->uploadFieldName);
        } else {
            $attributeValue = $this->getFileUploadPath($field);
        }

        // check for placeholder defined in option
        $placeholderImage = Arr::get($this->uploadFieldOptions, 'placeholder');

        return (empty($attributeValue) && $placeholderImage)
            ? $placeholderImage
            : $this->getStorageDisk()->url($attributeValue);
    }

    /**
     * Get the upload field name
     *
     * @param  null  $field
     *
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
     * Get all the image and file fields defined on model
     *
     * @return array
     */
    public function getDefinedUploadFields(): array
    {
        $fields = static::$imageFields ?? $this->imagesFields;

        return array_merge($this->getDefinedFileFields(), $fields);
    }

    /**
     * Get all the file fields defined on model
     *
     * @return array
     */
    public function getDefinedFileFields(): array
    {
        return static::$fileFields ?? $this->filesFields;
    }

    /**
     * Get upload field options
     *
     * @param $field
     *
     * @return array
     * @throws InvalidUploadFieldException
     */
    public function getUploadFieldOptions($field = null): array
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
            return is_int($fieldKey) ? [] : Arr::first($imagesFields);
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
     * Check if image filed is defined
     *
     * @param  string  $field
     *
     * @return bool
     */
    public function hasImageField(string $field): bool
    {
        return $this->hasUploadField($field, $this->getDefinedUploadFields());
    }

    /**
     * Check is upload field is defined
     *
     * @param  string  $field
     * @param  array  $definedField
     *
     * @return bool
     */
    private function hasUploadField(string $field, array $definedField): bool
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
     * Get the full path to upload file
     *
     * @param  UploadedFile  $file
     *
     * @return string
     */
    protected function getFileUploadPath(UploadedFile $file): string
    {
        // check if path override is defined for current file
        $pathOverrideMethod = Str::camel(strtolower($this->uploadFieldName) . 'UploadFilePath');

        if (method_exists($this, $pathOverrideMethod)) {
            return $this->getImageUploadPath() . '/' . $this->$pathOverrideMethod($file);
        }

        return $this->getImageUploadPath() . '/' . $file->hashName();
    }

    /**
     * Get image upload path
     *
     * @return string
     */
    protected function getImageUploadPath(): string
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
     * Get storage disk
     *
     * @return Filesystem
     */
    protected function getStorageDisk(): Filesystem
    {
        return Storage::disk($this->getImageUploadDisk());
    }

    /**
     * Get image upload disk
     *
     * @return string
     */
    protected function getImageUploadDisk(): string
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
     * Get html image tag for a field if image present
     *
     * @param  string|null  $field
     * @param  string  $attributes
     *
     * @return string
     */
    public function imageTag(?string $field = null, string $attributes = ''): string
    {
        // if no field found just return empty string
        if (!$this->hasImageField($field) || $this->hasFileField($field)) {
            return '';
        }

        try {
            return '<img src="' . $this->imageUrl($field) . '" ' . $attributes . ' />';
        } catch (Exception $exception) {
        }
        return '';
    }

    /**
     * Check if file filed is defined
     *
     * @param  string  $field
     *
     * @return bool
     */
    public function hasFileField(string $field): bool
    {
        return $this->hasUploadField($field, $this->getDefinedFileFields());
    }

    /**
     * Upload a file
     *
     * @param $file
     * @param  string|null  $field
     *
     * @return $this
     * @throws InvalidUploadFieldException
     */
    public function uploadFile($file, ?string $field = null): self
    {
        return $this->uploadImage($file, $field);
    }

    /**
     * Upload and resize image
     *
     * @param  UploadedFile  $imageFile
     * @param  string|null  $field
     *
     * @return $this
     * @throws InvalidUploadFieldException|\Exception
     */
    public function uploadImage(UploadedFile $imageFile, ?string $field = null): self
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
        if (!empty($currentFile) && $currentFile != $filePath) {
            $this->deleteImage($currentFile);
        }

        return $this;
    }

    /**
     * Validate image file with given rules in option
     *
     * @param $file
     * @param  string  $fieldName
     * @param  array  $imageOptions
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateImage($file, string $fieldName, array $imageOptions): void
    {
        if ($rules = Arr::get($imageOptions, 'rules')) {
            $this->validationFactory()->make(
                [$fieldName => $file],
                [$fieldName => $rules]
            )->validate();
        }
    }

    /**
     * Get a validation factory instance.
     *
     * @return \Illuminate\Contracts\Validation\Factory
     */
    protected function validationFactory(): Factory
    {
        return app(Factory::class);
    }

    /**
     * Process file upload
     *
     * @param  UploadedFile  $file
     *
     * @return string
     * @throws \Exception
     */
    public function handleFileUpload(UploadedFile $file): string
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
     * Trigger user defined before save hook.
     *
     * @param $image
     *
     * @return $this
     * @throws \Exception
     */
    protected function triggerBeforeSaveHook($image): self
    {
        if (isset($this->uploadFieldOptions['before_save'])) {
            $this->triggerHook($this->uploadFieldOptions['before_save'], $image);
        }

        return $this;
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
     * Trigger user defined after save hook.
     *
     * @param $image
     *
     * @return $this
     * @throws \Exception
     */
    protected function triggerAfterSaveHook($image): self
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
     *
     * @return string
     * @throws \Exception
     */
    protected function handleImageUpload($imageFile): string
    {
        // resize the image with given option
        $image = $this->resizeImage($imageFile, $this->uploadFieldOptions);

        // save the uploaded file on disk
        return $this->saveImage($imageFile, $image);
    }

    /**
     * Resize image based on options
     *
     * @param $imageFile
     * @param  array  $imageFieldOptions
     *
     * @return \Intervention\Image\Image
     */
    public function resizeImage($imageFile, array $imageFieldOptions): \Intervention\Image\Image
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
            [$x, $y] = $crop;

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
     * Check if image need resizing from options
     *
     * @param  array  $imageFieldOptions
     *
     * @return bool
     */
    protected function needResizing(array $imageFieldOptions): bool
    {
        return Arr::has($imageFieldOptions, 'width') || Arr::has($imageFieldOptions, 'height');
    }

    /**
     * Get the crop option
     *
     * @param  array  $imageFieldOptions
     *
     * @return array|boolean
     */
    protected function getCropOption(array $imageFieldOptions)
    {
        $crop = Arr::get($imageFieldOptions, 'crop', false);

        // check for crop override
        if (isset($this->cropCoordinates) && count($this->cropCoordinates) == 2) {
            $crop = $this->cropCoordinates;
        }

        return $crop;
    }

    /**
     * Save the image to disk
     *
     * @param  UploadedFile  $imageFile
     * @param $image
     *
     * @return string
     * @throws \Exception
     */
    protected function saveImage(UploadedFile $imageFile, $image): string
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
            (string) $image->encode(null, $imageQuality),
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
     * @param  string  $imagePath
     * @param  string  $imageFieldName
     */
    protected function updateModel(string $imagePath, string $imageFieldName): void
    {
        // check if update_database = false (default: true)
        $imagesFields = $this->getDefinedUploadFields();
        $actualField = Arr::get($imagesFields, $imageFieldName);
        $updateAuthorized = Arr::get($actualField, 'update_database', true);

        // update model (if update_database=true or not set)
        if ($updateAuthorized) {
            $this->attributes[$imageFieldName] = $imagePath;
            $dispatcher = $this->getEventDispatcher();
            self::unsetEventDispatcher();
            $this->save();
            self::setEventDispatcher($dispatcher);
        }
    }

    /**
     * Delete an Image
     *
     * @param  string  $filePath
     */
    public function deleteImage(string $filePath): void
    {
        $this->deleteUploadedFile($filePath);
    }

    /**
     * Delete a file from disk
     *
     * @param  string  $filePath
     */
    private function deleteUploadedFile(string $filePath): void
    {
        if ($this->getStorageDisk()->exists($filePath)) {
            $this->getStorageDisk()->delete($filePath);
        }
    }

    /**
     * Override Crop X and Y coordinates
     *
     * @param  int  $x
     * @param  int  $y
     *
     * @return $this
     */
    public function cropTo(int $x, int $y): self
    {
        $this->cropCoordinates = [$x, $y];
        return $this;
    }

    /**
     * Setter for model image fields
     *
     * @param  array  $fieldsOptions
     *
     * @return $this
     */
    public function setImagesField(array $fieldsOptions): self
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
     * @param  array  $fieldsOptions
     *
     * @return $this
     */
    public function setFilesField(array $fieldsOptions): self
    {
        if (isset(static::$fileFields)) {
            static::$fileFields = array_merge($this->getDefinedUploadFields(), $fieldsOptions);
        } else {
            $this->filesFields = $fieldsOptions;
        }

        return $this;
    }

    /**
     * Delete a file
     *
     * @param  string  $filePath
     */
    public function deleteFile(string $filePath): void
    {
        $this->deleteUploadedFile($filePath);
    }

    /**
     * Disable auto upload
     *
     * @return $this
     */
    public function disableAutoUpload(): self
    {
        $this->disableAutoUpload = true;
        return $this;
    }

    /**
     * Enable auto upload
     *
     * @return $this
     */
    public function enableAutoUpload(): self
    {
        $this->disableAutoUpload = false;
        return $this;
    }

    /**
     * Auto image upload handler
     *
     * @throws InvalidUploadFieldException
     * @throws \Exception
     */
    protected function autoUpload(): void
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
     * Check if auto upload is allowed
     *
     * @return bool
     */
    protected function canAutoUploadImages(): bool
    {
        return property_exists($this, 'autoUploadImages')
            ? $this->autoUploadImages
            : config('imageup.auto_upload_images', false);
    }

    /**
     * Auto delete image handler
     */
    protected function autoDeleteImage(): void
    {
        if (config('imageup.auto_delete_images')) {
            foreach ($this->getDefinedUploadFields() as $field => $options) {
                $field = is_numeric($field) ? $options : $field;
                if (!is_null($this->getOriginal($field))) {
                    $this->deleteImage($this->getOriginal($field));
                }
            }
        }
    }
}
