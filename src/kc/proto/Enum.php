<?php

namespace kc\proto;

/**
* Extends this class to make proto enum
*/
abstract class Enum
{
    static function getName(int $number): string {
        $child = get_called_class();
        return isset($child::FIELDS[$number]) ? $child::FIELDS[$number] : '#unknown';
    }
}