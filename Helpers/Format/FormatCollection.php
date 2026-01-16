<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Provides various methods to format data into different output.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Format;

use Helpers\Format\Traits\ApiResponseTrait;
use Helpers\Format\Traits\ArrayTrait;
use Helpers\Format\Traits\JsonTrait;
use Helpers\Format\Traits\ObjectTrait;
use Helpers\Format\Traits\StringTrait;
use Helpers\Format\Traits\XmlTrait;

class FormatCollection
{
    use ApiResponseTrait;
    use ArrayTrait;
    use JsonTrait;
    use ObjectTrait;
    use StringTrait;
    use XmlTrait;
}
