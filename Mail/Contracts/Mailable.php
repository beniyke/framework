<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This interface defines the contract for mailable objects.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Contracts;

use Helpers\Data;
use Mail\Core\EmailBuilder;

interface Mailable
{
    public function toMail(EmailBuilder $builder): Data;
}
