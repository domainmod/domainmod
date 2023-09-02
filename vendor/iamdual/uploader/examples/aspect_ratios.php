<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->must_be_image();
    $upload->aspect_ratios(array("16:9", "1:1"));
    $upload->max_size(5); // in MB
    $upload->path("upload/files");

    if (!$upload->upload()) {
        echo "Upload error: " . $upload->get_error();
    } else {
        echo "Upload successful!";
    }

}
?>

<form enctype="multipart/form-data" action="" method="post">
    Select a file with aspect ratio 16:9 or 1:1 <input type="file" name="file"> <input type="submit" value="Upload">
</form>
