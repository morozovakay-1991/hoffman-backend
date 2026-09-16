<?php

namespace Tests\Feature\Docs;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Tests\TestCase;

/**
 * Generating the OpenAPI document performs static analysis over every
 * documented route and comfortably adds tens of megabytes that Scramble
 * never releases for the lifetime of the process. Each test below runs in
 * its own process so that footprint doesn't accumulate onto the rest of the
 * suite (which otherwise runs in a single PHP process under the default
 * 128M CLI memory_limit).
 */
class ApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    #[RunInSeparateProcess]
    public function test_docs_page_is_reachable_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/docs/api');

        $response->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_docs_json_is_reachable_and_valid_in_local_environment(): void
    {
        $this->app['env'] = 'local';

        $response = $this->getJson('/docs/api.json');

        $response->assertOk()
            ->assertJsonStructure(['openapi', 'info', 'paths'])
            ->assertJsonPath('openapi', '3.1.0');

        $paths = $response->json('paths');

        $this->assertArrayHasKey('/auth/login', $paths);
        $this->assertArrayHasKey('/diary/days/{dayNumber}', $paths);

        foreach (array_keys($paths) as $path) {
            $this->assertStringNotContainsString('webhook', $path);
        }
    }

    #[RunInSeparateProcess]
    public function test_docs_are_reachable_in_staging_environment(): void
    {
        $this->app['env'] = 'staging';

        $this->getJson('/docs/api.json')->assertOk();
    }

    #[RunInSeparateProcess]
    public function test_docs_are_blocked_in_production_environment(): void
    {
        $this->app['env'] = 'production';

        $this->get('/docs/api')->assertForbidden();
        $this->getJson('/docs/api.json')->assertForbidden();
    }
}
