<?php

namespace Sitecake\PageManager;

interface PageManagerInterface
{
    /**
     * Returns page based on passed identifier.
     * If identifier is empty, method should return home page.
     *
     * @param mixed $identifier
     *
     * @return Page
     */
    public function getPage($identifier = '');

    /**
     * Returns page draft based on passed identifier.
     * If identifier is empty, method should return home page.
     *
     * @param mixed $identifier
     *
     * @return Page
     */
    public function getDraft($identifier = '');

    public function saveDraft($identifier, $content = []);
}
