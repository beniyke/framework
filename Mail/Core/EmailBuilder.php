<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * This class builds email messages from templates and data.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Mail\Core;

use Core\Services\ConfigServiceInterface;
use Helpers\Html\Assets;
use Mail\Contracts\TemplateLoaderInterface;
use Mail\Contracts\TemplateRendererInterface;
use Mail\Services\MailDebugSaver;

class EmailBuilder
{
    private array $data = [];

    private string $templateFile = '';

    private TemplateLoaderInterface $loader;

    private TemplateRendererInterface $renderer;

    private MailDebugSaver $debugSaver;

    private ConfigServiceInterface $config;

    private Assets $assets;

    public function __construct(TemplateLoaderInterface $loader, TemplateRendererInterface $renderer, MailDebugSaver $debugSaver, ConfigServiceInterface $config, Assets $assets)
    {
        $this->loader = $loader;
        $this->renderer = $renderer;
        $this->debugSaver = $debugSaver;
        $this->config = $config;
        $this->assets = $assets;

        $this->setDefaultData();
    }

    /**
     * Initializes or resets the data array to default values.
     */
    private function setDefaultData(): void
    {
        $logo = $this->config->get('mail.builder.brand.logo');
        $logoUrl = $this->assets->url($logo);

        $this->data = [
            'logo' => $logoUrl,
            'title' => $this->config->get('mail.builder.brand.name'),
            'subject' => '',
            'preheader' => '',
            'content' => '',
            'footnote' => '&copy; ' . date('Y') . ' ' . $this->config->get('mail.builder.brand.name') . ', All rights reserved.',
        ];
    }

    public function template(string $name): self
    {
        $templates = $this->config->get('mail.builder.templates');
        $this->templateFile = $templates[$name] ?? $this->templateFile;

        return $this;
    }

    public function logo(?string $logo = null): self
    {
        if (! empty($logo)) {
            $this->data['logo'] = $logo;
        }

        return $this;
    }

    public function title(?string $title = null): self
    {
        if (! empty($title)) {
            $this->data['title'] = $title;
        }

        return $this;
    }

    public function footnote(?string $footnote = null): self
    {
        if (! empty($footnote)) {
            $this->data['footnote'] = $footnote;
        }

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->data['subject'] = $subject;

        return $this;
    }

    public function preheader(string $preheader): self
    {
        $this->data['preheader'] = $preheader;

        return $this;
    }

    public function content(string $content): self
    {
        $this->data['content'] = $content;

        return $this;
    }

    public function reset(): self
    {
        $this->templateFile = '';
        $this->setDefaultData();

        return $this;
    }

    public function build(): string
    {
        if (empty($this->data['preheader'])) {
            $this->data['preheader'] = mb_substr(strip_tags($this->data['content']), 0, 150);
        }

        $template = $this->loader->load($this->templateFile);
        $content = $this->renderer->render($template, $this->data);

        if ($this->config->isDebugEnabled()) {
            $filename = 'mail-' . date('YmdHis') . '.html';
            $this->debugSaver->save($filename, $content);
        }

        $this->reset();

        return $content;
    }
}
