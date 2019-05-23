<?php

namespace Sitecake\Authentication;

interface IdentifierInterface
{
    /**
     * Returns true if passed credentials can be identified and false if not
     *
     * @param array $credentials Authentication credentials
     *
     * @return bool
     */
    public function identify(array $credentials);
}
