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
}
