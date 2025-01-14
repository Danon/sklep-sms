<?php
namespace Tests\Feature\Http\Api\Server;

use App\Exceptions\LicenseRequestException;
use App\Models\Price;
use App\Models\Server;
use App\Payment\General\PaymentMethod;
use App\Repositories\BoughtServiceRepository;
use App\Repositories\UserRepository;
use App\Server\Platform;
use App\ServiceModules\ExtraFlags\ExtraFlagType;
use App\System\License;
use Tests\Psr4\TestCases\HttpTestCase;

class PurchaseResourceWalletTest extends HttpTestCase
{
    private BoughtServiceRepository $boughtServiceRepository;
    private UserRepository $userRepository;
    private Server $server;
    private Price $price;
    private string $serviceId = "vip";
    private string $ip = "192.0.2.1";
    private string $steamId = "STEAM_1:0:22309350";

    protected function setUp(): void
    {
        parent::setUp();

        $this->boughtServiceRepository = $this->app->make(BoughtServiceRepository::class);
        $this->userRepository = $this->app->make(UserRepository::class);

        $this->server = $this->factory->server();
        $this->factory->serverService([
            "server_id" => $this->server->getId(),
            "service_id" => $this->serviceId,
        ]);
        $this->price = $this->factory->price([
            "service_id" => $this->serviceId,
            "server_id" => $this->server->getId(),
            "transfer_price" => 100,
        ]);
    }

    /** @test */
    public function purchase_using_wallet()
    {
        // given
        $user = $this->factory->user([
            "steam_id" => $this->steamId,
            "wallet" => 10000,
        ]);

        $sign = md5(
            implode("#", [ExtraFlagType::TYPE_SID, $this->steamId, "", $this->server->getToken()])
        );

        // when
        $response = $this->post(
            "/api/server/purchase",
            [
                "auth_data" => $this->steamId,
                "ip" => $this->ip,
                "method" => PaymentMethod::WALLET()->getValue(),
                "price_id" => $this->price->getId(),
                "service_id" => $this->serviceId,
                "sign" => $sign,
                "type" => ExtraFlagType::TYPE_SID,
            ],
            [
                "token" => $this->server->getToken(),
            ],
            [
                "Authorization" => "Bearer {$this->steamId}",
                "User-Agent" => Platform::AMXMODX,
            ]
        );

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            "#<return_value>purchased</return_value><text>Usługa została prawidłowo zakupiona\.</text><positive>1</positive><bsid>\d+</bsid>#",
            $response->getContent()
        );

        preg_match("#<bsid>(\d+)</bsid>#", $response->getContent(), $matches);
        $boughtServiceId = (int) $matches[1];
        $boughtService = $this->boughtServiceRepository->get($boughtServiceId);
        $this->assertNotNull($boughtService);
        $this->assertSameEnum(PaymentMethod::WALLET(), $boughtService->getMethod());

        $freshUser = $this->userRepository->get($user->getId());
        $this->assertEqualsMoney(9900, $freshUser->getWallet());
        $this->assertEquals($this->ip, $freshUser->getLastIp());
    }

    /** @test */
    public function cannot_purchase_using_wallet_if_not_enough_money()
    {
        // given
        $user = $this->factory->user([
            "steam_id" => $this->steamId,
            "wallet" => 99,
        ]);

        $sign = md5(
            implode("#", [ExtraFlagType::TYPE_SID, $this->steamId, "", $this->server->getToken()])
        );

        // when
        $response = $this->post(
            "/api/server/purchase",
            [
                "auth_data" => $this->steamId,
                "ip" => $this->ip,
                "method" => PaymentMethod::WALLET()->getValue(),
                "price_id" => $this->price->getId(),
                "service_id" => $this->serviceId,
                "sign" => $sign,
                "type" => ExtraFlagType::TYPE_SID,
            ],
            [
                "token" => $this->server->getToken(),
            ],
            [
                "Authorization" => "Bearer {$this->steamId}",
                "User-Agent" => Platform::AMXMODX,
            ]
        );

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            "#<return_value>no_money</return_value><text>Bida! Nie masz wystarczającej ilości kasy w portfelu\. Doładuj portfel ;-\)</text><positive>0</positive>#",
            $response->getContent()
        );

        $freshUser = $this->userRepository->get($user->getId());
        $this->assertEqualsMoney(99, $freshUser->getWallet());
    }

    /** @test */
    public function cannot_purchase_using_wallet_if_not_authorized()
    {
        // given
        $sign = md5(
            implode("#", [ExtraFlagType::TYPE_SID, $this->steamId, "", $this->server->getToken()])
        );

        // when
        $response = $this->post(
            "/api/server/purchase",
            [
                "auth_data" => $this->steamId,
                "ip" => $this->ip,
                "method" => PaymentMethod::WALLET()->getValue(),
                "price_id" => $this->price->getId(),
                "service_id" => $this->serviceId,
                "sign" => $sign,
                "type" => ExtraFlagType::TYPE_SID,
            ],
            [
                "token" => $this->server->getToken(),
            ],
            [
                "Authorization" => "Bearer {$this->steamId}",
                "User-Agent" => Platform::AMXMODX,
            ]
        );

        // then
        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression(
            "#<return_value>wallet_not_logged</return_value><text>Nie można zapłacić portfelem, gdy nie jesteś zalogowany.</text><positive>0</positive>#",
            $response->getContent()
        );
    }

    /** @test */
    public function cannot_make_a_purchase_if_license_is_invalid()
    {
        // given
        $license = $this->app->make(License::class);
        $license->shouldReceive("isValid")->andReturn(false);
        $license->shouldReceive("getLoadingException")->andReturn(new LicenseRequestException());

        // given
        $user = $this->factory->user([
            "steam_id" => $this->steamId,
            "wallet" => 10000,
        ]);

        $sign = md5(
            implode("#", [ExtraFlagType::TYPE_SID, $this->steamId, "", $this->server->getToken()])
        );

        // when
        $response = $this->post(
            "/api/server/purchase",
            [
                "auth_data" => $this->steamId,
                "ip" => $this->ip,
                "method" => PaymentMethod::WALLET()->getValue(),
                "price_id" => $this->price->getId(),
                "service_id" => $this->serviceId,
                "sign" => $sign,
                "type" => ExtraFlagType::TYPE_SID,
            ],
            [
                "token" => $this->server->getToken(),
            ],
            [
                "Authorization" => "Bearer {$this->steamId}",
                "User-Agent" => Platform::AMXMODX,
            ]
        );

        // then
        $this->assertSame(402, $response->getStatusCode());
        $json = json_decode($response->getContent(), true);
        $this->assertEquals(
            [
                "message" => "Coś poszło nie tak podczas łączenia się z serwerem weryfikacyjnym.",
            ],
            $json
        );
        $freshUser = $this->userRepository->get($user->getId());
        $this->assertEqualsMoney(10000, $freshUser->getWallet());
    }
}
