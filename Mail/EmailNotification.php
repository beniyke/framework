<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Abstract EmailNotification class.
 * Represents an email notification that can be sent via the Mailer.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail;

use Helpers\Data;
use Mail\Contracts\Mailable;
use Mail\Core\EmailBuilder;

abstract class EmailNotification implements Mailable
{
    protected Data $payload;

    public function __construct(Data $payload)
    {
        $this->payload = $payload;
    }

    public function getPreheader(): ?string
    {
        return null;
    }

    public function getSender(): ?array
    {
        return null;
    }

    public function getAttachment(): array
    {
        return [];
    }

    public function getTemplate(): string
    {
        return 'default';
    }

    public function getTemplateLogo(): ?string
    {
        return null;
    }

    public function getTemplateTitle(): ?string
    {
        return null;
    }

    public function getTemplateFootnote(): ?string
    {
        return null;
    }

    /**
     * Builds the final HTML content using the EmailBuilder.
     */
    final protected function getContent(EmailBuilder $builder): string
    {
        return $builder->template($this->getTemplate())
            ->logo($this->getTemplateLogo())
            ->title($this->getTemplateTitle())
            ->footnote($this->getTemplateFootnote())
            ->subject($this->getTitle())
            ->preheader($this->getPreheader() ?? '')
            ->content($this->getRawMessageContent())
            ->build();
    }

    /**
     * Converts the notification into a Data object for the Mail Dispatcher.
     */
    public function toMail(EmailBuilder $builder): Data
    {
        $mail = array_filter([
            'from' => $this->getSender(),
            'recipients' => $this->getRecipients(),
            'subject' => $this->getSubject(),
            'message' => $this->getContent($builder),
            'attachment' => $this->getAttachment(),
        ]);

        return Data::make($mail);
    }

    /**
     * Define who receives the email.
     */
    abstract public function getRecipients(): array;

    /**
     * Define the email Subject line.
     */
    abstract public function getSubject(): string;

    /**
     * Define the inner title (header) of the email.
     */
    abstract public function getTitle(): string;

    /**
     * Define the body content of the email.
     */
    abstract protected function getRawMessageContent(): string;
}
