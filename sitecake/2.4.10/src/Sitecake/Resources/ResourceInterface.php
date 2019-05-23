<?php

namespace Sitecake\Resources;

interface ResourceInterface
{
    /**
     * Sets public source file path
     *
     * @param string $path
     */
    public function setPath($path);

    /**
     * Returns public source file path
     *
     * @return mixed|null|string
     */
    public function getPath();

    /**
     * Returns string representation of instance
     *
     * @return string
     */
    public function __toString();
}
