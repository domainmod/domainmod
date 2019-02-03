<?php
namespace Auth0\SDK\API\Helpers;

use \Auth0\SDK\API\Header\Header;
use \Auth0\SDK\Exception\CoreException;
use \GuzzleHttp\Client;
use \GuzzleHttp\Exception\RequestException;

/**
 * Class RequestBuilder
 *
 * @package Auth0\SDK\API\Helpers
 */
class RequestBuilder
{

    /**
     * Domain for the request.
     *
     * @var string
     */
    protected $domain;

    /**
     * Base API path for the request.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Path to request.
     *
     * @var array
     */
    protected $path = [];

    /**
     * HTTP method to use for the request.
     *
     * @var array
     */
    protected $method = [];

    /**
     * Headers to include for the request.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * URL parameters for the request.
     *
     * @var array
     */
    protected $params = [];

    /**
     * Form parameters to send with the request.
     *
     * @var array
     */
    protected $form_params = [];

    /**
     * Files to send with a multipart request.
     *
     * @var array
     */
    protected $files = [];

    /**
     * Guzzle HTTP Client options.
     *
     * @var array
     *
     * @see http://docs.guzzlephp.org/en/stable/request-options.html
     */
    protected $guzzleOptions = [];

    /**
     * Request body.
     *
     * @var string
     */
    protected $body;

    /**
     * Valid return types for the call() method.
     *
     * @var array
     */
    protected $returnTypes = [ 'body', 'headers', 'object' ];

    /**
     * Default return type.
     *
     * @var string
     */
    protected $returnType;

    /**
     * RequestBuilder constructor.
     *
     * @param array $config Configuration array passed to \Auth0\SDK\API\Management constructor.
     *
     * @throws CoreException If a returnType is set that is invalid.
     */
    public function __construct(array $config)
    {
        $this->method        = $config['method'];
        $this->domain        = $config['domain'];
        $this->basePath      = isset($config['basePath']) ? $config['basePath'] : '';
        $this->guzzleOptions = isset($config['guzzleOptions']) ? $config['guzzleOptions'] : [];
        $this->headers       = isset($config['headers']) ? $config['headers'] : [];

        if (array_key_exists('path', $config)) {
            $this->path = $config['path'];
        }

        $this->setReturnType( isset( $config['returnType'] ) ? $config['returnType'] : null );
    }

    /**
     * Magic method to overload method calls to paths.
     *
     * @param string     $name      Method invoked.
     * @param array|null $arguments Arguments to add to the path.
     *
     * @return RequestBuilder
     */
    public function __call($name, $arguments)
    {
        $argument = null;

        if (count($arguments) > 0) {
            $argument = $arguments[0];
        }

        $this->addPath($name, $argument);

        return $this;
    }

    /**
     * Add a path and an optional argument to this request.
     *
     * @param string      $name     Path to add.
     * @param string|null $argument Optional argument to add.
     *
     * @return RequestBuilder
     */
    public function addPath($name, $argument = null)
    {
        $this->path[] = $name;
        if ($argument !== null) {
            $this->path[] = $argument;
        }

        return $this;
    }

    /**
     * Add a path variable.
     *
     * @param string $variable Path variable to add.
     *
     * @return RequestBuilder
     */
    public function addPathVariable($variable)
    {
        $this->path[] = $variable;
        return $this;
    }

    /**
     * Get the path and URL parameters of this request.
     *
     * @return string
     */
    public function getUrl()
    {
        return trim(implode('/', $this->path), '/').$this->getParams();
    }

    /**
     * Output a URL
     *
     * @return string
     */
    public function getParams()
    {
        if (empty($this->params)) {
            return '';
        }

        $params = array_map(function ($key, $value) {
            return "$key=$value";
        }, array_keys($this->params), $this->params);

        return '?'.implode('&', $params);
    }

