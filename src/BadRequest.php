<?php

namespace ImpressiveWeb\YandexDisk;

class BadRequest extends \Exception
{
    public function __construct($message)
    {
        parent::__construct($message);
    }
}
