<?php
namespace Tests\Feature\Http\Api\Admin;

use App\ServiceModules\MybbExtraGroups\MybbUserService;
use App\Services\UserServiceService;
use Tests\Psr4\Concerns\MybbRepositoryConcern;
use Tests\Psr4\TestCases\HttpTestCase;

class UserServiceCollectionMybbTest extends HttpTestCase
{
    use MybbRepositoryConcern;

    /** @var UserServiceService */
    private $userServiceService;

    protected function setUp()
    {
        parent::setUp();

        $this->userServiceService = $this->app->make(UserServiceService::class);
        $this->actingAs($this->factory->admin());
        $this->mockMybbRepository();
    }

    /** @test */
    public function add_user_service()
    {
        // given
        $this->mybbRepositoryMock
            ->shouldReceive("existsByUsername")
            ->withArgs(["example"])
            ->andReturnTrue();

        $this->mybbRepositoryMock
            ->shouldReceive("updateGroups")
            ->withArgs([1, [1, 2], 1])
            ->andReturnNull();

        $expectedExpire = time() + 5 * 24 * 60 * 60;
        $service = $this->factory->mybbService();

        // when
        $response = $this->post("/api/admin/services/{$service->getId()}/user_services", [
            "mybb_username" => "example",
            "quantity" => "5",
        ]);

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame("ok", $json["return_id"]);

        /** @var MybbUserService $userService */
        $userService = $this->userServiceService->findOne();
        $this->assertNotNull($userService);
        $this->assertSame($service->getId(), $userService->getServiceId());
        $this->assertSame(1, $userService->getMybbUid());
        $this->assertSame(0, $userService->getUserId());
        $this->assertAlmostSameTimestamp($expectedExpire, $userService->getExpire());
    }
}