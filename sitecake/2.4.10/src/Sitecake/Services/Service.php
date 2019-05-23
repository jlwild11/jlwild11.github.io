<?php

namespace Sitecake\Services;

use Cake\Event\Event;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Sitecake\Exception\MissingActionException;
use Sitecake\Site;
use Sitecake\Util\InstanceConfigTrait;
use Sitecake\Util\Text;
use Sitecake\Util\Utils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class Service implements ServiceInterface, EventDispatcherInterface, EventListenerInterface
{
    use EventDispatcherTrait;
    use InstanceConfigTrait;

    /**
     * @var Site
     */
    protected $site;

    /**
     * Default configuration
     *
     * @var array
     */
    protected $defaultConfig = [];

    /**
     * Alias under which this service is registered
     *
     * @var string
     */
    protected $alias;

    /**
     * Service constructor.
     *
     * @param Site   $site
     * @param string $alias
     * @param array  $config
     */
    public function __construct(Site $site, $alias, array $config = [])
    {
        $this->site = $site;
        $this->alias = $alias;
        $this->setConfig($config);
        $this->getEventManager()->on($this);
    }


    /**
     * {@inheritdoc}
     */
    public function isAuthRequired($action)
    {
        return true;
    }

    /**
     * beforeAction callback event which is fired before each action in service
     *
     * @param Event $event
     *
     * @return null|Response
     */
    public function beforeAction(Event $event)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function implementedEvents()
    {
        return [
            'Service.beforeActionInvoke' => 'beforeAction'
        ];
    }

    /**
     * Returns camelize name for service
     *
     * @return string
     */
    protected function getName()
    {
        return ucfirst(substr(Text::camelize($this->alias, '_'), 1));
    }

    /**
     * Checks whether passed action is $this service action and if it's public
     *
     * @param string $action Action to check
     *
     * @return bool
     */
    protected function actionExists($action)
    {
        if (!is_subclass_of($this, Service::class)) {
            return false;
        }

        return Utils::hasPublicMethod($this, $action);
    }

    /**
     * {@inheritdoc}
     */
    public function invokeAction($action, Request $request)
    {
        if (!$this->actionExists($action)) {
            throw new MissingActionException([
                'action' => $action,
                'service' => $this->getName()
            ], 400);
        }

        $beforeInvokeEvent = $this->dispatchEvent('Service.beforeActionInvoke', compact('request'));
        if ($beforeInvokeEvent->getResult() instanceof Response) {
            return $beforeInvokeEvent->getResult();
        }

        /* @var callable $callable */
        $callable = [$this, $action];

        return $callable($request);
    }

    /**
     * Returns json response
     *
     * @param     $req
     * @param     $data
     * @param int $status
     *
     * @return JsonResponse
     */
    protected function json($req, $data, $status = 200)
    {
        $resp = new JsonResponse($data, $status);

        if ($req->query->has('callback')) {
            $resp->setCallback($req->query->get('callback'));
        }

        return $resp;
    }

    /**
     * Redirects to passed URL
     *
     * @param       $url
     * @param int   $status
     * @param array $headers
     *
     * @return RedirectResponse
     */
    protected function redirect($url, $status = 302, array $headers = [])
    {
        return new RedirectResponse($url, $status, $headers);
    }

    /**
     * Returns passed content wrapped as Response object
     *
     * @param mixed $content
     *
     * @return Response
     */
    protected function response($content)
    {
        return new Response((string)$content);
    }
}
