<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Uploads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadDownloadNameTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    private function stored(string $ext): string
    {
        @mkdir(Uploads::path(), 0755, true);
        $name = 'up_' . uniqid() . '.' . $ext;
        file_put_contents(Uploads::path($name), 'x');
        $this->files[] = $name;
        return $name;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $f) @unlink(Uploads::path($f));
        parent::tearDown();
    }

    /** @dataProvider awkwardNames */
    public function test_files_with_awkward_names_open_instead_of_erroring(string $realName): void
    {
        $user   = User::factory()->create(['is_active' => true]);
        $stored = $this->stored('txt');

        // "%", accents and Urdu used to make Symfony throw (a 500) because no ASCII fallback was given
        $res = $this->actingAs($user)->get('/uploads/' . $stored . '/' . rawurlencode($realName) . '?name=' . rawurlencode($realName));

        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        // the real (non-ASCII) name is still delivered, via the RFC 5987 filename* parameter
        if (preg_match('/[^ -~]/', $realName)) {
            $this->assertStringContainsString("filename*=utf-8''" . rawurlencode($realName), $res->headers->get('Content-Disposition'));
        }
    }

    public static function awkwardNames(): array
    {
        return [
            'percent sign'  => ['Report 100% final.txt'],
            'accents'       => ['résumé août.txt'],
            'urdu'          => ['رپورٹ فائنل.txt'],
            'plain'         => ['plain name.txt'],
            'quotes+semi'   => ['my "quoted"; name.txt'],
        ];
    }

    public function test_forced_download_branch_also_handles_awkward_names(): void
    {
        $user   = User::factory()->create(['is_active' => true]);
        $stored = $this->stored('html');

        $res = $this->actingAs($user)->get('/uploads/' . $stored . '/' . rawurlencode('100% résumé.html'));

        $res->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
    }
}
