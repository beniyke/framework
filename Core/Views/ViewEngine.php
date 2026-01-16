<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * ViewEngine encapsulates view-related functionality, including rendering templates,
 * extending layouts, working with sections, including partials, and generating CSRF tokens.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Views;

use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\EnvironmentInterface;
use Debugger\Debugger;
use Exception;
use Helpers\File\FileSystem;
use Helpers\File\Paths;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\Http\Session;
use Throwable;

class ViewEngine implements ViewInterface
{
    private string $template;

    private array $data = [];

    private array $sections = [];

    private ?string $current_section = null;

    private string $template_path;

    private string $extension = '.php';

    private Request $request;

    private Response $response;

    private ConfigServiceInterface $config;

    private bool $isLayoutPath = false;

    private Flash $flash;

    private array $stacks = [];

    private array $renderedOnce = [];

    private EnvironmentInterface $environment;

    private Session $session;

    public function __construct(Request $request, Response $response, ConfigServiceInterface $config, Flash $flash, EnvironmentInterface $environment, Session $session)
    {
        $this->request = $request;
        $this->response = $response;
        $this->config = $config;
        $this->flash = $flash;
        $this->environment = $environment;
        $this->session = $session;
    }

    private function layout(): self
    {
        $this->isLayoutPath = true;

        return $this;
    }

    public function path(string $path): self
    {
        $this->template_path = rtrim($path, DIRECTORY_SEPARATOR);

        return $this;
    }

    public function template(string $template): self
    {
        $this->template = $this->resolveTemplatePath($template);

        return $this;
    }

    private function resolveTemplatePath(string $template): string
    {
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        $full_path = $this->template_path . DIRECTORY_SEPARATOR . $this->resolveExtension($template);

        return FileSystem::exists($full_path) ? $full_path : Paths::coreViewTemplatePath('noview.html');
    }

    private function resolveExtension(string $template): string
    {
        return pathinfo($template, PATHINFO_EXTENSION) ? $template : $template . $this->extension;
    }

    public function denyAccessIf(bool $value, string $template = 'deny'): self
    {
        if ($value) {
            $this->template = $this->resolveTemplatePath($template);
        }

        return $this;
    }

    public function data(array $data): self
    {
        $this->data = array_merge($this->data, $data);

        return $this;
    }

    public function content(): string
    {
        return $this->compile($this->template, $this->data);
    }

    public function render(): Response
    {
        $html = $this->content();

        return $this->response
            ->header(['Content-Type' => 'text/html; charset=UTF-8'])
            ->ok($html);
    }

    private function compile(string $view_template, array $data = []): string
    {
        if (! FileSystem::exists($view_template)) {
            throw new Exception("Template not found: $view_template");
        }

        if (class_exists(Debugger::class)) {
            $debugger = Debugger::getInstance();
            if ($debugger->getDebugBar()->hasCollector('views')) {
                $debugger->getDebugBar()['views']->addView($view_template, $data);
            }
        }

        $data = array_merge($this->data, $data);

        $level = ob_get_level();
        ob_start();
        try {
            extract($data, EXTR_SKIP);
            include $view_template;

            return ltrim(ob_get_clean());
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $e;
        }
    }

    private function extend(string $template, array $data = []): string
    {
        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        $layout = Paths::layoutPath($template);
        $template_path = $this->resolveExtension($layout);

        return $this->compile($template_path, $data);
    }

    private function startSection(string $name): void
    {
        ob_start();
        $this->current_section = $name;
    }

    private function endSection(): void
    {
        if ($this->current_section !== null) {
            $this->sections[$this->current_section] = ob_get_clean();
            $this->current_section = null;
        }
    }

    private function setSection(string $name, string $content): void
    {
        $this->sections[$name] = $content;
    }

    private function hasSection(string $name): bool
    {
        return isset($this->sections[$name]);
    }

    private function section(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    private function active(string $path, string $class = 'active', string $default = ''): string
    {
        $uri = ltrim($this->request->uri(), '/');
        $path = ltrim($path, '/');

        if ($path === '' || $path === '/') {
            return ($uri === '' || $uri === '/') ? $class : $default;
        }

        return str_contains($uri, $path) ? $class : $default;
    }

    private function isRoute(string $name): bool
    {
        return $this->request->route_name() === $name;
    }

    private function user(): ?object
    {
        return $this->request->user();
    }

    private function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->session;
        }

        return $this->session->get($key, $default);
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    private function isLocal(): bool
    {
        return $this->environment->isLocal();
    }

    private function isProduction(): bool
    {
        return $this->environment->isProduction();
    }

