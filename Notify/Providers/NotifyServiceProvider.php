<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This service provider registers notification services and channels.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Notify\Providers;

use App\Channels\Adapters\EmailAdapter;
use App\Channels\Adapters\InAppAdapter;
use App\Channels\Adapters\Interfaces\EmailAdapterInterface;
use App\Channels\Adapters\Interfaces\InAppAdapterInterface;
use App\Channels\Adapters\Interfaces\SmsAdapterInterface;
use App\Channels\Adapters\Interfaces\WhatsAppAdapterInterface;
use App\Channels\Adapters\SmsAdapter;
use App\Channels\Adapters\WhatsAppAdapter;
use App\Channels\EmailChannel;
use App\Channels\InAppChannel;
use App\Channels\SmsChannel;
use App\Channels\WhatsAppChannel;
use Core\Services\DeferredServiceProvider;
use Notify\NotificationManager;

class NotifyServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            NotificationManager::class,
            EmailAdapter::class,
            WhatsAppAdapter::class,
            SmsAdapter::class,
            InAppAdapter::class,
            EmailAdapterInterface::class,
            InAppAdapterInterface::class,
            WhatsAppAdapterInterface::class,
            SmsAdapterInterface::class
        ];
    }

    public function register(): void
    {
        $this->container->singleton(EmailAdapterInterface::class, EmailAdapter::class);
        $this->container->singleton(InAppAdapterInterface::class, InAppAdapter::class);
        $this->container->singleton(WhatsAppAdapterInterface::class, WhatsAppAdapter::class);
        $this->container->singleton(SmsAdapterInterface::class, SmsAdapter::class);

        $this->container->singleton(NotificationManager::class, function ($container) {
            $manager = new NotificationManager();

            $inAppChannel = $container->make(InAppChannel::class);
            $emailChannel = $container->make(EmailChannel::class);
            $smsChannel = $container->make(SmsChannel::class);
            $whatsAppChannel = $container->make(WhatsAppChannel::class);

            $manager->registerChannel('in-app', $inAppChannel);
            $manager->registerChannel('email', $emailChannel);
            $manager->registerChannel('sms', $smsChannel);
            $manager->registerChannel('whatsapp', $whatsAppChannel);

            return $manager;
        });
    }
}
