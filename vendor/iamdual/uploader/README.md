### Uploader 🚀
Safe, simple and useful file upload class for PHP 5.4+

### Installing
```
composer require iamdual/uploader
```

### Examples
Basic:
```php
use iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->allowed_extensions(array("png", "jpg", "jpeg", "gif"));
    $upload->max_size(5); // in MB
    $upload->path("upload/files");
    $upload->name("foo");
    
    if (! $upload->upload()) {
        echo "Upload error: " . $upload->get_error();
    } else {
        echo "Upload successful!";
    }
}
```

Inline using:
```php
use iamdual\Uploader;

if (isset($_FILES["file"])) {
    $upload = (new Uploader($_FILES["file"]))->max_size(20)->path("upload/files")->encrypt_name();
    
    if (! $upload->upload()) {
        echo "Upload error: " . $upload->get_error();
    } else {
        echo "Upload successful!";
    }
}
```

More examples in the "[examples](/examples)" directory.

### Methods
| Name | Description |
|---|---|
| `allowed_extensions(array $extensions)` | Allowed file extensions (example: png, gif, jpg) |
| `disallowed_extensions(array $extensions)` | Disallowed file extensions (example: html, php, dmg) |
| `allowed_types(array $types)` | Allowed mime types (example: image/png, image/jpeg) |
| `disallowed_types(array $types)` | Disallowed mime types |
| `max_size(int $size)` | Maximum file size (as MB) |
| `min_size(int $size)` | Minimum file size (as MB) |
| `override()` | Override the file with the same name |
| `path(string $path)` | Set the path where files will be uploaded |
| `name(string $name)` | Rename the uploaded file (example: foo) |
| `encrypt_name()` | Encrypt file name to hide the original name |
| `must_be_image()` | Check the file is image |
| `image_max_dimensions(int $width, int $height)` | Maximum image dimensions |
| `image_min_dimensions(int $width, int $height)` | Minimum image dimensions |
| `error_messages(array $errors)` | Custom error messages |

| Name | Description | Return |
|---|---|---|
| `upload()` | Upload the file and return output of the check() | boolean |
| `check()` | Check the file can be uploaded | boolean |
| `get_name()` | Get the uploaded file name | string |
| `get_tmp_name()` | Get the temporary file path | string |
| `get_data_url()` | Get the file as base64 encoded data URL | string |
| `get_path()` | Get the path of the file | string |
| `get_error()` | Get error message if an error occurred | string |

### Notes
* `exif` and `fileinfo` extensions must be enabled.
* [`exif_imagetype()`](https://php.net/manual/en/function.exif-imagetype.php) and [`getimagesize()`](https://php.net/manual/en/function.getimagesize.php) must be allowed.

### Contributes
Please send pull request or open an issue if you have the feature you want.
