<?php
namespace Auth0\SDK\API\Header;

class ContentType extends Header
{

    /**
     * ContentType constructor.
     *
     * @param string $contentType
     */
    public function __construct($contentType)
    {
        parent::__construct('Content-Type', $contentType);
    }
}
