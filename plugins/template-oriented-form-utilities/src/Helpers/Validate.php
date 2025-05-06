<?php

namespace TofuPlugin\Helpers;

use Respect\Validation\Validator as v;

class Validate
{
    /**
     * Validate the email address.
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string | null $email): bool
    {
        // If the email is empty, return true.
        if (empty($email)) {
            return true;
        }

        return v::email()->validate($email) === true;
    }

    public static function isValidNumber(string | null $number): bool
    {
        if (empty($number)) {
            return true;
        }

        return v::number()->validate($number) === true;
    }

    public static function isValidLength(string | null $text, int | null $min = null, int | null $max = null): bool
    {
        if (empty($text)) {
            return true;
        }

        return v::length($min, $max)->validate($text) === true;
    }

    public static function isValidPhone(string | null $phone): bool
    {
        if (empty($phone)) {
            return true;
        }

        return v::phone()->validate($phone) === true;
    }

    public static function isValidCode(string | null $code): bool
    {
        if (empty($code)) {
            return true;
        }

        return v::numericVal()->validate($code) === true;
    }

    public static function isValidSize(string | null $file, string | null $size): bool
    {
        if (empty($file)) {
            return true;
        }

        return v::size($size)->validate($file) === true;
    }
}
