<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Handles the rendering and logic of HTML components.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Helpers\Html;

use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Helpers\Http\Flash;

class HtmlComponent
{
    private array $data = [];

    private string $componentFile;

    private string $name;

    private bool $is_invalid = false;

    private bool $path_resolved = false;

    private Flash $flash;

    private ?string $fieldName = null;

    public function __construct(Flash $flash, string $name)
    {
        $this->flash = $flash;
        $this->name = $name;

        $systemPath = Paths::systemPath('Helpers/Html/components/' . $name);
        $resolvedSystemPath = $this->resolveExtension($systemPath);

        if (FileSystem::exists($resolvedSystemPath)) {
            $this->componentFile = $resolvedSystemPath;
            $this->path_resolved = true;

            return;
        }

        $templatePath = Paths::templatePath('components/' . $name);
        $resolvedTemplatePath = $this->resolveExtension($templatePath);

        if (FileSystem::exists($resolvedTemplatePath)) {
            $this->componentFile = $resolvedTemplatePath;
            $this->path_resolved = true;

            return;
        }

        $this->componentFile = $resolvedTemplatePath;
    }

    public function path(string $path): self
    {
        $newPath = Paths::templatePath('components/' . $this->name, $path);
        $this->componentFile = $this->resolveExtension($newPath);
        $this->path_resolved = FileSystem::exists($this->componentFile);

        return $this;
    }

    public function data(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function content(mixed $content): self
    {
        $this->data['value'] = $content;

        return $this;
    }

    public function options(array $options, bool $description = true): self
    {
        $this->data['options']['description'] = $description;
        $this->data['options']['data'] = $options;

        return $this;
    }

    public function selected(mixed $selected): self
    {
        $this->data['options']['selected'] = $selected;

        return $this;
    }

    public function attributes(array $attributes): self
    {
        $this->data['attributes'] = $attributes;

        if (isset($attributes['name'])) {
            $this->fieldName = $attributes['name'];
        }

        return $this;
    }

    public function flagIf(bool $is_invalid): self
    {
        $this->is_invalid = $is_invalid;

        return $this;
    }

    private function processFlashData(): void
    {
        $field = $this->fieldName;

        if (! $field) {
            return;
        }

        $oldValue = $this->flash->old($field);
        if (! isset($this->data['value']) && $oldValue !== null) {
            $this->data['value'] = $oldValue;
        }

        if ($this->flash->peekInputError($field)) {
            $this->is_invalid = true;

            $errorMessage = '';

            if ($this->name === 'error') {
                $errorMessage = $this->flash->getInputError($field);
            } else {
                $errorMessage = $this->flash->peekInputError($field);
            }

            $this->data['error_message'] = $errorMessage;
        }
    }

    private function resolveExtension(string $template): string
    {
        return pathinfo($template, PATHINFO_EXTENSION) ? $template : $template . '.php';
    }

    public function render(): string
    {
        if (! $this->path_resolved && ! file_exists($this->componentFile)) {
            throw new Exception("Component file '{$this->componentFile}' not found for component '{$this->name}'");
        }

        if ($this->fieldName) {
            $this->processFlashData();
        }

        return $this->prepareComponent($this->componentFile);
    }

    private function prepareComponent(string $file): string
    {
        if (! isset($this->data['attributes'])) {
            $this->data['attributes'] = [];
        }
        if (! isset($this->data['attributes']['class'])) {
            $this->data['attributes']['class'] = '';
        }

        if ($this->is_invalid) {
            $this->data['attributes']['class'] = trim($this->data['attributes']['class'] . ' is-invalid');
        }

        $data = [
            str_replace('-', '_', $this->name) => $this->data,
        ];

        ob_start();
        extract($data, EXTR_SKIP);
        include $file;

        return ltrim(ob_get_clean());
    }
}
