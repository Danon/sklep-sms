<?php
namespace Tests\Feature\Http\View\Admin;

use Tests\Psr4\Concerns\AuthConcern;
use Tests\Psr4\TestCases\IndexTestCase;

class ServicesTest extends IndexTestCase
{
    use AuthConcern;

    /** @test */
    public function it_loads()
    {
        // given
        $user = $this->factory->user();
        $this->actingAs($user);

        // when
        $response = $this->get('/admin.php', ['pid' => 'services']);

        // then
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertContains('Panel Admina', $response->getContent());
        $this->assertContains('<div class="title is-4">Usługi', $response->getContent());
    }
}