<?php

namespace ImpressiveWeb\YandexDisk;

use Psr\Http\Message\ResponseInterface;

class BadRequest extends \Exception
{
    public ?string $yandexCode = null;

    public function __construct(public ResponseInterface $response)
    {
        $body = json_decode($response->getBody(), true);
        //          "message" => "Ошибка проверки поля "path": Это поле является обязательным."
        //          "description" => "Error validating field "path": This field is required."
        //          "error" => "FieldValidationError"

        if (null !== $body) {
            if (isset($body['description'])) {
                $this->yandexCode = $body['description'];
            }
            parent::__construct($body['description']);
        }
    }
}
