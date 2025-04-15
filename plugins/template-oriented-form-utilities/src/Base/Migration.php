<?php

namespace TofuPlugin\Base;

use Monolog\Logger as MonologLogger;

abstract class Migration
{
    /** @var \wpdb */
    protected $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    abstract public function sql();
}
