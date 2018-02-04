<?php

namespace OlegStyle\YobitApi\Models;

/**
 * Class CurrencyPair
 * @package OlegStyle\YobitApi\Models
 *
 * @author Oleh Borysenko <olegstyle1@gmail.com>
 */
class CurrencyPair
{
    /**
     * @var string
     */
    public $from;

    /**
     * @var string
     */
    public $to;

    public function __construct(string $from, string $to)
    {
        $this->from = $from;
        $this->to = $to;
    }

    public function __toString()
    {
        return "{$this->from}_{$this->to}";
    }

    public static function toObject(string $pair)
    {
        $pair = explode('_', $pair);

        return new self($pair[0], $pair[1]);
    }
}
