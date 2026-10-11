<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiDocumentationTest extends TestCase
{
    private string $docsDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        // Exercise first-request generation without overwriting local documentation.
        $this->docsDirectory = sys_get_temp_dir().'/medicare-openapi-'.bin2hex(random_bytes(6));
        config(['l5-swagger.defaults.paths.docs' => $this->docsDirectory]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->docsDirectory);
        parent::tearDown();
    }

    public function test_documentation_generates_and_covers_every_named_api_operation(): void
    {
        $spec = $this->getJson('/docs')->assertOk()->json();
        $documented = [];
        foreach ($spec['paths'] as $path => $operations) {
            foreach (array_keys($operations) as $method) {
                $documented[] = strtoupper($method).' '.$path;
            }
        }

        $actual = [];
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'api.')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $actual[] = $method.' /'.substr($route->uri(), strlen('api/'));
            }
        }
        sort($documented);
        sort($actual);
        $this->assertSame($actual, $documented);
        $this->assertSame('/api', $spec['servers'][0]['url']);
        $this->assertSame('bearer', $spec['components']['securitySchemes']['bearerAuth']['scheme']);
        $this->assertSame(['email', 'password'], $spec['components']['schemas']['Login']['required']);
    }

    public function test_swagger_ui_is_available_without_exposing_sample_credentials(): void
    {
        $this->get('/api/doc')->assertOk()->assertSee('SwaggerUIBundle', false)
            ->assertDontSee('LearningDemo!2026');
    }
}
