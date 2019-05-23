<?php

namespace Sitecake\Authentication;

use Symfony\Component\HttpFoundation\Request;

interface AuthenticatorInterface
{
    public function authenticate(Request $request);
}
