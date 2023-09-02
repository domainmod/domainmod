<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $upload = new Uploader($_FILES["file"]);
    $upload->allowed_extensions(array("png", "jpg", "jpeg", "gif"));
    $upload->max_size(5); // in MB

    if (!$upload->check()) {
        echo "An error occurred: " . $upload->get_error();
    } else {
        echo 'Base64 encoded data URL:<br /><textarea cols=80 rows=10>'.$upload->get_data_url().'</textarea>';
    }

}
?>

<form enctype="multipart/form-data" action="" method="post">
    Select File: <input type="file" name="file"> <input type="submit" value="Upload">
</form>
