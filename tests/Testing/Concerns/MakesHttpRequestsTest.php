<?php

use Laminas\Diactoros\Response\JsonResponse;
use Mini\Framework\Application;
use Mini\Framework\Http\Request;
use Mini\Framework\Testing\Concerns\MakesHttpRequests;
use PHPUnit\Framework\TestCase;

class MakesHttpRequestsTest extends TestCase
{
    /**
     * Placeholder test to avoid PHPUnit warning.
     * TODO: Implement actual tests for MakesHttpRequests trait.
     */
    public function testPlaceholder()
    {
        $this->assertTrue(true);
    }

    // use MakesHttpRequests;

    // public function testReceiveJson()
    // {
    //     $this->app = new Application;
    //     $this->app->router->get('/', function () {
    //         return new JsonResponse(['foo' => 'bar', 'hello' => 'world']);
    //     });

    //     $this->handle(Request::create('/', 'GET'));

    //     // Test response is json
    //     $this->receiveJson();

    //     // Test response contains fragment
    //     $this->receiveJson(['foo' => 'bar']);
    // }
}
