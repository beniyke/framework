<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for entities that can be notified via messages (SMS, etc).
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Contracts;

use Helpers\Data;

interface MessageNotifiable extends Notifiable
{
    public function toMessage(): Data;
}
