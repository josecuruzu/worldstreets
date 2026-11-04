<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CloudflareProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_ip', fn (\Illuminate\Http\Request $r) => $r->ip());
    }

    public function test_desde_cloudflare_se_usa_la_ip_del_visitante(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '172.70.1.1'])
            ->get('/_ip', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertSee('203.0.113.7');
    }

    public function test_desde_cualquier_otro_origen_la_cabecera_se_ignora(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.9'])
            ->get('/_ip', ['X-Forwarded-For' => '203.0.113.7'])
            ->assertSee('198.51.100.9')
            ->assertDontSee('203.0.113.7');
    }
}
