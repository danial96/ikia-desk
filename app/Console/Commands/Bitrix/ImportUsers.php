<?php

namespace App\Console\Commands\Bitrix;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ImportUsers extends BitrixCommand
{
    protected $signature   = 'bitrix:import-users {--fresh : Wipe existing Bitrix users before import}';
    protected $description = 'Import all active users from Bitrix24 into the local database';

    public function handle(): int
    {
        $this->info('Fetching users from Bitrix24...');

        $bxUsers = $this->bxAll('user.get', [
            'ACTIVE' => 'Y',
            'select' => [
                'ID','NAME','LAST_NAME','EMAIL','SECOND_NAME',
                'PERSONAL_PHONE','WORK_PHONE','WORK_POSITION',
                'WORK_DEPARTMENT','PERSONAL_PHOTO',
                'PERSONAL_GENDER','PERSONAL_BIRTHDAY',
                'DATE_REGISTER','LAST_LOGIN',
                'TIME_ZONE_OFFSET','UF_SKYPE',
                'ACTIVE',
            ],
        ], 'result');

        if (empty($bxUsers)) {
            $this->error('No users returned from Bitrix. Check webhook permissions.');
            return 1;
        }

        $this->info("Found " . count($bxUsers) . " users. Importing...");
        $bar = $this->output->createProgressBar(count($bxUsers));
        $bar->start();

        $imported = 0;
        $skipped  = 0;

        foreach ($bxUsers as $u) {
            $bitrixId  = (int)($u['ID'] ?? 0);
            $firstName = trim($u['NAME'] ?? '');
            $lastName  = trim($u['LAST_NAME'] ?? '');
            $fullName  = trim("$firstName $lastName") ?: "User #$bitrixId";
            $email     = trim($u['EMAIL'] ?? '');

            if (!$email) {
                $email = "bitrix_user_{$bitrixId}@ikia.local";
            }

            $photoUrl  = $u['PERSONAL_PHOTO'] ?? null;
            $position  = $u['WORK_POSITION']  ?? null;

            // Map Bitrix status to role
            $role = 'employee';

            $data = [
                'bitrix_id'       => $bitrixId,
                'name'            => $fullName,
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'email'           => $email,
                'work_email'      => $email,
                'position'        => $position,
                'department'      => $u['WORK_DEPARTMENT'] ?? null,
                'work_phone'      => $u['WORK_PHONE']      ?? null,
                'personal_phone'  => $u['PERSONAL_PHONE']  ?? null,
                'skype'           => $u['UF_SKYPE']        ?? null,
                'gender'          => $u['PERSONAL_GENDER'] ?? null,
                'birthday'        => $this->parseDate($u['PERSONAL_BIRTHDAY'] ?? null),
                'hired_date'      => $this->parseDate($u['DATE_REGISTER']     ?? null),
                'last_login_at'   => $this->parseDateTime($u['LAST_LOGIN']    ?? null),
                'bitrix_photo_url'=> $photoUrl,
                'role'            => $role,
                'is_active'       => true,
                'password'        => Hash::make(\Illuminate\Support\Str::random(32)),
            ];

            try {
                $user = User::updateOrCreate(['bitrix_id' => $bitrixId], $data);

                // Download & store avatar locally
                if ($photoUrl && !$user->avatar) {
                    $this->downloadAvatar($user, $photoUrl);
                }

                $imported++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("  Skip user $bitrixId ($email): " . $e->getMessage());
                $skipped++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. Imported: $imported  |  Skipped: $skipped");

        return 0;
    }

    private function downloadAvatar(User $user, string $url): void
    {
        try {
            // Encode spaces and special chars in the path portion only
            $parsed = parse_url($url);
            $url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '')
                 . implode('/', array_map('rawurlencode', explode('/', $parsed['path'] ?? '')));

            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $img = curl_exec($ch);
            $ct  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if (!$img || strlen($img) < 500) return;

            $ext  = str_contains($ct, 'png') ? 'png' : 'jpg';
            $path = "avatars/{$user->bitrix_id}.{$ext}";
            Storage::disk('public')->put($path, $img);
            $user->update(['avatar' => $path]);
        } catch (\Throwable) {
            // Non-fatal — leave avatar null
        }
    }

    private function parseDate(?string $v): ?string
    {
        if (!$v) return null;
        try { return (new \DateTime($v))->format('Y-m-d'); }
        catch (\Throwable) { return null; }
    }

    private function parseDateTime(?string $v): ?string
    {
        if (!$v) return null;
        try { return (new \DateTime($v))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
