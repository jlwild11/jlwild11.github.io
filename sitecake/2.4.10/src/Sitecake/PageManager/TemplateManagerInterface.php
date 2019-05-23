<?php

namespace Sitecake\PageManager;

interface TemplateManagerInterface
{
    /**
     * Returns template based on passed identifier
     *
     * @param mixed $identifier
     *
     * @return TemplateInterface
     */
    public function getTemplate($identifier);
}
