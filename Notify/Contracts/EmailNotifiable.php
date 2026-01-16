<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for entities that can be notified via email.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Contracts;

use Mail\Contracts\Mailable;

interface EmailNotifiable extends Mailable, Notifiable
{
}
