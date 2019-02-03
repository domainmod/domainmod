<?php

namespace Auth0\SDK\Helpers\Cache;

class FileSystemCacheHandler implements CacheHandler
{

    /**
     *
     * @var string
     */
    protected $tmp_dir;

    /**
     * FileSystemCacheHandler constructor.
     *
     * @param string $temp_directory_prefix
     */
    public function __construct($temp_directory_prefix = 'auth0-php')
    {
        $this->tmp_dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.$temp_directory_prefix.DIRECTORY_SEPARATOR;
        if (! file_exists($this->tmp_dir)) {
            mkdir($this->tmp_dir);
        }
    }

    /**
     *
     * @param  string $key
     * @return mixed|null
     */
    public function get($key)
    {
        $key = md5($key);

        if (! file_exists($this->tmp_dir.$key)) {
            return null;
        }

        $file = fopen($this->tmp_dir.$key, 'r');
        flock($file, LOCK_EX);

        $data = fgets($file);

        flock($file, LOCK_UN);
        fclose($file);

        return unserialize(base64_decode($data));
    }

    /**
     *
     * @param string $key
     */
    public function delete($key)
    {
        $key = md5($key);
        $this->set($key, null);
        @unlink($this->tmp_dir.$key);
    }

    /**
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set($key, $value)
    {
        $key   = md5($key);
        $value = base64_encode(serialize($value));

        $file = fopen($this->tmp_dir.$key, 'w+');
        flock($file, LOCK_EX);

        fwrite($file, $value, strlen($value));

        flock($file, LOCK_UN);
        fclose($file);
    }
}
