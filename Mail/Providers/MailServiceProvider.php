<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This service provider registers mail services.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Providers;

use Core\Services\ConfigServiceInterface;
use Core\Services\DeferredServiceProvider;
use Helpers\File\Contracts\LoggerInterface;
use Helpers\File\Paths;
use Helpers\Html\Assets;
use Mail\Contracts\MailDriverInterface;
use Mail\Contracts\TemplateLoaderInterface;
use Mail\Contracts\TemplateRendererInterface;
use Mail\Core\EmailBuilder;
use Mail\Drivers\PHPMailerDriver;
use Mail\Services\FileTemplateLoader;
use Mail\Services\MailDebugSaver;
use Mail\Services\TemplateRenderer;
use PHPMailer\PHPMailer\PHPMailer;

class MailServiceProvider extends DeferredServiceProvider
{
    public static function provides(): array
    {
        return [
            TemplateLoaderInterface::class,
            TemplateRendererInterface::class,
            MailDebugSaver::class,
            EmailBuilder::class,
            MailDriverInterface::class,
        ];
    }

    public function register(): void
    {
        $config = $this->container->get(ConfigServiceInterface::class);

        $this->container->singleton(TemplateLoaderInterface::class, function ($container) use ($config) {
            $path = Paths::basePath($config->get('mail.paths.template'));

            return new FileTemplateLoader($path);
        });

        $this->container->singleton(TemplateRendererInterface::class, function ($container) {
            return new TemplateRenderer();
        });

        $this->container->singleton(MailDebugSaver::class, function ($container) use ($config) {
            $path = $config->get('mail.paths.debug');

            return new MailDebugSaver($path);
        });

        $this->container->singleton(EmailBuilder::class, function ($container) {
            return new EmailBuilder(
                $container->get(TemplateLoaderInterface::class),
                $container->get(TemplateRendererInterface::class),
                $container->get(MailDebugSaver::class),
                $container->get(ConfigServiceInterface::class),
                $container->get(Assets::class),
            );
        });

        $this->container->instance(PHPMailer::class, new PHPMailer());
        $this->container->singleton(MailDriverInterface::class, function ($container) {
            return new PHPMailerDriver($container->get(PHPMailer::class), $container->get(ConfigServiceInterface::class), $container->get(LoggerInterface::class));
        });
    }
}
