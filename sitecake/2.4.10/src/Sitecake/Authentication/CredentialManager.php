<?php

namespace Sitecake\Authentication;

class CredentialManager implements CredentialManagerInterface
{
    /**
     * @var array Credential string
     */
    protected $credentials;

    /**
     * @var string Path to file with credentials
     */
    protected $credentialsFile;

    /**
     * CredentialManager constructor.
     *
     * @param string $credentialsFile Path to credential file
     */
    public function __construct($credentialsFile)
    {
        $this->credentialsFile = $credentialsFile;
    }

    /**
     * Returns credentials array
     *
     * @return array
     */
    public function getCredentials()
    {
        if ($this->credentials === null) {
            $this->readCredentials();
        }
        return $this->credentials;
    }

    /**
     * Reads credentials set in credentials file
     *
     * @return void
     */
    public function readCredentials()
    {
        if (!empty($this->credentialsFile)) {
            if ($txt = $this->readCredentialFile()) {
                preg_match_all('/\$credentials\s*=\s*"([^"]+)"/', $txt, $matches);
                $this->credentials['password'] = $matches[1][0];
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function saveCredentials(array $credentials)
    {
        if (!empty($this->credentialsFile)) {
            $this->credentials = $credentials;

            return $this->writeCredentialFile();
        }

        return false;
    }

    /**
     * Reads content of credential file
     *
     * @return bool|string
     */
    public function readCredentialFile()
    {
        if (file_exists($this->credentialsFile)) {
            return file_get_contents($this->credentialsFile);
        }

        return false;
    }

    /**
     * Writes password to credential file and returns success of that operation
     *
     * @return bool
     */
    public function writeCredentialFile()
    {
        if (file_exists($this->credentialsFile)) {
            return (bool)file_put_contents(
                $this->credentialsFile,
                '<?php $credentials = "' . $this->credentials['password'] . '";'
            );
        }

        return false;
    }
}
