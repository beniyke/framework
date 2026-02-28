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

use Helpers\Data\Data;
use Mail\Core\EmailBuilder;
use Notify\Contracts\Notifiable;

interface Mailable extends Notifiable
{
    public function toMail(EmailBuilder $builder): Data;
}
