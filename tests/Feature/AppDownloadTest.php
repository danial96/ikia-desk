<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MobileApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    public function test_the_button_and_page_stay_hidden_until_a_download_link_is_set(): void
    {
        config(['desk.mobile.store.android' => null, 'desk.mobile.store.ios' => null, 'desk.mobile.apk' => null]);

        $this->assertFalse(MobileApp::available());
        $this->actingAs($this->user())->get('/dashboard')->assertOk()->assertDontSee('Download app');
        $this->actingAs($this->user())->get('/app')->assertNotFound();
    }

    public function test_the_sidebar_button_appears_above_the_profile_once_a_link_is_set(): void
    {
        config(['desk.mobile.store.android' => 'https://play.google.com/store/apps/details?id=com.ikiatech.desk']);

        $html = $this->actingAs($this->user())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Download app', $html);
        $this->assertStringContainsString(route('app.download'), $html);
        $this->assertLessThan(strpos($html, 'class="sb-user"'), strpos($html, 'Download app'), 'the button belongs above the profile block');
    }

    public function test_the_page_lists_every_configured_store_and_needs_a_login(): void
    {
        config([
            'desk.mobile.store.android' => 'https://play.example/ikia',
            'desk.mobile.store.ios'     => 'https://testflight.example/ikia',
            'desk.mobile.apk'           => 'https://desk.example/downloads/ikia.apk',
        ]);

        $this->get('/app')->assertRedirect(route('login'));

        $this->actingAs($this->user())->get('/app')->assertOk()
            ->assertSee('https://play.example/ikia', false)
            ->assertSee('https://testflight.example/ikia', false)
            ->assertSee('https://desk.example/downloads/ikia.apk', false)
            ->assertSee('Install unknown apps')
            ->assertSee('vendor/qrcode.js', false);
    }

    public function test_only_http_links_are_ever_rendered(): void
    {
        config(['desk.mobile.store.android' => 'javascript:alert(1)', 'desk.mobile.store.ios' => 'https://ok.example/app', 'desk.mobile.apk' => 'data:text/html;base64,AAAA']);

        $this->assertSame(['android' => null, 'ios' => 'https://ok.example/app', 'apk' => null], MobileApp::links());
        $this->actingAs($this->user())->get('/app')->assertOk()->assertDontSee('javascript:', false)->assertDontSee('data:text/html', false);
    }

    public function test_the_qr_library_is_served(): void
    {
        $this->assertFileExists(public_path('vendor/qrcode.js'));
        $this->assertStringContainsString('qrcode', file_get_contents(public_path('vendor/qrcode.js')));
    }

    public function test_the_release_note_for_the_app_is_new_and_last(): void
    {
        $releases = require resource_path('whatsnew.php');
        $ids = array_column($releases, 'id');

        $this->assertSame($ids, array_values(array_unique($ids)), 'release ids must be unique');
        $this->assertSame(max($ids), end($releases)['id']);
        $this->assertStringContainsString('phone app', implode(' ', end($releases)['items']));
    }
}
