<?php
namespace Tests\Feature\Http\Api\Admin;

use Tests\Psr4\TestCases\HttpTestCase;

class PageSmsCodesActionBoxAddTest extends HttpTestCase
{
    /** @test */
    public function get_add_box()
    {
        // give
        $this->actingAs($this->factory->admin());

        // when
        $response = $this->getJson("/api/admin/pages/sms_codes/action_boxes/sms_code_add");

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertEquals('ok', $json['return_id']);
        $this->assertContains("Dodaj kod SMS", $json['template']);
    }

    /** @test */
    public function requires_permission_to_get()
    {
        // give
        $admin = $this->factory->user();
        $this->actingAs($admin);

        // when
        $response = $this->getJson("/api/admin/pages/sms_codes/action_boxes/sms_code_add");

        // then
        $this->assertSame(200, $response->getStatusCode());
        $json = $this->decodeJsonResponse($response);
        $this->assertEquals('no_access', $json["return_id"]);
    }
}
