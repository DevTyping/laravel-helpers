<?php

namespace DevTyping\Helpers;

/**
 * Trait Str
 * @package App\Helpers\v2
 */
trait Str {

    /**
     * Check if a string contain a symbol
     *
     * @param $string
     * @param string $symbol
     * @return bool
     */
    public function hasSymbol($string, $symbol = ':') {
        return (strpos($string, $symbol) !== false);
    }
}
