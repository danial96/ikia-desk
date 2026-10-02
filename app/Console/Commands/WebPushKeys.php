<?php

namespace App\Console\Commands;

use App\Support\WebPush;
use Illuminate\Console\Command;

class WebPushKeys extends Command
{
    protected $signature = 'webpush:keys';
    protected $description = 'Generate a VAPID key pair for browser push notifications (put the lines in .env)';

    public function handle(): int
    {
        [$public, $private] = WebPush::generateKeys();
        $this->line('WEBPUSH_PUBLIC_KEY=' . $public);
        $this->line('WEBPUSH_PRIVATE_KEY=' . $private);
        $this->newLine();
        $this->warn('Keep the private key secret. Changing the pair later makes every existing subscription invalid.');
        return self::SUCCESS;
    }
}
