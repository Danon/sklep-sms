<?php
namespace App\Support;

class Expression
{
    private $value;

    public function __construct($value)
    {
        $this->value = (string) $value;
    }

    public function __toString()
    {
        return $this->value;
    }
}
