<?php

namespace Sitecake\Authentication;

use Symfony\Component\HttpFoundation\Request;

class Authenticate
{
    /**
     * @var AuthenticatorInterface[]
     */
    protected $authenticators = [];

    public function registerAuthenticator(AuthenticatorInterface $auth)
    {
        $this->authenticators[] = $auth;
    }

    public function authenticate(Request $request)
    {
        foreach ($this->authenticators as $auth) {
            if ($auth->authenticate($request)) {
                return true;
            }
        }

        return false;
    }
}
