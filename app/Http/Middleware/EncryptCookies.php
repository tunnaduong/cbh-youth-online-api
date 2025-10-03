<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

/**
 * Encrypt cookies middleware.
 *
 * This middleware is responsible for encrypting cookies before they are sent to the
 * browser and decrypting them when they are received from the browser.
 */
class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];
}
