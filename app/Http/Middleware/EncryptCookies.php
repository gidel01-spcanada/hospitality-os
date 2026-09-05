<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncryptCookies;
use Symfony\Component\HttpFoundation\Request;

class EncryptCookies extends BaseEncryptCookies
{
    public function isDisabled($name)
    {
        if (app()->environment('local') && in_array($name, ['XSRF-TOKEN', config('session.cookie')], true)) {
            return true;
        }

        return parent::isDisabled($name);
    }

    protected function decrypt(Request $request)
    {
        foreach ($request->cookies as $key => $cookie) {
            if ($this->isDisabled($key)) {
                continue;
            }

            try {
                $value = $this->decryptCookie($key, $cookie);

                $request->cookies->set($key, $this->validateValue($key, $value));
            } catch (DecryptException|\ArgumentCountError) {
                $request->cookies->set($key, null);
            }
        }

        return $request;
    }
}