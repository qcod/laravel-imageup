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
     * file_input field on option is present upon model update and create.
     * Can be overridden in individual field options
     */
    'auto_upload_images' => true,

    /**
     * It will auto delete images once a record is deleted from the database
     */
    'auto_delete_images' => true,

    /**
     * Set an image quality
     */
    'resize_image_quality' => 80
];
