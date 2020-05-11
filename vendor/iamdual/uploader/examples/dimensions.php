<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->must_be_image();
    $upload->min_image_dimensions(1024, null); // minimum 1024px width & **any** height is allowed
    $upload->max_image_dimensions(2048, 1000); // maximum 2048px width & 1000px height is allowed
    $upload->max_size(5); // in MB
    $upload->path("upload/files");

    # Dimensions are also can be set with variables using array:
    $upload->min_image_dimensions = array(1024, null);
    $upload->max_image_dimensions = array(2048, 1000);

    if (!$upload->upload()) {
        echo "Upload error: " . $upload->get_error();
    } else {
        echo "Upload successful!";
    }

}
?>

<form enctype="multipart/form-data" action="" method="post">
    Select File: <input type="file" name="file"> <input type="submit" value="Upload">
</form>
