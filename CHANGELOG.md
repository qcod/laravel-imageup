# Changelog

All notable changes to `laravel-imageup` will be documented in this file

## 1.1.0 - 2020-09-24
- Laravel 8 support

## 1.0.9 - 2020-03-22
- Laravel 7 support

## 1.0.7 - 2019-09-06
- Laravel 6 support

## 1.0.6 - 2019-09-06
- Laravel 5.8 support

## 1.0.5 - 2018-11-03
- Added support to upload non image file also
- Can disable/enable auto upload dynamiclly by calling `$model->disableAutoUpload()` and enable it back `$model->enableAutoUpload()` 
- Improved tests & Code cleanup

## 1.0.4 - 2018-11-01
- Added support to customize filename and relative path dynamically

## 1.0.3 - 2018-10-21
- Added `before_save` and `after_save` hooks

## 1.0.2 - 2018-10-02
- Added `before_save` and `after_save` hooks

## 1.0.1 - 2018-09-29
- s3 storage bug #5 and #6 fixed to get imageUrl and delete old images
