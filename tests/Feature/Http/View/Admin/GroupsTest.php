<?php
namespace Tests\Feature\Http\View\Admin;

use Tests\Psr4\TestCases\HttpTestCase;

class GroupsTest extends HttpTestCase
{
    /** @test */
    public function it_loads()
    {
        // given
        $this->actingAs($this->factory->admin());

        // when
        $response = $this->get('/admin/groups');

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Panel Admina', $response->getContent());
        $this->assertContains('<div class="title is-4">Grupy', $response->getContent());
    }
}
