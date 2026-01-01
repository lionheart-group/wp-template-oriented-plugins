<?php

namespace TofuPlugin\Base;

use Monolog\Logger as MonologLogger;

abstract class Migration
{
    abstract public function sql();
}
