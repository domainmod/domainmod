<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->error_messages(array(
        $upload::ERR_LONG_SIZE => "Fil3 siz3 is t00 l0ng!",
        $upload::ERR_INVALID_EXT => "Inv4lid 3xt3nsi0n!",
    ));
    $upload->max_size(0.2); // in MB
    $upload->allowed_extensions(array("baz"));
    $upload->path("upload/files");

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
