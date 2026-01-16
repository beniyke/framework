<?php

declare(strict_types=1);

namespace Testing\Concerns;

use Core\Kernel;
use Core\Services\ConfigServiceInterface;
use Core\Support\Adapters\Interfaces\SapiInterface;
use Helpers\Http\Request;
use Helpers\Http\Response;
use Helpers\Http\Session;
use Helpers\Http\UserAgent;
use PHPUnit\Framework\Assert as PHPUnit;

trait MakesHttpRequests
{
    /**
     * The last response received by the test.
     */
    protected ?Response $lastResponse = null;

    /**
     * Visit the given URI with a GET request.
     */
    public function get(string $uri, array $headers = []): Response
    {
        return $this->call('GET', $uri, [], [], [], $headers);
    }

    /**
     * Visit the given URI with a POST request.
     */
    public function post(string $uri, array $data = [], array $headers = []): Response
    {
        return $this->call('POST', $uri, $data, [], [], $headers);
    }

    /**
     * Visit the given URI with a JSON request.
     */
    public function json(string $method, string $uri, array $data = [], array $headers = []): Response
    {
        $headers['CONTENT_TYPE'] = 'application/json';
        $headers['HTTP_ACCEPT'] = 'application/json';

        $content = json_encode($data);

        return $this->call($method, $uri, [], [], [], $headers, $content);
    }

    /**
     * Call the given URI and return the Response.
     */
    public function call(string $method, string $uri, array $parameters = [], array $cookies = [], array $files = [], array $server = [], ?string $content = null): Response
    {
        // Mock the request environment
        $_SERVER = array_merge($_SERVER, $server);
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REQUEST_URI'] = $uri;

        if (strtoupper($method) === 'POST') {
            $_POST = $parameters;
        } else {
            $_GET = $parameters;
        }

        $request = Request::createFromGlobals(
            resolve(ConfigServiceInterface::class),
            resolve(SapiInterface::class),
            resolve(Session::class),
            resolve(UserAgent::class),
            $content
        );

        $kernel = resolve(Kernel::class);
        $this->lastResponse = $kernel->handle($request);

        return $this->lastResponse;
    }

    /**
     * Assert the response status code.
     */
    protected function assertStatus(int $status): self
    {
        PHPUnit::assertEquals($status, $this->lastResponse->getStatusCode());

        return $this;
    }

    /**
     * Assert the response is successful (2xx).
     */
    protected function assertSuccessful(): self
    {
        PHPUnit::assertTrue($this->lastResponse->isSuccessful($this->lastResponse->getStatusCode()));

        return $this;
    }

    /**
     * Assert the response contains the given string.
     */
    protected function assertSee(string $value, bool $escape = true): self
    {
        $content = $this->lastResponse->getContent();
        $value = $escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false) : $value;

        PHPUnit::assertStringContainsString($value, $content);

