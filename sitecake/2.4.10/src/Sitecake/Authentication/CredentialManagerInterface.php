<?php

namespace Sitecake\Authentication;

interface CredentialManagerInterface
{
    /**
     * Sets/updates passed credentials
     *
     * @param array $credentials Credentials to store
     *
     * @return bool
     */
    public function saveCredentials(array $credentials);
}
