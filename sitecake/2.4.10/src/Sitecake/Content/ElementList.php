<?php

namespace Sitecake\Content;

use Cake\Collection\Collection;

class ElementList extends Collection implements \Countable
{
    /**
     * Make this object countable.
     *
     * Part of the Countable interface. Calling this method
     * will convert the underlying traversable object into an array and
     * read the count of the underlying data.
     *
     * @return int
     */
    public function count()
    {
        if ($this->getInnerIterator() instanceof \Countable) {
            return $this->getInnerIterator()->count();
        }

        return count($this->toArray());
    }

    public function eq($index)
    {
        $elements = $this->filter(function ($value, $key) use ($index) {
            return $key === (int)$index;
        });

        return $elements->first();
    }

    /**
     * Making collection mutable
     *
     * {@inheritdoc}
     */
    public function &current()
    {
        $element = parent::current();

        return $element;
    }
}
