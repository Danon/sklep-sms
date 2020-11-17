<?php
namespace Tests\Feature\Http\View\Shop;

use Symfony\Component\HttpFoundation\Response;
use Tests\Psr4\TestCases\HttpTestCase;

class ContactTest extends HttpTestCase
{
    /** @test */
    public function it_loads()
    {
        // given

        // when
        $response = $this->get("/page/contact");

        // then
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertContains("Kontakt", $response->getContent());
    }
}
