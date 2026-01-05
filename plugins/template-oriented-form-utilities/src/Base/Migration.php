<?php

namespace TofuPlugin\Base;

use Monolog\Logger as MonologLogger;

abstract class Migration
{
    /**
     * Get the SQL statement for the migration
     *
     * @return string
     */
    abstract public function sql(): string;
}
