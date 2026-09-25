<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AvatarImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class AvatarsToWebp extends Command
{
    protected $signature = 'avatars:webp {--dry-run}';
    protected $description = 'Convert every uploaded avatar to a small square WebP and point users at it';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $saved = 0; $done = 0;
        foreach (User::whereNotNull('avatar')->where('avatar', '!=', '')->get() as $u) {
            if (str_ends_with($u->avatar, '.webp') || !$disk->exists($u->avatar)) continue;
            $oldPath = $u->avatar;
            $old = $disk->get($oldPath);
            $webp = AvatarImage::toWebp($old);
            if (!$webp) { $this->warn("skip #{$u->id} {$u->avatar}"); continue; }
            $new = 'avatars/' . $u->id . '-' . substr(md5($webp), 0, 8) . '.webp';   // new name → browsers refetch, not stale cache
            $this->line("#{$u->id} {$u->avatar} " . round(strlen($old) / 1024) . "KB -> {$new} " . round(strlen($webp) / 1024) . 'KB');
            if (!$this->option('dry-run')) {
                $disk->put($new, $webp);
                if (!$disk->exists($new)) { $this->error("write failed for #{$u->id}"); continue; }
                $u->forceFill(['avatar' => $new])->save();
                $disk->delete($oldPath);
            }
            $saved += strlen($old) - strlen($webp); $done++;
        }
        $this->info(($this->option('dry-run') ? '[dry-run] ' : '') . "$done avatars, saves " . round($saved / 1024) . ' KB');
        return 0;
    }
}
