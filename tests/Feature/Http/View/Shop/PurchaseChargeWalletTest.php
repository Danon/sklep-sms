<?php
namespace Tests\Feature\Http\View\Shop;

use Tests\Psr4\TestCases\HttpTestCase;

class PurchaseChargeWalletTest extends HttpTestCase
{
    /** @test */
    public function it_loads()
    {
        // given
        $user = $this->factory->user();
        $this->actingAs($user);

        // when
        $response = $this->get('/page/purchase', ['service' => 'charge_wallet']);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains('Zakup usługi - Doładowanie Portfela', $response->getContent());
    }

    /** @test */
    public function requires_being_logged()
    {
        // when
        $response = $this->get('/page/purchase', ['service' => 'charge_wallet']);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertContains(
            'Nie możesz przeglądać tej strony. Nie jesteś zalogowany/a.',
            $response->getContent()
        );
    }
}
