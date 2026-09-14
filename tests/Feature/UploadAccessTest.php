<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadAccessTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(Uploads::path(), 0755, true);
        $this->file = 'test_' . uniqid() . '.txt';
        file_put_contents(Uploads::path($this->file), 'hello');
    }

    protected function tearDown(): void
    {
        @unlink(Uploads::path($this->file));
        parent::tearDown();
    }

    public function test_uploaded_files_require_authentication(): void
    {
        $this->get('/uploads/' . $this->file)->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_download_an_uploaded_file(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $res = $this->actingAs($user)->get('/uploads/' . $this->file);
        $res->assertOk();
        $this->assertSame('hello', $res->streamedContent());
    }

    public function test_path_traversal_is_blocked(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)->get('/uploads/..%2f..%2f..%2f.env')->assertNotFound();
        $this->actingAs($user)->get('/uploads/nope-does-not-exist.txt')->assertNotFound();
    }
}
