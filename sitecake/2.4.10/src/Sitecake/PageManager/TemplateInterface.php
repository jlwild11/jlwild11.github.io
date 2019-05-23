<?php

namespace Sitecake\PageManager;

interface TemplateInterface
{
    /**
     * Returns template source
     *
     * @return string
     */
    public function getSource();

    /**
     * Returns template's evaluated source
     *
     * @return string
     */
    public function evaluateSource();
}
