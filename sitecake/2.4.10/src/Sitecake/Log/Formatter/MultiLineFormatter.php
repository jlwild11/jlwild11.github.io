<?php

namespace Sitecake\Log\Formatter;

use Monolog\Formatter\LineFormatter;

class MultiLineFormatter extends LineFormatter
{
    public function format(array $record)
    {
        return preg_replace('/\s(\#[0-9]+)/', "\n$1", parent::format($record));
    }
}