        return $this;
    }

    /**
     * Assert the response JSON matches the given structure.
     */
    protected function assertJsonFragment(array $data): self
    {
        $actual = json_decode($this->lastResponse->getContent(), true);
        PHPUnit::assertIsArray($actual, 'Response is not valid JSON');

        foreach ($data as $key => $value) {
            PHPUnit::assertArrayHasKey($key, $actual);
            PHPUnit::assertEquals($value, $actual[$key]);
        }

        return $this;
    }

    /**
     * Assert the response has status code 200.
     */
    protected function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    /**
     * Assert the response has status code 201.
     */
    protected function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    /**
     * Assert the response has status code 202.
     */
    protected function assertAccepted(): self
    {
        return $this->assertStatus(202);
    }

    /**
     * Assert the response has status code 204.
     */
    protected function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    /**
     * Assert the response has status code 404.
     */
    protected function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    /**
     * Assert the response has status code 403.
     */
    protected function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    /**
     * Assert the response has status code 401.
     */
    protected function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    /**
     * Assert the response has status code 422.
     */
    protected function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    /**
     * Assert the response has status code 500.
     */
    protected function assertServerError(): self
    {
        return $this->assertStatus(500);
    }

    /**
     * Assert the response does not contain the given string.
     */
    protected function assertDontSee(string $value, bool $escape = true): self
    {
        $content = $this->lastResponse->getContent();
        $value = $escape ? htmlspecialchars($value, ENT_QUOTES, 'UTF-8', false) : $value;

        PHPUnit::assertStringNotContainsString($value, $content);

        return $this;
    }

    /**
     * Assert the response is a redirect.
     */
    protected function assertRedirect(?string $uri = null): self
    {
        $statusCode = $this->lastResponse->getStatusCode();
        PHPUnit::assertTrue(
            $statusCode >= 300 && $statusCode < 400,
            "Response status code [{$statusCode}] is not a redirect status code."
        );

        if ($uri !== null) {
            $location = $this->lastResponse->getHeader('Location');
            PHPUnit::assertEquals($uri, $location, 'Redirect location does not match.');
        }

        return $this;
    }

    /**
     * Assert the response contains the given JSON data.
     */
    protected function assertJsonData(array $data): self
    {
        $actual = json_decode($this->lastResponse->getContent(), true);
        PHPUnit::assertIsArray($actual, 'Response is not valid JSON');

        foreach ($data as $key => $value) {
            PHPUnit::assertArrayHasKey($key, $actual, "Missing expected key [{$key}] in JSON response.");
            if (is_array($value)) {
                PHPUnit::assertEquals($value, $actual[$key]);
            } else {
                PHPUnit::assertSame($value, $actual[$key]);
            }
        }

        return $this;
    }

    /**
     * Assert the response JSON exactly matches the given data.
     */
    protected function assertExactJsonData(array $data): self
    {
        $actual = json_decode($this->lastResponse->getContent(), true);
        PHPUnit::assertIsArray($actual, 'Response is not valid JSON');
        PHPUnit::assertEquals($data, $actual, 'JSON does not exactly match.');

        return $this;
    }

    /**
     * Assert the response JSON has the given structure.
     */
    protected function assertJsonStructure(array $structure, ?array $responseData = null): self
    {
        $responseData = $responseData ?? json_decode($this->lastResponse->getContent(), true);
        PHPUnit::assertIsArray($responseData, 'Response is not valid JSON');

        foreach ($structure as $key => $value) {
            if (is_array($value) && $key === '*') {
                // Wildcard: Check structure of all items in array
                PHPUnit::assertIsArray($responseData, 'Expected array for wildcard structure.');
                foreach ($responseData as $item) {
                    $this->assertJsonStructure($value, $item);
                }
            } elseif (is_array($value)) {
                // Nested structure
                PHPUnit::assertArrayHasKey($key, $responseData, "Missing expected key [{$key}] in JSON structure.");
                $this->assertJsonStructure($value, $responseData[$key]);
            } else {
                // Simple key
                PHPUnit::assertArrayHasKey($value, $responseData, "Missing expected key [{$value}] in JSON structure.");
            }
        }

        return $this;
    }

    /**
     * Assert the response JSON array count.
     */
    protected function assertJsonCount(int $count, ?string $key = null): self
    {
        $actual = json_decode($this->lastResponse->getContent(), true);
        PHPUnit::assertIsArray($actual, 'Response is not valid JSON');

        if ($key !== null) {
            PHPUnit::assertArrayHasKey($key, $actual, "Key [{$key}] not found in JSON.");
            $actual = $actual[$key];
            PHPUnit::assertIsArray($actual, "Key [{$key}] is not an array.");
        }

        PHPUnit::assertCount($count, $actual, "Expected JSON count of {$count}.");

        return $this;
    }

    /**
     * Assert the response has a header.
     */
    protected function assertHeader(string $name, ?string $value = null): self
    {
        $header = $this->lastResponse->getHeader($name);
        PHPUnit::assertNotNull($header, "Header [{$name}] is not present in response.");

        if ($value !== null) {
            PHPUnit::assertEquals($value, $header, "Header [{$name}] does not match expected value.");
        }

        return $this;
    }

    /**
     * Assert the response does not have a header.
     */
    protected function assertHeaderMissing(string $name): self
    {
        $header = $this->lastResponse->getHeader($name);
        PHPUnit::assertNull($header, "Header [{$name}] is present but was not expected.");

        return $this;
    }

    /**
     * Assert the response has a cookie.
     */
    protected function assertCookie(string $name, ?string $value = null): self
    {
        $cookie = $this->lastResponse->getCookie($name);
        PHPUnit::assertNotNull($cookie, "Cookie [{$name}] is not present in response.");

        if ($value !== null) {
            PHPUnit::assertEquals($value, $cookie, "Cookie [{$name}] does not match expected value.");
        }

        return $this;
    }

    /**
     * Assert the response does not have a cookie.
     */
    protected function assertCookieMissing(string $name): self
    {
        $cookie = $this->lastResponse->getCookie($name);
        PHPUnit::assertNull($cookie, "Cookie [{$name}] is present but was not expected.");

        return $this;
    }
}
