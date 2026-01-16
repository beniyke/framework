<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * MessagesCollector collects messages for the DebugBar.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Debugger\Collector;

use DebugBar\DataCollector\MessagesCollector as BaseMessagesCollector;

class MessagesCollector extends BaseMessagesCollector
{
    public function getName(): string
    {
        return 'messages';
    }
}
