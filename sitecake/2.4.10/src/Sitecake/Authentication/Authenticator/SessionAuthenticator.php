<?php

namespace Sitecake\Authentication\Authenticator;

use Sitecake\Authentication\AbstractAuthenticator;
use Symfony\Component\HttpFoundation\Request;

class SessionAuthenticator extends AbstractAuthenticator
{
    protected $defaultConfig = [
        'sessionKey' => 'loggedin'
    ];

    /**
     * {@inheritdoc}
     */
    public function authenticate(Request $request)
    {
        if (($session = $request->getSession()) !== null) {
            return $session->get($this->getConfig('sessionKey'));
        }

        return false;
    }
}
