<?php

namespace Sitecake\Services;

use Sitecake\Authentication\Authenticate;
use Sitecake\Authentication\Authenticator\DefaultAuthenticator;
use Sitecake\Authentication\Authenticator\SessionAuthenticator;
use Sitecake\Authentication\CredentialManager;
use Sitecake\Authentication\Identifier\CredentialFileIdentifier;
use Sitecake\Content\PageRenderAwareTrait;
use Sitecake\Exception\MissingArgumentsException;
use Sitecake\PageManager\Page;
use Sitecake\PageManager\PageManager;
use Sitecake\Resources\ResourceManager;
use Sitecake\SessionManager;
use Sitecake\Site;
use Sitecake\Sitecake as App;
use Symfony\Component\HttpFoundation\Request;

class SessionService extends Service
{
    use PageRenderAwareTrait;

    const SUCCESS_SESSION_STARTED = 0;
    const FAILURE_AUTHENTICATION_FAILED = 1;
    const FAILURE_SESSION_EXISTS = 2;

    const CREDENTIAL_PARAM_NAME = 'credentials';

    /**
     * @var Authenticate
     */
    protected $auth;

    /**
     * @var CredentialManager
     */
    protected $credentialManager;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @var PageManager
     */
    protected $pageManager;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * SessionService constructor.
     *
     * @param Site   $site
     * @param string $alias
     * @param array  $config
     */
    public function __construct(Site $site, $alias, array $config = [])
    {
        parent::__construct($site, $alias, $config);
        $this->initializeAuth($this->getConfig('CREDENTIALS_PATH'));
        $this->sessionManager = $this->getConfig('sessionManager');
        $this->pageManager = $this->getConfig('pageManager');
        $this->resourceManager = $this->getConfig('resourceManager');
    }

    /**
     * {@inheritdoc}
     */
    public function isAuthRequired($action)
    {
        return !($action === 'login' || $action === 'change');
    }

    /**
     * Initialize authentication
     *
     * @param string $credentialsPath
     *
     * @return void
     */
    protected function initializeAuth($credentialsPath)
    {
        $this->auth = new Authenticate();
        $this->auth->registerAuthenticator(new SessionAuthenticator());
        if (file_exists($credentialsPath)) {
            $this->credentialManager = new CredentialManager($credentialsPath);
            $this->auth->registerAuthenticator(
                new DefaultAuthenticator(new CredentialFileIdentifier($this->credentialManager), [
                    'paramName' => self::CREDENTIAL_PARAM_NAME
                ])
            );
        }
    }

    /**
     * Login action.
     * Authenticates user and starts editing session if not already started
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     */
    public function login(Request $request)
    {
        if ($request->query->get(self::CREDENTIAL_PARAM_NAME)) {
            $response = self::FAILURE_AUTHENTICATION_FAILED;
            if ($this->auth->authenticate($request)) {
                if ($this->sessionManager->sessionExists()) {
                    $response = self::FAILURE_SESSION_EXISTS;
                } else {
                    $request->getSession()->set('loggedin', true);
                    $this->sessionManager->startSession();

                    $response = self::SUCCESS_SESSION_STARTED;
                }
            }

            return $this->json($request, ['status' => $response], 200);
        } else {
            $page = $this->pageManager->getPage();

            $this->preparePageForRender($page);

            $this->injectLoginDialog($page);

            return $this->response($page->render());
        }
    }

    /**
     * Adds needed scripts for login to page
     *
     * @param Page $page
     */
    protected function injectLoginDialog(Page $page)
    {
        $page->enqueueScript($this->getLoginClientCode(), ['inline' => true])
            ->enqueueScript(App::getPath('EDITOR_LOGIN_URL'), [], ['data-cfasync' => 'false']);
    }

    /**
     * Returns js code that needs to be injected on login page
     *
     * @return string
     */
    protected function getLoginClientCode()
    {
        return 'var sitecakeGlobals = {' .
            "editMode: false, " .
            'serverVersionId: "2.4.8dev", ' .
            'phpVersion: "' . phpversion() . '@' . PHP_OS . '", ' .
            'serviceUrl:"' . App::getPath('SERVICE_URL') . '", ' .
            'configUrl:"' . App::getPath('EDITOR_CONFIG_URL') . '", ' .
            'forceLoginDialog: true' .
            '};';
    }

    /**
     * Change action.
     * Changes password
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function change(Request $request)
    {
        $credentials = $request->query->get('credentials');
        $newCredentials = $request->query->get('newCredentials');

        if (is_null($credentials)) {
            throw new MissingArgumentsException(['name' => 'credentials'], 400);
        }

        if (is_null($newCredentials)) {
            throw new MissingArgumentsException(['name' => 'newCredentials'], 400);
        }

        if ($this->auth->authenticate($request)) {
            $this->credentialManager->saveCredentials(['password' => $newCredentials]);
            $status = 0;
        } else {
            $status = 1;
        }

        return $this->json($request, ['status' => $status], 200);
    }

    /**
     * Logout action.
     * Terminates editing session
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function logout(Request $request)
    {
        $this->sessionManager->terminate();

        return $this->json($request, ['status' => 0], 200);
    }

    /**
     * Alive action.
     * Refresh editing session for current logged in user.
     *
     * @param Request $request
     *
     * @return \Symfony\Component\HttpFoundation\JsonResponse
     */
    public function alive(Request $request)
    {
        $this->sessionManager->refresh();

        return $this->json($request, ['status' => 0], 200);
    }
}
