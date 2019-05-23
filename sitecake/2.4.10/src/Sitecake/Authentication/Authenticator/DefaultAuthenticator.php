<?php

namespace Sitecake\Authentication\Authenticator;

use Sitecake\Authentication\AbstractAuthenticator;
use Symfony\Component\HttpFoundation\Request;

class DefaultAuthenticator extends AbstractAuthenticator
{
    /**
     * Default configuration
     *
     * @var array
     */
    protected $defaultConfig = [
        'paramName' => 'credentials'
    ];

    public function authenticate(Request $request)
    {
        return $this->identifier->identify([
            'password' => $request->query->get($this->getConfig('paramName'))
        ]);
    }
}
