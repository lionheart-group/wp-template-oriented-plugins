<?php

namespace TofuPlugin\Models;

use TofuPlugin\Base\DatabaseModels as AbstractModels;
use TofuPlugin\Logger;

class Session extends AbstractModels {
    const TABLE_SUFFIX = 'tofu_sessions';
}
