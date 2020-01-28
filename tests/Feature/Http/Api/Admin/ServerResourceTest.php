<?php
namespace Tests\Feature\Http\Api\Admin;

use App\Repositories\ServerRepository;
use App\Verification\PaymentModules\Microsms;
use App\Verification\PaymentModules\Simpay;
use Tests\Psr4\TestCases\HttpTestCase;

class ServerResourceTest extends HttpTestCase
{
    /** @var ServerRepository */
    private $serverRepository;

    protected function setUp()
    {
        parent::setUp();
        $this->serverRepository = $this->app->make(ServerRepository::class);
    }

    /** @test */
    public function updates_server()
    {
        // given
        $this->actingAs($this->factory->admin());
        $server = $this->factory->server();

        $paymentPlatform = $this->factory->paymentPlatform([
            'module' => Simpay::MODULE_ID,
        ]);

        // when
        $response = $this->put("/api/admin/servers/{$server->getId()}", [
            'name' => 'My Example2',
            'ip' => '192.168.0.2',
            'port' => '27016',
            'sms_platform' => $paymentPlatform->getId(),
        ]);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertSame("ok", $json["return_id"]);
        $freshServer = $this->serverRepository->get($server->getId());
        $this->assertSame("My Example2", $freshServer->getName());
        $this->assertSame("192.168.0.2", $freshServer->getIp());
        $this->assertSame("27016", $freshServer->getPort());
        $this->assertSame($paymentPlatform->getId(), $freshServer->getSmsPlatformId());
    }

    /** @test */
    public function deletes_server()
    {
        // given
        $this->actingAs($this->factory->admin());
        $server = $this->factory->server();

        // when
        $response = $this->delete("/api/admin/servers/{$server->getId()}");

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertSame("ok", $json["return_id"]);
        $freshServer = $this->serverRepository->get($server->getId());
        $this->assertNull($freshServer);
    }
}