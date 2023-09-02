<?php
/*
 * Copyright 2021, Ekin Karadeniz <iamdual@protonmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace iamdual;

class Uploader
{
    const ERR_EMPTY_FILE = 1;
    const ERR_INVALID_EXT = 2;
    const ERR_INVALID_TYPE = 3;
    const ERR_LONG_SIZE = 4;
    const ERR_SMALL_SIZE = 5;
    const ERR_UNKNOWN_ERROR = 6;
    const ERR_NOT_AN_IMAGE = 7;
    const ERR_MAX_DIMENSION = 8;
    const ERR_MIN_DIMENSION = 9;
    const ERR_ASPECT_RATIO = 10;

    /**
     * Error ID
     * @var int
     */
    private $error = null;

    /**
     * The file array
     * @var array
     */
    private $file = null;

    /**
     * Default error messages
     * @var array
     */
    private $error_messages = array(
        self::ERR_EMPTY_FILE => "No file selected.",
        self::ERR_INVALID_EXT => "Invalid file extension.",
        self::ERR_INVALID_TYPE => "Invalid file mime type.",
        self::ERR_LONG_SIZE => "File size is too large.",
        self::ERR_SMALL_SIZE => "File size is too small.",
        self::ERR_UNKNOWN_ERROR => "Unknown error occurred.",
        self::ERR_NOT_AN_IMAGE => "The selected file must be an image.",
        self::ERR_MAX_DIMENSION => "The dimensions of the image is too large.",
        self::ERR_MIN_DIMENSION => "The dimensions of the image is too small.",
        self::ERR_ASPECT_RATIO => "The aspect ratio of the image is not as specified.",
    );

    /**
     * Customized error messages
     * @var array
     */
    public $custom_error_messages = null;

    /**
     * @var array
     */
    public $extensions = null;

    /**
     * @var array
     */
    public $disallowed_extensions = null;

    /**
     * @var array
     */
    public $types = null;

    /**
     * @var array
     */
    public $disallowed_types = null;

    /**
     * @var int
     */
    public $max_size = null;

    /**
     * @var int
     */
    public $min_size = null;

    /**
     * @var string
     */
    public $path = null;

    /**
     * @var string
     */
    public $name = null;

    /**
     * @var boolean
     */
    public $auto_extension = true;

    /**
     * @var boolean
     */
    public $must_be_image = false;

    /**
     * @var array
     */
    public $max_image_dimensions = null;

    /**
     * @var array
     */
    public $min_image_dimensions = null;

    /**
     * @var array
     */
    public $image_aspect_ratios = null;

    /**
     * @var boolean
     */
    public $encrypt_name = false;

    /**
     * @var boolean
     */
    public $override = false;

    /**
     * Set the file array ($_FILES or equivalent of this)
     * @param array $file
     */
    function __construct($file)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * Allowed file extensions (example: png, gif, jpg)
     * @param array $extensions
     * @return $this
     */
    public function allowed_extensions($extensions)
    {
        $this->extensions = (is_array($extensions)) ? $extensions : null;
        return $this;
    }

    /**
     * Disallowed file extensions (example: html, php, dmg)
     * @param array $extensions
     * @return $this
     */
    public function disallowed_extensions($extensions)
    {
        $this->disallowed_extensions = (is_array($extensions)) ? $extensions : null;
        return $this;
    }

    /**
     * Allowed mime types (example: image/png, image/jpeg)
     * @param array $types
     * @return $this
     */
    public function allowed_types($types)
    {
        $this->types = (is_array($types)) ? $types : null;
        return $this;
    }

    /**
     * Disallowed mime types
     * @param array $types
     * @return $this
     */
    public function disallowed_types($types)
    {
        $this->disallowed_types = (is_array($types)) ? $types : null;
        return $this;
    }

    /**
     * Maximum file size in MB
     * @param int $size
     * @return $this
     */
    public function max_size($size)
    {
        $this->max_size = (is_numeric($size)) ? $size : null;
        return $this;
    }

    /**
     * Minimum file size in MB
     * @param int $size
     * @return $this
     */
    public function min_size($size)
    {
        $this->min_size = (is_numeric($size)) ? $size : null;
        return $this;
    }

    /**
     * Maximum dimensions of the image
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function max_dimensions($width, $height)
    {
        $this->max_image_dimensions = array($width, $height);
        return $this;
    }

    /**
     * Minimum dimensions of the image
     * @param int $width
     * @param int $height
     * @return $this
     */
    public function min_dimensions($width, $height)
    {
        $this->min_image_dimensions = array($width, $height);
        return $this;
    }

    /**
     * @deprecated
     * DEPRECATED: Use max_dimensions()
     */
    public function max_image_dimensions($width, $height)
    {
        return $this->max_dimensions($width, $height);
    }

    /**
     * @deprecated
     * DEPRECATED: Use min_dimensions()
     */
    public function min_image_dimensions($width, $height)
    {
        return $this->min_dimensions($width, $height);
    }

    /**
     * Image aspect ratios has to be
     * @param array $aspect_ratios
     * @return $this
     */
    public function aspect_ratios($aspect_ratios)
    {
        $this->image_aspect_ratios = $aspect_ratios;
        return $this;
    }

    /**
     * Override (write over) the file with the same name
     * @return $this
     */
    public function override()
    {
        $this->override = true;
        return $this;
    }

    /**
     * The path where files will be uploaded
     * @param string $path
     * @return $this
     */
    public function path($path)
    {
        $this->path = rtrim($path, "/");
        return $this;
    }

    /**
     * Rename the uploaded file (example: foo)
     * @param string $name
     * @param boolean $auto_extension
     * @return $this
     */
    public function name($name, $auto_extension = true)
    {
        $this->name = $name;
        $this->auto_extension = $auto_extension;
        return $this;
    }

    /**
     * Encrypt file name to hide the original name
     * @return $this
     */
    public function encrypt_name()
    {
        $this->encrypt_name = true;
        return $this;
    }

    /**
     * Verify that the file is an image
     * @return $this
     */
    public function must_be_image()
    {
        $this->must_be_image = true;
        return $this;
    }

    /**
     * Set custom error messages
     * @param array $errors
     * @return $this
     */
    public function error_messages($errors)
    {
        $this->custom_error_messages = (is_array($errors)) ? $errors : null;
        return $this;
    }

    /**
     * Get error message
     * @param string $error_id
     * @return string
     */
    public function get_error_message($error_id)
    {
        if ($this->custom_error_messages !== null && isset($this->custom_error_messages[$error_id])) {
            return $this->custom_error_messages[$error_id];
        }
        return isset($this->error_messages[$error_id]) ? $this->error_messages[$error_id] : null;
    }

    /**
     * Get file name
     * @return string
     */
    public function get_name()
    {
        if ($this->name === null) {
            $this->name = $this->file["name"];
            $this->auto_extension = false;
        }

        if ($this->encrypt_name) {
            $this->name = self::hashed($this->name) . self::get_ext($this->file["name"], true);
            $this->encrypt_name = false;
            $this->auto_extension = false;
        }

        if ($this->auto_extension) {
            return $this->name . self::get_ext($this->file["name"], true);
        } else {
            return $this->name;
        }
    }

    /**
     * Get the name of the temporary file
     * @return string
     */
    public function get_tmp_name()
    {
        return isset($this->file["tmp_name"]) ? $this->file["tmp_name"] : null;
    }

    /**
     * Get the mime type of the file
     * @return string
     */
    public function get_type()
    {
        return isset($this->file["type"]) ? $this->file["type"] : null;
    }

    /**
     * Get the size of the file
     * @return int
     */
    public function get_size()
    {
        return isset($this->file["size"]) ? $this->file["size"] : null;
    }

    /**
     * Get the data URL of the temporary file
     * @return string
     */
    public function get_data_url()
    {
        return self::data_url($this->get_tmp_name());
    }

    /**
     * Check the file can be uploaded
     * @return boolean
     */
    public function check()
    {
        if (!is_array($this->file) || $this->error !== null) {
            return false;
        }

        // Standard validations
        if (!isset($this->file["name"]) || !isset($this->file["tmp_name"]) || !isset($this->file["type"]) || !isset($this->file["size"]) || !isset($this->file["error"])) {
            $this->error = self::ERR_EMPTY_FILE;
        } else if (strlen($this->file["name"]) == 0 || strlen($this->file["tmp_name"]) == 0 || strlen($this->file["type"]) == 0 || $this->file["size"] == 0) {
            $this->error = self::ERR_EMPTY_FILE;
        } else if ($this->extensions !== null && !in_array(self::get_ext($this->file["name"]), $this->extensions)) {
            $this->error = self::ERR_INVALID_EXT;
        } else if ($this->disallowed_extensions !== null && in_array(self::get_ext($this->file["name"]), $this->disallowed_extensions)) {
            $this->error = self::ERR_INVALID_EXT;
        } else if ($this->types !== null && !in_array($this->file["type"], $this->types)) {
            $this->error = self::ERR_INVALID_TYPE;
        } else if ($this->disallowed_types !== null && in_array($this->file["type"], $this->disallowed_types)) {
            $this->error = self::ERR_INVALID_TYPE;
        } else if ($this->max_size !== null && $this->file["size"] > self::mb_to_byte($this->max_size)) {
            $this->error = self::ERR_LONG_SIZE;
        } else if ($this->min_size !== null && $this->file["size"] < self::mb_to_byte($this->min_size)) {
            $this->error = self::ERR_SMALL_SIZE;
        } else if ($this->file["error"] == 1 || $this->file["error"] == 2) {
            $this->error = self::ERR_LONG_SIZE;
        } else if ($this->file["error"] == 4) {
            $this->error = self::ERR_EMPTY_FILE;
        } else if ($this->file["error"] > 0) {
            $this->error = self::ERR_UNKNOWN_ERROR;
        }

        if ($this->error !== null) {
            return false;
        }

        // Image validations
        if ($this->max_image_dimensions !== null || $this->min_image_dimensions !== null || $this->image_aspect_ratios) {
            $image_dimensions = getimagesize($this->file["tmp_name"]);
            if (!$image_dimensions) {
                $this->error = self::ERR_NOT_AN_IMAGE;
                return false;
            }
            if ($this->max_image_dimensions !== null) {
                for ($i = 0; $i <= 1; $i++) {
                    if (isset($this->max_image_dimensions[$i]) && is_numeric($this->max_image_dimensions[$i]) && $image_dimensions[$i] > $this->max_image_dimensions[$i]) {
                        $this->error = self::ERR_MAX_DIMENSION;
                        return false;
                    }
                }
            }
            if ($this->min_image_dimensions !== null) {
                for ($i = 0; $i <= 1; $i++) {
                    if (isset($this->min_image_dimensions[$i]) && is_numeric($this->min_image_dimensions[$i]) && $image_dimensions[$i] < $this->min_image_dimensions[$i]) {
                        $this->error = self::ERR_MIN_DIMENSION;
                        return false;
                    }
                }
            }
            if ($this->image_aspect_ratios !== null) {
                foreach ($this->image_aspect_ratios as $aspect_ratio) {
                    if (self::validate_aspect_ratio($aspect_ratio, $image_dimensions[0], $image_dimensions[1])) {
                        if ($this->error === self::ERR_ASPECT_RATIO) {
                            $this->error = null;
                        }
                        break; // Validation completed.
                    } else {
                        $this->error = self::ERR_ASPECT_RATIO;
                    }
                }
            }
        } else if ($this->must_be_image) {
            // If the file must be an image and getimagesize() didn't check the file, we need to use exif_imagetype instead of getimagesize for the performance.
            if (!exif_imagetype($this->file["tmp_name"])) {
                $this->error = self::ERR_NOT_AN_IMAGE;
                return false;
            }
        }

        return $this->error === null;
    }

    /**
     * Get error if exists
     * @param boolean $with_message (optional)
     * @return string
     */
    public function get_error($with_message = true)
    {
        return $with_message ? $this->get_error_message($this->error) : $this->error;
    }

    /**
     * Upload the file.
     * @param boolean $copy_file (optional)
     * @return boolean
     */
    public function upload($copy_file = false)
    {
        if ($this->check()) {
            $upload_dir = $this->get_path(null, false);
            if (!is_dir($upload_dir)) {
                @mkdir($upload_dir, 0777, true);
            }
            $filepath = $this->get_path();
            if ($this->override === false && file_exists($filepath)) {
                $number = 2;
                $filename = pathinfo($filepath, PATHINFO_FILENAME);
                do {
                    $this->name($filename . (($number) ? "_{$number}" : ""), true);
                    $number++;
                } while (file_exists($this->get_path()));
            }
            $upload_function = $copy_file ? "copy" : "move_uploaded_file";
            $upload_function($this->file["tmp_name"], $this->get_path());
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get the full path
     * @param string $filename (optional)
     * @param bool $include_filename (optional)
     * @return string
     */
    public function get_path($filename = null, $include_filename = true)
    {
        $path = "";
        if ($this->path !== null) {
            $path = $this->path . "/";
        }
        if (!$include_filename) {
            return $path;
        }

        if ($filename === null) {
            $filename = $this->get_name();
        }
        return $path . $filename;
    }

    /**
     * Get extension by filename
     * @param string $filename
     * @param boolean $with_dot
     * @return string
     */
    public static function get_ext($filename, $with_dot = false)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($with_dot && $extension) {
            return "." . $extension;
        }
        return $extension;
    }

    /**
     * Validate aspect ratio
     * @param mixed $aspect_ratio
     * @param int $width
     * @param int $height
     * @return bool
     */
    public static function validate_aspect_ratio($aspect_ratio, $width, $height)
    {
        if (!is_numeric($aspect_ratio)) {
            if (is_string($aspect_ratio)) {
                $aspect_ratio_pieces = explode(":", $aspect_ratio);
            } else if (is_array($aspect_ratio)) {
                $aspect_ratio_pieces = $aspect_ratio;
            }
            if (empty($aspect_ratio_pieces[0]) || empty($aspect_ratio_pieces[1])) {
                return false;
            }
            $aspect_ratio = (int)$aspect_ratio_pieces[0] / (int)$aspect_ratio_pieces[1];
        }

        return ($width / $height) === $aspect_ratio;
    }

    /**
     * Calculate the bytes
     * @param int $filesize
     * @return int
     */
    public static function mb_to_byte($filesize)
    {
        return $filesize * 1048576; // equivalent of "pow(1024, 2)"
    }

    /**
     * Create multiple file array
     * @param array $file_array
     * @return array
     */
    public static function multiple_file_array($file_array)
    {
        $files = array();
        foreach ($file_array as $files_key => $files_array) {
            foreach ($files_array as $i => $val) {
                $files[$i][$files_key] = $val;
            }
        }
        return $files;
    }

    /**
     * Create file array from base64 encoded file
     * @param string $base64
     * @param array $mime_map (optional)
     * @return array
     */
    public static function from_base64($base64, $mime_map = [])
    {
        $encoded = explode(";base64,", $base64, 2);
        if (isset($encoded[1])) {
            $base64 = $encoded[1];
        }
        return self::create_temp_file(base64_decode($base64), $mime_map);
    }

    /**
     * Create file array from raw input
     * @param array $mime_map (optional)
     * @return array
     */
    public static function from_raw_input($mime_map = [])
    {
        return self::create_temp_file(file_get_contents("php://input"), $mime_map);
    }

    /**
     * Create a temporary file from source
     * @param string $source
     * @param array $mime_map (optional)
     * @return array
     */
    public static function create_temp_file($source, $mime_map = [])
    {
        $temp = tmpfile();
        fwrite($temp, $source);
        $meta = stream_get_meta_data($temp);
        $mime = mime_content_type($meta["uri"]);

        if (isset($mime_map[$mime])) {
            $ext = $mime_map[$mime];
        } else {
            $arr = explode("/", $mime);
            $ext = end($arr);
        }

        register_shutdown_function(function () use ($temp) {
            fclose($temp);
        });

        return array(
            "name" => self::hashed($meta["uri"]) . "." . $ext,
            "size" => filesize($meta["uri"]),
            "type" => $mime,
            "tmp_name" => $meta["uri"],
            "error" => 0
        );
    }

    /**
     * Hashed text
     * @param string $filename
     * @return string
     */
    public static function hashed($filename)
    {
        return sha1($filename . "-" . rand(10000, 99999) . "-" . time());
    }

    /**
     * Get the data URL by the file path
     * https://developer.mozilla.org/en-US/docs/Web/HTTP/Basics_of_HTTP/Data_URIs
     * @param string $filepath
     * @return string
     */
    public static function data_url($filepath)
    {
        if (file_exists($filepath)) {
            $mime = mime_content_type($filepath);
            $source = file_get_contents($filepath);
            $encoded = base64_encode($source);
            return 'data:' . $mime . ';base64,' . $encoded;
        }
        return null;
    }
}
