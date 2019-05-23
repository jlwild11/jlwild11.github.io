<?php

namespace Sitecake\Authentication;

trait CredentialFileAwareTrait
{
    /**
     * @var array Credential string
     */
    protected $credentials = [];

    /**
     * @var string Path to file with credentials
     */
    protected $credentialsFile;


    /**
     * Reads credentials set in credentials file
     *
     * @param string $credentialsFile Path to credentials file
     *
     * @return void
     */
    public function readCredentials($credentialsFile)
    {
        $this->credentialsFile = $credentialsFile;
        if (!empty($this->credentialsFile)) {
            var_dump($this);
            if ($txt = $this->readCredentialFile()) {
                var_dump($txt);
                preg_match_all('/\$credentials\s*=\s*"([^"]+)"/', $txt, $matches);
                $this->credentials['password'] = $matches[1][0];
            }
        }
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
}