    private function include(string $template, array $data = [], ?string $path = null): string
    {
        if ($this->isLayoutPath) {
            $path = $this->getLayoutPath();
        }

        if (! empty($path)) {
            $this->template_path = $path;
        }

        $template = str_replace('.', DIRECTORY_SEPARATOR, $template);

        $full_path = rtrim($this->template_path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->resolveExtension($template);

        return $this->compile($full_path, $data);
    }

    private function modal(string $modal, array $data = [], ?string $path = null): string
    {
        return $this->include('modals' . DIRECTORY_SEPARATOR . $modal, $data, $path);
    }

    private function inc(string $file, array $data = [], ?string $path = null): string
    {
        return $this->include('inc' . DIRECTORY_SEPARATOR . $file, $data, $path);
    }

    private function csrf(): string
    {
        $hidden = $this->hidden($this->request->getCsrfTokenIdentifier(), $this->request->getCsrfToken());

        if ($this->config->get('csrf.honeypot')) {
            $hidden .= $this->hidden($this->request->getHoneypotIdentifier());
        }

        return $hidden;
    }

    private function importantFormFields(string $verb): string
    {
        $fields = $this->csrf();
        $fields .= $this->method($verb);
        $fields .= $this->callback();

        return $fields;
    }

    private function hidden(string $field_name, ?string $field_value = null): string
    {
        return '<input type="hidden" name="' . $field_name . '" value="' . $this->escape(($field_value ?? '')) . '" />';
    }

    private function callback(?string $value = null): string
    {
        return $this->hidden($this->request->getCallbackRouteIdentifier(), ($value ?? ltrim($this->request->uri(), '/')));
    }

    private function referer(?string $value = null): string
    {
        return $this->hidden($this->request->getCallbackRouteIdentifier(), ($value ?? $this->request->refererRoute()));
    }

    private function method(string $value): string
    {
        return $this->hidden($this->request->getMethodIdentifier(), strtoupper($value));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private function auth(): bool
    {
        return $this->request->user() !== null;
    }

    private function guest(): bool
    {
        return ! $this->auth();
    }

    private function error(string $field): ?string
    {
        return $this->flash->peekInputError($field);
    }

    private function hasError(string $field): bool
    {
        return $this->flash->hasInputError($field);
    }

    private function old(string $key, mixed $default = null): mixed
    {
        return $this->flash->old($key) ?? $default;
    }

    private function push(string $name): void
    {
        ob_start();
        $this->current_section = 'stack:' . $name;
    }

    private function prepend(string $name): void
    {
        ob_start();
        $this->current_section = 'prepend:' . $name;
    }

    private function endPush(): void
    {
        if ($this->current_section !== null && (str_starts_with($this->current_section, 'stack:') || str_starts_with($this->current_section, 'prepend:'))) {
            $isPrepend = str_starts_with($this->current_section, 'prepend:');
            $name = str_replace(['stack:', 'prepend:'], '', $this->current_section);
            $content = ob_get_clean();

            if (! isset($this->stacks[$name])) {
                $this->stacks[$name] = [];
            }

            if ($isPrepend) {
                array_unshift($this->stacks[$name], $content);
            } else {
                $this->stacks[$name][] = $content;
            }

            $this->current_section = null;
        }
    }

    private function stack(string $name): string
    {
        return implode('', $this->stacks[$name] ?? []);
    }

    private function once(string $id): bool
    {
        if (isset($this->renderedOnce[$id])) {
            return false;
        }

        $this->renderedOnce[$id] = true;

        return true;
    }

    private function checked(bool $condition): string
    {
        return $condition ? ' checked="checked"' : '';
    }

    private function selected(bool $condition): string
    {
        return $condition ? ' selected="selected"' : '';
    }

    private function disabled(bool $condition): string
    {
        return $condition ? ' disabled="disabled"' : '';
    }

    private function readonly(bool $condition): string
    {
        return $condition ? ' readonly="readonly"' : '';
    }

    private function required(bool $condition): string
    {
        return $condition ? ' required="required"' : '';
    }

    private function class(array $classes): string
    {
        $output = [];
        foreach ($classes as $class => $condition) {
            if (is_int($class)) {
                $output[] = $condition;
            } elseif ($condition) {
                $output[] = $class;
            }
        }

        return empty($output) ? '' : ' class="' . implode(' ', $output) . '"';
    }

    private function style(array $styles): string
    {
        $output = [];
        foreach ($styles as $property => $value) {
            if (is_int($property)) {
                $output[] = $value;
            } elseif ($value) {
                $output[] = "$property: $value";
            }
        }

        return empty($output) ? '' : ' style="' . implode('; ', $output) . '"';
    }

    private function json(mixed $data): string
    {
        return json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }

    private function getLayoutPath(): string
    {
        return Paths::layoutPath();
    }
}
