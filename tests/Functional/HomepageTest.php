<?php

declare(strict_types=1);

namespace Tests\Functional;

class HomepageTest extends BaseTestCase
{
    /**
     * Test that the index route returns a rendered response containing the text 'SlimFramework' but not a greeting
     */
    public function testGetHomepage(): void
    {
        $response = $this->runApp('GET', '/cej2018');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Registrace pro Central European Jamboree 2018', (string) $response->getBody());
        $this->assertNotContains('Hello', (string) $response->getBody());
    }
}
