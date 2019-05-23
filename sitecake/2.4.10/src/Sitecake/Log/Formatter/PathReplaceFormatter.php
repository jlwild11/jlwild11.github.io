<?php

namespace Sitecake\Log\Formatter;

use Monolog\Formatter\HtmlFormatter;
use Monolog\Logger;

class PathReplaceFormatter extends HtmlFormatter
{
    protected $replacements = [];

    /**
     * @param array  $replacements          Array of replacements where key is what should be replaced
     *                                      and value is replacement
     * @param string $dateFormat            The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(
        $replacements = [],
        $dateFormat = null
    ) {
        $this->replacements = $replacements;
        parent::__construct($dateFormat);
    }

    public function format(array $record)
    {
        return $this->replace(parent::format($this->translateRecord($record)));
    }

    protected function translateRecord(array $record)
    {
        $record['message'] = $record['description'];
        $record['datetime'] = new \DateTime();
        $record['channel'] = 'Sitecake';
        $record['level'] = Logger::toMonologLevel($record['level']);
        $record['extra'] = debug_backtrace();

        return $record;
    }

    protected function replace($content)
    {
        foreach ($this->replacements as $search => $replacement) {
            $content = str_replace($search, $replacement, $content);
        }

        return $content;
    }
}
