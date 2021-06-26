<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->allowed_extensions(array("png", "jpg", "jpeg", "gif"));
    $upload->allowed_types(array("image/png", "image/jpeg"));
    $upload->max_size(5); // in MB
    $upload->min_size(0); // in MB
    $upload->path("upload/files");

    if (!$upload->check()) {
        echo "Upload error: " . $upload->get_error();
    } else {
        echo 'Base64 encoded data URL: <textarea>'.$upload->get_data_url().'</textarea>';
    }

}
?>

<form enctype="multipart/form-data" action="" method="post">
    Select File: <input type="file" name="file"> <input type="submit" value="Upload">
</form>
