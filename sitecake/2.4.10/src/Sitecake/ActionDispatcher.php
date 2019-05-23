<?php

namespace Sitecake;

use Sitecake\Error\ErrorHandler;
use Sitecake\Exception\Http\UnauthorizedException;
use Sitecake\Exception\MissingActionException;
use Sitecake\Services\Exception\ServiceNotFoundException;
use Sitecake\Services\Service;
use Sitecake\Services\ServiceRegistry;
use Sitecake\Util\Text;
use Sitecake\Util\Utils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ActionDispatcher
{
    const SERVICE_QUERY = 'service';

    const ACTION_QUERY = 'action';

    /**
     * @var ServiceRegistry
     */
    protected $serviceRegistry;

    /**
     * @var SessionManager
     */
    protected $sessionManager;

    /**
     * @var \Sitecake\Error\ErrorHandler
     */
    protected $exceptionHandler;

    /**
     * @var array
     */
    protected $defaultAction = ['_pages', 'render'];

    /**
     * @var array
     */
    protected $loginAction = ['_session', 'login'];

    /**
     * ActionDispatcher constructor.
     *
     * @param SessionManager  $sessionManager
     * @param ServiceRegistry $serviceRegistry
     * @param ErrorHandler $exceptionHandler
     */
    public function __construct(
        SessionManager $sessionManager,
        ServiceRegistry $serviceRegistry,
        ErrorHandler $exceptionHandler
    ) {
        $this->sessionManager = $sessionManager;
        $this->serviceRegistry = $serviceRegistry;
        $this->exceptionHandler = $exceptionHandler;
    }

    public function setDefaultAction($service, $action)
    {
        $this->defaultAction = [
            'service' => $service,
            'action' => $action
        ];
    }

    public function setLoginAction($service, $action)
    {
        $this->loginAction = [
            'service' => $service,
            'action' => $action
        ];
    }

    /**
     * Dispatches specific action of a specific service based on passed request.
     * Service alias and action name are passed as query parameters.
     *
     * @param Request $request
     *
     * @return Response
     * @throws ServiceNotFoundException If service with passed alias doesn't exist
     */
    public function dispatch(Request $request)
    {
        if ($request->query->has(self::SERVICE_QUERY)) {
            $serviceAlias = $request->query->get(self::SERVICE_QUERY);
            $action = $request->query->has(self::ACTION_QUERY) ? $request->query->get(self::ACTION_QUERY) : '';
        } else {
            $serviceAlias = $this->defaultAction['service'];
            $action = $this->defaultAction['action'];
        }

        /** @var Service $service */
        $service = $this->serviceRegistry->get($serviceAlias);

        if (!isset($service)) {
            throw new ServiceNotFoundException(
                sprintf('Service %s couldn\'t be found', substr(Text::camelize($serviceAlias, '_'), 1))
            );
        }

        return $this->invoke($service, $action, $request);
    }

    /**
     * Invokes passed action on a passed service instance
     *
     * @param Service $service
     * @param string  $action
     * @param Request $request
     *
     * @return Response
     * @throws MissingActionException If passed service doesn't implement passed action
     */
    protected function invoke(Service $service, $action, $request)
    {
        try {
            if ($service->isAuthRequired($action) && !$this->sessionManager->sessionStarted()) {
                $acceptsJsonContent = in_array('application/json', $request->getAcceptableContentTypes());
                if ($request->isXmlHttpRequest() && $acceptsJsonContent) {
                    throw new UnauthorizedException('Unauthorized access');
                } else {
                    $service = $this->serviceRegistry->get($this->loginAction['service']);
                    $action = $this->loginAction['action'];
                }
            }

            $response = $service->invokeAction($action, $request);
            if (!($response instanceof Response)) {
                throw new \LogicException(
                    'Service action can only return Symfony\Component\HttpFoundation\Response',
                    500
                );
            }

            return $response;
        } catch (\Exception $e) {
            $code = $e->getCode();
            $httpCodes = [400, 401, 403, 404, 405, 500];

            // TODO : Need to send real http status when new client is implemented
            if (empty($code) || !in_array($code, $httpCodes)) {
                $code = 500;
            }
            $response = [
                'status' => -1,
                'code' => $code,
                'errMessage' => $e->getMessage()
            ];
            if (Sitecake::isDebugMode()) {
                $response['trace'] = Utils::formatTrace($e);
            }

            $acceptsJsonContent = in_array('application/json', $request->getAcceptableContentTypes());
            if ($request->isXmlHttpRequest() && $acceptsJsonContent) {
                return new JsonResponse($response, $code);
            }

            return new Response($this->exceptionHandler->handleException($e, true), $code);
        }
    }
}
