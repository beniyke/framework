<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Interface for entities that can be notified via database.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Contracts;

use Helpers\Data;

interface DatabaseNotifiable extends Notifiable
{
    public function toDatabase(): Data;
}
