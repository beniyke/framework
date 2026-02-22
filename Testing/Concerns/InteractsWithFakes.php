<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Replace the Mail service with a fake.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Testing\Concerns;

use Audit\Services\AuditManagerService;
use Core\Event;
use Core\Ioc\Container;
use Cron\Interfaces\CronInterface;
use Defer\DeferrerInterface;
use Helpers\File\Contracts\CacheInterface;
use Helpers\File\Contracts\LoggerInterface;
use Helpers\File\Storage\StorageInterface;
use Helpers\Http\Client\Curl;
use Helpers\Http\Session;
use Mail\Mailer;
use Notify\NotificationManager;
use Queue\QueueManager;
use Security\Auth\Interfaces\AuthManagerInterface;
use Testing\Fakes\AuditFake;
use Testing\Fakes\AuthFake;
use Testing\Fakes\CacheFake;
use Testing\Fakes\DeferFake;
use Testing\Fakes\EventFake;
use Testing\Fakes\HttpFake;
use Testing\Fakes\LogFake;
use Testing\Fakes\MailFake;
use Testing\Fakes\NotificationFake;
use Testing\Fakes\QueueFake;
use Testing\Fakes\ScheduleFake;
use Testing\Fakes\SessionFake;
use Testing\Fakes\StorageFake;

trait InteractsWithFakes
{
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

    /**
     * Replace the Logger service with a fake.
     */
    protected function fakeLog(): LogFake
    {
        $fake = new LogFake();

        Container::getInstance()->instance(LoggerInterface::class, $fake);

        return $fake;
    }

    /**
     * Replace the Session service with a fake.
     */
    protected function fakeSession(): SessionFake
    {
        $fake = new SessionFake();

        Container::getInstance()->instance(Session::class, $fake);

        return $fake;
    }

    /**
     * Replace the Schedule service with a fake.
     */
    protected function fakeSchedule(): ScheduleFake
    {
        $fake = new ScheduleFake();

        Container::getInstance()->instance(CronInterface::class, $fake);

        return $fake;
    }

    /**
     * Replace the Defer service with a fake.
     */
    protected function fakeDefer(): DeferFake
    {
        $fake = new DeferFake();

        Container::getInstance()->instance(DeferrerInterface::class, $fake);

        return $fake;
    }

    /**
     * Replace the Auth service with a fake.
     */
    protected function fakeAuth(): AuthFake
    {
        $fake = new AuthFake();

        Container::getInstance()->instance(AuthManagerInterface::class, $fake);

        return $fake;
    }

    /**
     * Replace the Storage service with a fake.
     */
    protected function fakeStorage(): StorageFake
    {
        $fake = new StorageFake();

        Container::getInstance()->instance(StorageInterface::class, $fake);

        return $fake;
    }
}
