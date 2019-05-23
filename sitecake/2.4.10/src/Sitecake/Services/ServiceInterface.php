<?php

namespace Sitecake\Services;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface ServiceInterface
{
    /**
     * Returns whether passed action should be authenticated or not
     *
     * @param string $action
     *
     * @return bool
     */
    public function isAuthRequired($action);

    /**
     * Returns Response object based on passed request.
     * Invokes Service action that's red from request params and returns actions return value
     *
     * @param string $action Action to be invoked
     * @param Request $request Request instance
     *
     * @return Response
     */
    public function invokeAction($action, Request $request);
}
