<?php

use Mini\Framework\Http\Middleware\Cors\CorsService;
use PHPUnit\Framework\TestCase;

class CorsServiceTest extends TestCase
{
    /**
     * @test
     */
    public function it_can_have_options(): void
    {
        $options = [
            'allowedOrigins' => ['localhost'],
            'allowedOriginsPatterns' => ['/something/'],
            'allowedHeaders' => ['x-custom'],
            'allowedMethods' => ['PUT'],
            'maxAge' => 684,
            'supportsCredentials' => true,
            'exposedHeaders' => ['x-custom-2'],
        ];

        $service = new CorsService($options);

        $this->assertInstanceOf(CorsService::class, $service);

        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals($options['allowedOrigins'], $normalized['allowedOrigins']);
        $this->assertEquals($options['allowedOriginsPatterns'], $normalized['allowedOriginsPatterns']);
        $this->assertEquals($options['allowedHeaders'], $normalized['allowedHeaders']);
        $this->assertEquals($options['allowedMethods'], $normalized['allowedMethods']);
        $this->assertEquals($options['maxAge'], $normalized['maxAge']);
        $this->assertEquals($options['supportsCredentials'], $normalized['supportsCredentials']);
        $this->assertEquals($options['exposedHeaders'], $normalized['exposedHeaders']);
    }

    /**
     * @test
     */
    public function it_can_set_options(): void
    {
        $service = new CorsService;
        $normalized = $this->getOptionsFromService($service);
        $this->assertEquals([], $normalized['allowedOrigins']);

        $this->assertInstanceOf(CorsService::class, $service);

        $options = [
            'allowedOrigins' => ['localhost'],
            'allowedOriginsPatterns' => ['/something/'],
            'allowedHeaders' => ['x-custom'],
            'allowedMethods' => ['PUT'],
            'maxAge' => 684,
            'supportsCredentials' => true,
            'exposedHeaders' => ['x-custom-2'],
        ];

        $service->setOptions($options);

        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals($options['allowedOrigins'], $normalized['allowedOrigins']);
        $this->assertEquals($options['allowedOriginsPatterns'], $normalized['allowedOriginsPatterns']);
        $this->assertEquals($options['allowedHeaders'], $normalized['allowedHeaders']);
        $this->assertEquals($options['allowedMethods'], $normalized['allowedMethods']);
        $this->assertEquals($options['maxAge'], $normalized['maxAge']);
        $this->assertEquals($options['supportsCredentials'], $normalized['supportsCredentials']);
        $this->assertEquals($options['exposedHeaders'], $normalized['exposedHeaders']);
    }

    /**
     * @test
     */
    public function it_can_overwrite_set_options(): void
    {
        $service = new CorsService(['allowedOrigins' => ['example.com']]);
        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals(['example.com'], $normalized['allowedOrigins']);

        $this->assertInstanceOf(CorsService::class, $service);

        $options = [
            'allowedOrigins' => ['localhost'],
            'allowedOriginsPatterns' => ['/something/'],
            'allowedHeaders' => ['x-custom'],
            'allowedMethods' => ['PUT'],
            'maxAge' => 684,
            'supportsCredentials' => true,
            'exposedHeaders' => ['x-custom-2'],
        ];

        $service->setOptions($options);

        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals($options['allowedOrigins'], $normalized['allowedOrigins']);
        $this->assertEquals($options['allowedOriginsPatterns'], $normalized['allowedOriginsPatterns']);
        $this->assertEquals($options['allowedHeaders'], $normalized['allowedHeaders']);
        $this->assertEquals($options['allowedMethods'], $normalized['allowedMethods']);
        $this->assertEquals($options['maxAge'], $normalized['maxAge']);
        $this->assertEquals($options['supportsCredentials'], $normalized['supportsCredentials']);
        $this->assertEquals($options['exposedHeaders'], $normalized['exposedHeaders']);
    }

    /**
     * @test
     */
    public function it_can_have_no_options(): void
    {
        $service = new CorsService;
        $this->assertInstanceOf(CorsService::class, $service);

        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals([], $normalized['allowedOrigins']);
        $this->assertEquals([], $normalized['allowedOriginsPatterns']);
        $this->assertEquals([], $normalized['allowedHeaders']);
        $this->assertEquals([], $normalized['allowedMethods']);
        $this->assertEquals([], $normalized['exposedHeaders']);
        $this->assertEquals(0, $normalized['maxAge']);
        $this->assertEquals(false, $normalized['supportsCredentials']);
    }

