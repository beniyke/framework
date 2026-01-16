<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class implements the MailDriverInterface using PHPMailer.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Drivers;

use Core\Services\ConfigServiceInterface;
use Helpers\File\Contracts\LoggerInterface;
use Mail\Contracts\MailDriverInterface;
use Mail\MailStatus;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

final class PHPMailerDriver implements MailDriverInterface
{
    private PHPMailer $mailer;

    private ConfigServiceInterface $config;

    private LoggerInterface $logger;

    public function __construct(PHPMailer $mailer, ConfigServiceInterface $config, LoggerInterface $logger)
    {
        $logger->setLogFile('mail.log');

        $this->mailer = $mailer;
        $this->config = $config;
        $this->logger = $logger;

        $this->configureMailer();
    }

    private function configureMailer(): void
    {
        $mailConfig = $this->config->get('mail');

        $this->mailer->isHTML(true);
        $this->mailer->CharSet = 'UTF-8';

        $isSmtp = $mailConfig['smtp']['status'] ?? false;

        if ($isSmtp) {
            $smtpConfig = $mailConfig['smtp'];
            $this->mailer->isSMTP();
            $this->mailer->Host = $smtpConfig['host'];
            $this->mailer->SMTPAuth = $smtpConfig['auth'];
            $this->mailer->Username = $smtpConfig['username'];
            $this->mailer->Password = $smtpConfig['password'];
            $this->mailer->SMTPSecure = $smtpConfig['encryption'];
            $this->mailer->Port = $smtpConfig['port'];
            $this->mailer->SMTPDebug = $smtpConfig['debug'] ?? 0;
        }
    }

    public function send(array $from, string $subject, array $recipients, string $message, array $attachment = []): MailStatus
    {
        try {
            $this->mailer->setFrom($from['email'], $from['name'] ?? '');
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $message;

            $this->addAddresses($recipients['to'] ?? [], 'addAddress');
            $this->addAddresses($recipients['cc'] ?? [], 'addCC');
            $this->addAddresses($recipients['bcc'] ?? [], 'addBCC');
            $this->addAddresses($recipients['reply_to'] ?? [], 'addReplyTo');

            foreach ($attachment as $path => $name) {
                $this->mailer->addAttachment($path, $name);
            }

            $this->mailer->send();
            $this->logger->info('Email sent successfully.', ['subject' => $subject, 'to' => $recipients['to']]);

            return MailStatus::success();
        } catch (Exception $e) {
            $errorMessage = 'PHPMailer error: ' . $this->mailer->ErrorInfo;
            $this->logger->error($errorMessage, ['subject' => $subject, 'to' => $recipients['to'], 'exception' => $e->getMessage()]);

            return MailStatus::failure($errorMessage);
        } finally {
            $this->clearMailer();
        }
    }

    private function addAddresses(array $addresses, string $method): void
    {
        foreach ($addresses as $key => $value) {
            $emailAddress = '';
            $displayName = '';

            if (is_string($key) && filter_var($key, FILTER_VALIDATE_EMAIL)) {
                $emailAddress = $key;
                $displayName = (string) $value;
            } elseif (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $emailAddress = $value;
                $displayName = '';
            } else {
                $logContext = [
                    'key' => is_scalar($key) ? $key : gettype($key),
                    'value' => is_scalar($value) ? $value : gettype($value),
                    'method' => $method,
                ];

                $this->logger->warning('Skipping invalid or empty email address in mailer', $logContext);

                continue;
            }

            if (! empty($emailAddress)) {
                $this->mailer->$method($emailAddress, $displayName);
            }
        }
    }

    private function clearMailer(): void
    {
        $this->mailer->clearAllRecipients();
        $this->mailer->clearAttachments();
    }
}
