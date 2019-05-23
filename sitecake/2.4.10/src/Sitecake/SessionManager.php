<?php

namespace Sitecake;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

class SessionManager
{
    const SESSION_TIMEOUT = 10000;

    /**
     * Session instance
     *
     * @var SessionInterface
     */
    protected $session;

    /**
     * Concurrent editing manager
     *
     * @var FileLock
     */
    protected $fileLock;

    /**
     * EditSessionManager constructor.
     *
     * @param SessionInterface $session  Session
     * @param FileLock         $fileLock File lock handler
     */
    public function __construct(SessionInterface $session, FileLock $fileLock)
    {
        $this->session = $session;
        $this->fileLock = $fileLock;
    }

    /**
     * Starts editing session
     */
    public function startSession()
    {
        if (!$this->sessionStarted()) {
            $this->session->set('loggedin', true);
            $this->fileLock->set('login', self::SESSION_TIMEOUT);
            Sitecake::eventBus()->dispatch('Sitecake.editSessionStart');
        }
    }

    /**
     * Returns whether the current editing session is started.
     *
     * @return boolean returns true if user is logged in.
     */
    public function sessionStarted()
    {
        return $this->session->has('loggedin');
    }

    /**
     * Returns whether there is already editing session started
     *
     * @return bool
     */
    public function sessionExists()
    {
        return $this->fileLock->exists('login');
    }

    /**
     * Terminates current editing session
     */
    public function terminate()
    {
        $this->session->invalidate(0);
        $this->fileLock->remove('login');
        Sitecake::eventBus()->dispatch('Sitecake.editSessionEnd');
    }

    /**
     * Refreshes current editing session lock
     */
    public function refresh()
    {
        if ($this->sessionStarted()) {
            $this->fileLock->set('login', self::SESSION_TIMEOUT);
            Sitecake::eventBus()->dispatch('Sitecake.editSessionRenew');
        }
    }
}