    /**
     * @test
     */
    public function it_can_have_empty_options(): void
    {
        $service = new CorsService([]);
        $this->assertInstanceOf(CorsService::class, $service);

        $normalized = $this->getOptionsFromService($service);

        $this->assertEquals([], $normalized['allowedOrigins']);
        $this->assertEquals([], $normalized['allowedOriginsPatterns']);
        $this->assertEquals([], $normalized['allowedHeaders']);
        $this->assertEquals([], $normalized['allowedMethods']);
        $this->assertEquals([], $normalized['exposedHeaders']);
        $this->assertEquals(0, $normalized['maxAge']);
        $this->assertEquals(false, $normalized['supportsCredentials']);
    }

    /**
     * @test
     */
    public function it_normalizes_false_exposed_headers(): void
    {
        $service = new CorsService(['exposedHeaders' => false]);
        $this->assertEquals([], $this->getOptionsFromService($service)['exposedHeaders']);
    }

    /**
     * @test
     */
    public function it_allows_null_max_age(): void
    {
        $service = new CorsService(['maxAge' => null]);
        $this->assertNull($this->getOptionsFromService($service)['maxAge']);
    }

    /**
     * @test
     */
    public function it_allows_zero_max_age(): void
    {
        $service = new CorsService(['maxAge' => 0]);
        $this->assertEquals(0, $this->getOptionsFromService($service)['maxAge']);
    }

    /**
     * @test
     */
    public function it_throws_exception_on_invalid_exposed_headers(): void
    {
        $this->expectException(TypeError::class);

        /** @phpstan-ignore-next-line */
        $service = new CorsService(['exposedHeaders' => true]);
    }

    /**
     * @test
     */
    public function it_throws_exception_on_invalid_origins_array(): void
    {
        $this->expectException(TypeError::class);

        /** @phpstan-ignore-next-line */
        $service = new CorsService(['allowedOrigins' => 'string']);
    }

    /**
     * @test
     */
    public function it_normalizes_wildcard_origins(): void
    {
        $service = new CorsService(['allowedOrigins' => ['*']]);
        $this->assertInstanceOf(CorsService::class, $service);

        $this->assertTrue($this->getOptionsFromService($service)['allowAllOrigins']);
    }

    /**
     * @test
     */
    public function it_normalizes_wildcard_headers(): void
    {
        $service = new CorsService(['allowedHeaders' => ['*']]);
        $this->assertInstanceOf(CorsService::class, $service);

        $this->assertTrue($this->getOptionsFromService($service)['allowAllHeaders']);
    }

    /**
     * @test
     */
    public function it_normalizes_wildcard_methods(): void
    {
        $service = new CorsService(['allowedMethods' => ['*']]);
        $this->assertInstanceOf(CorsService::class, $service);

        $this->assertTrue($this->getOptionsFromService($service)['allowAllMethods']);
    }

    /**
     * @test
     */
    public function it_converts_wildcard_origin_patterns(): void
    {
        $service = new CorsService(['allowedOrigins' => ['*.mydomain.com']]);
        $this->assertInstanceOf(CorsService::class, $service);

        $patterns = $this->getOptionsFromService($service)['allowedOriginsPatterns'];
        $this->assertEquals(['#^.*\.mydomain\.com\z#u'], $patterns);
    }

    /**
     * @test
     */
    public function it_normalizes_underscore_options(): void
    {
        $options = [
            'allowed_origins' => ['localhost'],
            'allowed_origins_patterns' => ['/something/'],
            'allowed_headers' => ['x-custom'],
            'allowed_methods' => ['PUT'],
            'max_age' => 684,
            'supports_credentials' => true,
            'exposed_headers' => ['x-custom-2'],
        ];

        $service = new CorsService($options);
        $this->assertInstanceOf(CorsService::class, $service);

        $this->assertEquals($options['allowed_origins'], $this->getOptionsFromService($service)['allowedOrigins']);
        $this->assertEquals(
            $options['allowed_origins_patterns'],
            $this->getOptionsFromService($service)['allowedOriginsPatterns']
        );
        $this->assertEquals($options['allowed_headers'], $this->getOptionsFromService($service)['allowedHeaders']);
        $this->assertEquals($options['allowed_methods'], $this->getOptionsFromService($service)['allowedMethods']);
        $this->assertEquals($options['exposed_headers'], $this->getOptionsFromService($service)['exposedHeaders']);
        $this->assertEquals($options['max_age'], $this->getOptionsFromService($service)['maxAge']);
        $this->assertEquals(
            $options['supports_credentials'],
            $this->getOptionsFromService($service)['supportsCredentials']
        );
    }

    /**
     * @return CorsNormalizedOptions
     */
    private function getOptionsFromService(CorsService $service): array
    {
        $reflected = new ReflectionClass($service);

        $properties = $reflected->getProperties(ReflectionProperty::IS_PRIVATE);

        $options = [];
        foreach ($properties as $property) {
            $property->setAccessible(true);
            $options[$property->getName()] = $property->getValue($service);
        }

        /* @var CorsNormalizedOptions $options */
        return $options;
    }
}
