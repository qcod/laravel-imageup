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
