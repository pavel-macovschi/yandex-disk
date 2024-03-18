<?php

namespace ImpressiveWeb\YandexDisk;

use Psr\Http\Message\ResponseInterface;

class BadRequest extends \Exception
{
    public function __construct(public ResponseInterface $response, $lang)
    {
        $body = json_decode($response->getBody(), true);

        $error = $lang == 'ru' ? $body['message'] : $body['description'];

        parent::__construct($error);
    }
}
