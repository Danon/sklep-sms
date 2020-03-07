<?php
namespace Tests\Unit\Application;

use Tests\Psr4\TestCases\TestCase;

class VersionTest extends TestCase
{
    /** @test */
    public function version_is_ok()
    {
        $this->assertEquals('3.12.0-rc.4', $this->app->version());
    }
}
