<?php

namespace Sitecake\Authentication\Identifier;

use Sitecake\Authentication\CredentialFileAwareTrait;
use Sitecake\Authentication\CredentialManager;
use Sitecake\Authentication\IdentifierInterface;

class CredentialFileIdentifier implements IdentifierInterface
{
    /**
     * Credential manager instance
     * @var CredentialManager
     */
    protected $credentialManager;

    /**
     * CredentialFileIdentifier constructor.
     *
     * @param CredentialManager $credentialManager
     */
    public function __construct(CredentialManager $credentialManager)
    {
        $this->credentialManager = $credentialManager;
    }

    /**
     * {@inheritdoc}
     */
    public function identify(array $credentials)
    {
        if (isset($credentials['password'])) {
            $storedCredentials = $this->credentialManager->getCredentials();
            return $storedCredentials['password'] === $credentials['password'];
        }

        return false;
    }
}
