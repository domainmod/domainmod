<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->extensions = array("png", "jpg", "jpeg", "gif");
    $upload->name       = "foo";
    $upload->max_size   = $upload->mb_to_byte(5);
    $upload->override   = false;
    $upload->path       = "upload/files";

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
