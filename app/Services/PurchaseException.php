<?php

namespace App\Services;

/** Error de negocio en una compra; el mensaje se muestra tal cual al usuario. */
class PurchaseException extends \RuntimeException
{
    /** @param string|null $field campo del formulario al que corresponde el error */
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