    /**
     *
     * @return RequestBuilder
     */
    public function dump()
    {
        echo '<pre>';
        echo "METHOD: {$this->method}\n";
        echo "URL: {$this->getUrl()}\n";

        echo "HEADERS:\n\t";
        echo implode("\n\t", array_map(function ($k, $v) {
            return "$k: $v";
        }, array_keys($this->headers), $this->headers));
        echo "\n";

        echo "BODY: {$this->body}\n";

        echo '</pre>';

        return $this;
    }

    /**
     *
     * @param  string $field
     * @param  string $file_path
     * @return RequestBuilder
     */
    public function addFile($field, $file_path)
    {
        $this->files[$field] = $file_path;
        return $this;
    }

    /**
     *
     * @param  string $key
     * @param  string $value
     * @return RequestBuilder
     */
    public function addFormParam($key, $value)
    {
        $this->form_params[$key] = $value;
        return $this;
    }

    /**
     *
     * @return array
     */
    public function getGuzzleOptions()
    {
        return array_merge(
            ['base_uri' => $this->domain.$this->basePath],
            $this->guzzleOptions
        );
    }

    /**
     *
     * @return mixed|string
     *
     * @throws RequestException
     */
    public function call()
    {
        // Create a new Guzzle client.
        $client = new Client($this->getGuzzleOptions());

        try {
            $data = [
                'headers' => $this->headers,
                'body' => $this->body,
            ];

            if (! empty($this->files)) {
                $data['multipart'] = $this->buildMultiPart();
            } else if (! empty($this->form_params)) {
                $data['form_params'] = $this->form_params;
            }

            $response = $client->request($this->method, $this->getUrl(), $data);

            switch ($this->returnType) {
                case 'headers':
                return $response->getHeaders();

                case 'object':
                return $response;

                case 'body':
                default:
                    $body = (string) $response->getBody();
                    if (strpos($response->getHeaderLine('content-type'), 'json') !== false) {
                        return json_decode($body, true);
                    }
                return $body;
            }
        } catch (RequestException $e) {
            throw $e;
        }
    }

    /**
     *
     * @param  $headers
     * @return RequestBuilder
     */
    public function withHeaders($headers)
    {
        foreach ($headers as $header) {
            $this->withHeader($header);
        }

        return $this;
    }

    /**
     *
     * @param  Header|string $header
     * @param  null|string   $value
     * @return $this
     */
    public function withHeader($header, $value = null)
    {
        if ($header instanceof Header) {
            $this->headers[$header->getHeader()] = $header->getValue();
        } else {
            $this->headers[$header] = $value;
        }

        return $this;
    }

    /**
     *
     * @param  string $body
     * @return $this
     */
    public function withBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     *
     * @param  string $key
     * @param  mixed  $value
     * @return $this
     */
    public function withParam($key, $value)
    {
        $value = ($value === true ? 'true' : $value);
        $value = ($value === false ? 'false' : $value);

        $this->params[$key] = $value;
        return $this;
    }

    /**
     *
     * @param  array $params
     * @return RequestBuilder
     */
    public function withParams($params)
    {
        foreach ($params as $param) {
            $this->withParam($param['key'], $param['value']);
        }

        return $this;
    }

    /**
     * Add URL parameters using $key => $value array.
     *
     * @param array $params - URL parameters to add.
     *
     * @return RequestBuilder
     */
    public function withDictParams($params)
    {
        foreach ($params as $key => $value) {
            $this->withParam($key, $value);
        }

        return $this;
    }

    /**
     *
     * @param  $type
     * @return $this
     * @throws CoreException When the type passed is not valid.
     */
    public function setReturnType($type)
    {
        if (empty( $type )) {
            $type = 'body';
        }

        if (! in_array($type, $this->returnTypes)) {
            throw new CoreException('Invalid returnType');
        }

        $this->returnType = $type;
        return $this;
    }

    /**
     *
     * @return array
     */
    private function buildMultiPart()
    {
        $multipart = [];

        foreach ($this->files as $field => $file) {
            $multipart[] = [
                'name' => $field,
                'contents' => fopen($file, 'r')
            ];
        }

        foreach ($this->form_params as $param => $value) {
            $multipart[] = [
                'name' => $param,
                'contents' => $value
            ];
        }

        return $multipart;
    }
}
