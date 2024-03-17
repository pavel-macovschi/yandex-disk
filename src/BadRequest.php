<?php

namespace ImpressiveWeb\YandexDisk;

use Psr\Http\Message\ResponseInterface;

class BadRequest extends \Exception
{
    public function __construct(public ResponseInterface $response)
    {
        $body = json_decode($response->getBody(), true);

        parent::__construct($body['description']);
    }
}
