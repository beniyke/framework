<?php

declare(strict_types=1);

/**
 * Anchor Framework
 *
 * Class SmartValidationMiddleware implementation.
 *
 * @author BenIyke <beniyke34@gmail.com> | Twitter: @BigBeniyke
 */

namespace Core\Middleware;

use Closure;
use Core\Ioc\ContainerInterface;
use Exceptions\ValidationException;
use Helpers\File\Paths;
use Helpers\Http\Flash;
use Helpers\Http\Request;
use Helpers\Http\Response;

class SmartValidationMiddleware implements MiddlewareInterface
{
    protected Flash $flash;

    protected ContainerInterface $container;

    public function __construct(Flash $flash, ContainerInterface $container)
    {
        $this->flash = $flash;
        $this->container = $container;
    }

    public function handle(Request $request, Response $response, Closure $next): Response
    {
        $request->setValidatorResolver(fn (string $class) => $this->container->make($class));
        $request->setValidationFailureHandler(function (Request $req, $validator) {
            throw new ValidationException('The given data was invalid.', $validator->errors());
        });

        try {
            $class = $request->getRouteContext('validator');

            if (! $class) {
                // Get Context from Route Context System
                $domain = $request->getRouteContext('domain');
                $entity = $request->getRouteContext('entity');
                $action = $request->getRouteContext('action');

                if ($domain && $entity && $action) {
                    $domain = ucfirst($domain);
                    $entity = ucfirst($entity);

                    // Resolve Class Name (Supports Form and Api variants)
                    $type = $request->routeIsApi() ? 'Api' : 'Form';
                    $namespace = "App\\{$domain}\\Validations\\{$type}";

                    // Try variations: {Entity}, {Action}{Entity}, Search{Entity}
                    if ($action === 'index') {
                        $options = ["Search{$entity}", $entity];
                    } else {
                        $options = [$entity, ucfirst($action) . $entity];
                    }

                    foreach ($options as $option) {
                        $testClass = "{$namespace}\\{$option}{$type}RequestValidation";
                        if (class_exists($testClass)) {
                            $class = $testClass;
                            break;
                        }

                        // Case-insensitive fallback for Linux/Case-sensitive systems
                        $resolved = $this->findCaseInsensitiveValidator($namespace, "{$option}{$type}RequestValidation");

                        if ($resolved) {
                            $class = $resolved;
                            break;
                        }
                    }
                }
            }

            if ($class && class_exists($class) && ! $request->validationAlreadyPerformed()) {
                $isDiscoveryExemption = str_contains($class, 'Search') || str_contains($class, 'Filter');

                if (! $request->isStateChanging() && ! $request->getRouteContext('validator') && ! $isDiscoveryExemption) {
                    return $next($request, $response);
                }

                $validator = $this->container->make($class);
                $validator->validate($request->all());

                if ($validator->has_error()) {
                    return $this->handleFailure($request, $response, $validator->errors());
                }

                if (method_exists($validator, 'getRequest')) {
                    $request->setValidatedRequest($validator->getRequest());
                }
            }

            return $next($request, $response);
        } catch (ValidationException $e) {
            return $this->handleFailure($request, $response, $e->getErrors());
        }
    }

    protected function handleFailure(Request $request, Response $response, array $errors): Response
    {
        if ($request->routeIsApi() || $request->isAjax()) {
            return $response->status(422)->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ]);
        }

        $this->flash->withInput($request->all(), $errors);

        return $response->redirect($request->callback());
    }

    protected function findCaseInsensitiveValidator(string $namespace, string $className): ?string
    {
        $path = str_replace(['App\\', '\\'], ['', DIRECTORY_SEPARATOR], $namespace);
        $dir = Paths::appSourcePath($path);

        if (! is_dir($dir)) {
            return null;
        }

        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $currentClass = pathinfo($file, PATHINFO_FILENAME);

            if (strcasecmp($currentClass, $className) === 0) {
                return "{$namespace}\\{$currentClass}";
            }
        }

        return null;
    }
}
