<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Route::get('/_ip', fn (Request $r) => response()->json(['ip' => $r->ip(), 'secure' => $r->isSecure()]));
    }

    public function test_behind_cloudflare_the_visitors_real_ip_and_scheme_are_used(): void
    {
        $res = $this->withServerVariables([
            'REMOTE_ADDR' => '173.245.48.5',                      // a Cloudflare edge address
            'HTTP_X_FORWARDED_FOR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->getJson('/_ip');

        $res->assertJson(['ip' => '203.0.113.9', 'secure' => true]);
    }

    public function test_the_ipv6_edge_ranges_are_trusted_too(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '2606:4700:10::1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'])
            ->getJson('/_ip')->assertJson(['ip' => '203.0.113.9']);
    }

    public function test_anyone_else_can_not_fake_their_ip_with_a_forwarded_header(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9'])
            ->getJson('/_ip')->assertJson(['ip' => '198.51.100.7']);
    }

    public function test_requests_that_do_not_come_through_cloudflare_behave_exactly_as_before(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '154.57.193.99'])->getJson('/_ip')->assertJson(['ip' => '154.57.193.99']);
    }
}
