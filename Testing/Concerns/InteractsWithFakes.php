<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Audit\Services\AuditManagerService;
use Core\Event;
use Core\Ioc\Container;
use Helpers\File\Contracts\CacheInterface;
use Helpers\Http\Client\Curl;
use Mail\Mailer;
use Notify\NotificationManager;
use Queue\QueueManager;
use Testing\Fakes\AuditFake;
use Testing\Fakes\CacheFake;
use Testing\Fakes\EventFake;
use Testing\Fakes\HttpFake;
use Testing\Fakes\MailFake;
use Testing\Fakes\NotificationFake;
use Testing\Fakes\QueueFake;

trait InteractsWithFakes
{
    /**
     * Replace the Mail service with a fake.
     */
    protected function fakeMail(): MailFake
    {
        $fake = new MailFake();

        Container::getInstance()->instance(Mailer::class, $fake);

        return $fake;
    }

    /**
     * Replace the Event service with a fake.
     */
    protected function fakeEvents(): EventFake
    {
        return Event::fake();
    }

    /**
     * Replace the Notification service with a fake.
     */
    protected function fakeNotifications(): NotificationFake
    {
        $fake = new NotificationFake();

        Container::getInstance()->instance(NotificationManager::class, $fake);

        return $fake;
    }

    /**
     * Replace the Queue service with a fake.
     */
    protected function fakeQueue(): QueueFake
    {
        $fake = new QueueFake();

        Container::getInstance()->instance(QueueManager::class, $fake);

        return $fake;
    }

    /**
     * Replace the Cache service with a fake.
     */
    protected function fakeCache(): CacheFake
    {
        $fake = new CacheFake();

        Container::getInstance()->instance(CacheInterface::class, $fake);

        return $fake;
    }

    /**
     * Replace the HTTP client with a fake.
     */
    protected function fakeHttp(): HttpFake
    {
        $fake = new HttpFake();

        Container::getInstance()->instance(Curl::class, $fake);

        return $fake;
    }

    /**
     * Replace the Audit service with a fake.
     */
    protected function fakeAudit(): AuditFake
    {
        $fake = new AuditFake();

        Container::getInstance()->instance(AuditManagerService::class, $fake);

        return $fake;
    }
}
