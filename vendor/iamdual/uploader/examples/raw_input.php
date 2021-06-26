<?php
require __DIR__ . '/../src/Uploader.php';

use \iamdual\Uploader;

if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] == "PUT") {

    $upload = new Uploader(Uploader::from_raw_input());
    $upload->max_size(5); // in MB
    $upload->path("upload/files");

    // While uploading file from raw input data, you need to change upload function
    // to "copy", otherwise file can not be uploaded!

    if (! $upload->upload(true)) {
        echo "Upload error: " . $upload->get_error() . PHP_EOL;
    } else {
        echo "Upload successful: " . $upload->get_name() . PHP_EOL;
    }

} else {

    echo <<<HTML

Sending files with raw input, allows you sending files from command line:
<br/>
<pre>
curl --upload-file ./hello.txt http://upload.local/examples/raw_input.php
</pre>

HTML;

}
