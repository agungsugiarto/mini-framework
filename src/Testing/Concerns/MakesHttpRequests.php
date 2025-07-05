<?php

namespace Mini\Framework\Testing\Concerns;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Testing\Assert as PHPUnit;
use Illuminate\Testing\AssertableJsonString;
use Laminas\Diactoros\UriFactory;
use Mini\Framework\Http\ServerRequestFactory;
use Psr\Http\Message\ServerRequestInterface;

trait MakesHttpRequests
{
    /**
     * The last response returned by the application.
     *
     * @var \Psr\Http\Message\ResponseInterface
     */
    protected $response;

    /**
     * The current URI being viewed.
     *
     * @var string
     */
    protected $currentUri;

    /**
     * Visit the given URI with a JSON request.
     *
     * @param  string  $method
     * @param  string  $uri
     * @return $this
     */
    public function json($method, $uri, array $data = [], array $headers = [])
    {
        $content = json_encode($data);

        $headers = array_merge([
            'CONTENT_LENGTH' => mb_strlen($content, '8bit'),
            'CONTENT_TYPE' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        $this->call(
            $method, $uri, [], [], [], $this->transformHeadersToServerVars($headers), $content
        );

        return $this;
    }

    /**
     * Visit the given URI with a GET request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function get($uri, array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('GET', $uri, [], [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a POST request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function post($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('POST', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a PUT request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function put($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('PUT', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a PATCH request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function patch($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('PATCH', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a DELETE request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function delete($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('DELETE', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a OPTIONS request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function options($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('OPTIONS', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Visit the given URI with a HEAD request.
     *
     * @param  string  $uri
     * @return $this
     */
    public function head($uri, array $data = [], array $headers = [])
    {
        $server = $this->transformHeadersToServerVars($headers);

        $this->call('HEAD', $uri, $data, [], [], $server);

        return $this;
    }

    /**
     * Send the given request through the application.
     *
     * This method allows you to fully customize the entire Request object.
     *
     * @return $this
     */
    public function handle(ServerRequestInterface $request)
    {
        $this->currentUri = $request->getUri()->getPath();

        $this->response = $this->app->prepareResponse($this->app->handle($request));

        return $this;
    }

    /**
     * Assert that the response contains JSON.
     *
     * @return $this
     */
    protected function shouldReturnJson(?array $data = null)
    {
        return $this->receiveJson($data);
    }

    /**
     * Assert that the response contains JSON.
     *
     * @param  array|null  $data
     * @return $this|null
     */
    protected function receiveJson($data = null)
    {
        return $this->seeJson($data);
    }

    /**
     * Assert that the response contains an exact JSON array.
     *
     * @return $this
     */
    public function seeJsonEquals(array $data)
    {
        $actual = json_encode(Arr::sortRecursive(
            json_decode($this->response->getBody()->getContents(), true)
        ));

        $data = json_encode(Arr::sortRecursive(
            json_decode(json_encode($data), true)
        ));

        PHPUnit::assertEquals($data, $actual);

        return $this;
    }

    /**
     * Assert that the response contains JSON.
     *
     * @param  bool  $negate
     * @return $this
     */
    public function seeJson(?array $data = null, $negate = false)
    {
        if (is_null($data)) {
            $decodedResponse = json_decode($this->response->getBody()->getContents(), true);

            if (is_null($decodedResponse) || $decodedResponse === false) {
                PHPUnit::fail(
                    "JSON was not returned from [{$this->currentUri}]."
                );
            }

            return $this->seeJsonContains($decodedResponse, $negate);
        }

        return $this->seeJsonContains($data, $negate);
    }

    /**
     * Assert that the response doesn't contain JSON.
     *
     * @return $this
     */
    public function dontSeeJson(?array $data = null)
    {
        return $this->seeJson($data, true);
    }

    /**
     * Assert that the JSON response has a given structure.
     *
     * @param  array|null  $responseData
     * @return $this
     */
    public function seeJsonStructure(?array $structure = null, $responseData = null)
    {
        $this->assertJsonStructure($structure, $responseData);

        return $this;
    }

    /**
     * Assert that the response has a given JSON structure.
     *
     * @param  array|null  $responseData
     * @return $this
     */
    public function assertJsonStructure(?array $structure = null, $responseData = null)
    {
        $this->decodeResponseJson()->assertStructure($structure, $responseData);

        return $this;
    }

    /**
     * Validate and return the decoded response JSON.
     *
     * @return AssertableJsonString
     *
     * @throws \Throwable
     */
    public function decodeResponseJson()
    {
        $testJson = new AssertableJsonString($this->response->getBody()->getContents());

        $decodedResponse = $testJson->json();

        if (is_null($decodedResponse) || $decodedResponse === false) {
            if ($this->exception) {
                throw $this->exception;
            } else {
                PHPUnit::fail('Invalid JSON was returned from the route.');
            }
        }

        return $testJson;
    }

    /**
     * Assert that the response contains the given JSON.
     *
     * @param  bool  $negate
     * @return $this
     */
    protected function seeJsonContains(array $data, $negate = false)
    {
        if ($negate) {
            $this->assertJsonMissing($data, false);
        } else {
            $this->assertJsonFragment($data);
        }

        return $this;
    }

    /**
     * Assert that the response does not contain the given JSON fragment.
     *
     * @param  bool  $exact
     * @return $this
     */
    public function assertJsonMissing(array $data, $exact = false)
    {
        $this->decodeResponseJson()->assertMissing($data, $exact);

        return $this;
    }

    /**
     * Assert that the response contains the given JSON fragment.
     *
     * @return $this
     */
    public function assertJsonFragment(array $data)
    {
        $this->decodeResponseJson()->assertFragment($data);

        return $this;
    }

    /**
     * Assert that the response doesn't contain the given JSON.
     *
     * @return $this
     */
    protected function seeJsonDoesntContains(array $data)
    {
        $this->assertJsonMissing($data, false);

        return $this;
    }

    /**
     * Format the given key and value into a JSON string for expectation checks.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return string
     */
    protected function formatToExpectedJson($key, $value)
    {
        $expected = json_encode([$key => $value]);

        if (Str::startsWith($expected, '{')) {
            $expected = substr($expected, 1);
        }

        if (Str::endsWith($expected, '}')) {
            $expected = substr($expected, 0, -1);
        }

        return $expected;
    }

    /**
     * Call the given URI and return the Response.
     *
     * @param  string  $method
     * @param  string  $uri
     * @param  array  $parameters
     * @param  array  $cookies
     * @param  array  $files
     * @param  array  $server
     * @param  string  $content
     * @return \Illuminate\Testing\TestResponse
     */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $this->currentUri = $this->prepareUrlForRequest($uri);

        $this->app['request'] = ServerRequestFactory::fromGlobals(
            $server,
            $parameters,
            $content,
            $cookies,
            $files
        )
            ->withMethod($method)
            ->withUri((new UriFactory)->createUri($uri));

        return $this->response = $this->app->prepareResponse($this->app->handle($this->app['request']));
    }

    /**
     * Turn the given URI into a fully qualified URL.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prepareUrlForRequest($uri)
    {
        if (Str::startsWith($uri, '/')) {
            $uri = substr($uri, 1);
        }

        if (! Str::startsWith($uri, 'http')) {
            $uri = $this->baseUrl.'/'.$uri;
        }

        return trim($uri, '/');
    }

    /**
     * Transform headers array to array of $_SERVER vars with HTTP_* format.
     *
     * @return array
     */
    protected function transformHeadersToServerVars(array $headers)
    {
        $server = [];
        $prefix = 'HTTP_';

        foreach ($headers as $name => $value) {
            $name = strtr(strtoupper($name), '-', '_');

            if (! Str::startsWith($name, $prefix) && $name != 'CONTENT_TYPE') {
                $name = $prefix.$name;
            }

            $server[$name] = $value;
        }

        return $server;
    }

    /**
     * Assert that the client response has an OK status code.
     *
     * @return void
     */
    public function assertResponseOk()
    {
        $this->assertStatus(200);
    }

    /**
     * Assert that the client response has a given status code.
     *
     * @param  int  $status
     * @return void
     */
    public function assertResponseStatus($status)
    {
        $this->assertStatus($status);
    }

    /**
     * Asserts that the status code of the response matches the given code.
     *
     * @param  int  $status
     * @return $this
     */
    protected function seeStatusCode($status)
    {
        $this->assertResponseStatus($status);

        return $this;
    }

    /**
     * Asserts that the response contains the given header and equals the optional value.
     *
     * @param  string  $headerName
     * @param  mixed  $value
     * @return $this
     */
    protected function seeHeader($headerName, $value = null)
    {
        $this->assertHeader($headerName, $value);

        return $this;
    }

    /**
     * Asserts that the response contains the given header and equals the optional value.
     *
     * @param  string  $headerName
     * @param  mixed  $value
     * @return $this
     */
    public function assertHeader($headerName, $value = null)
    {
        PHPUnit::assertTrue(
            $this->response->hasHeader($headerName), "Header [{$headerName}] not present on response."
        );

        $actual = $this->response->getHeaderLine($headerName);

        if (! is_null($value)) {
            PHPUnit::assertEquals(
                $value, $this->response->getHeaderLine($headerName),
                "Header [{$headerName}] was found, but value [{$actual}] does not match [{$value}]."
            );
        }

        return $this;
    }

    /**
     * Assert that the response has the given status code.
     *
     * @param  int  $status
     * @return $this
     */
    public function assertStatus($status)
    {
        $message = $this->statusMessageWithDetails($status, $actual = $this->response->getStatusCode());

        PHPUnit::assertSame($actual, $status, $message);

        return $this;
    }

    /**
     * Get an assertion message for a status assertion containing extra details when available.
     *
     * @param  string|int  $expected
     * @param  string|int  $actual
     * @return string
     */
    protected function statusMessageWithDetails($expected, $actual)
    {
        return "Expected response status code [{$expected}] but received {$actual}.";
    }

    /**
     * Disable middleware for the test.
     *
     * @return $this
     */
    public function withoutMiddleware()
    {
        $this->app->instance('middleware.disable', true);

        return $this;
    }
}
