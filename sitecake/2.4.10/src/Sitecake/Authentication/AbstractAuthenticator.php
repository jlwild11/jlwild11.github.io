<?php

namespace Sitecake\Authentication;

use Sitecake\Util\InstanceConfigTrait;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractAuthenticator implements AuthenticatorInterface
{
    use InstanceConfigTrait;
    /**
     * Identifier or identifiers collection.
     *
     * @var IdentifierInterface
     */
    protected $identifier;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * Constructor
     *
     * @param IdentifierInterface $identifier Identifier or identifiers collection.
     * @param array $config Configuration settings.
     */
    public function __construct(IdentifierInterface $identifier = null, array $config = [])
    {
        $this->setConfig($config);
        $this->identifier = $identifier;
    }

    /**
     * Gets the identifier.
     *
     * @return IdentifierInterface
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Sets the identifier.
     *
     * @param IdentifierInterface $identifier IdentifierInterface instance.
     * @return $this
     */
    public function setIdentifier(IdentifierInterface $identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Authenticate a user based on the request information.
     *
     * @param Request $request Request to read authentication information from.
     * @return bool Returns a result object.
     */
    abstract public function authenticate(Request $request);
}
