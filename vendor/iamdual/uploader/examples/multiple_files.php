<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_FILES["file"])) {

    $files = Uploader::multiple_file_array($_FILES["file"]);

    foreach ($files as $file) {
        $upload = new Uploader($file);
        $upload->allowed_extensions(array("png", "jpg", "jpeg", "gif"));
        $upload->max_size(5); // in MB
        $upload->path("upload/files");
        $upload->encrypt_name();

        if (!$upload->upload()) {
            echo $upload->get_name() . ": Upload error: " . $upload->get_error() . "<br />";
        } else {
            echo $upload->get_name() . ": Upload successful! <br />";
        }
    }

}
?>

<form enctype="multipart/form-data" action="" method="post">
    Select Multiple Files: <input type="file" name="file[]" multiple> <input type="submit" value="Upload">
</form>
