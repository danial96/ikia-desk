<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Project;
use App\Models\User;

class ImportProjects extends BitrixCommand
{
    protected $signature   = 'bitrix:import-projects {--limit=0 : Max workgroups to import (0 = all)}';
    protected $description = 'Import Bitrix24 workgroups as projects';

    public function handle(): int
    {
        $limit = (int)$this->option('limit');
        $this->info('Fetching workgroups from Bitrix24...');

        $groups = $this->bxAll('sonet_group.get', [
            'order'  => ['ID' => 'ASC'],
            'filter' => ['ACTIVE' => 'Y'],
            'select' => ['ID','NAME','DESCRIPTION','IMAGE_ID','OWNER_ID',
                         'NUMBER_OF_MEMBERS','DATE_CREATE','DATE_UPDATE',
                         'SITE_ID','VISIBLE','OPENED','CLOSED'],
        ], 'result');

        if (empty($groups)) {
            $this->warn('No workgroups returned.');
            return 0;
        }

        if ($limit > 0) {
            $groups = array_slice($groups, 0, $limit);
        }

        $this->info('Found ' . count($groups) . ' workgroups. Importing...');
        $bar = $this->output->createProgressBar(count($groups));
        $bar->start();

        $done = 0;
        foreach ($groups as $g) {
            $bxId   = (int)$g['ID'];
            $ownerId = (int)($g['OWNER_ID'] ?? 0);
            $owner  = $ownerId ? User::where('bitrix_id', $ownerId)->first() : null;

            // Use owner as created_by; fall back to first super_admin
            $createdBy = $owner?->id
                ?? User::where('role','super_admin')->value('id')
                ?? User::first()?->id;

            if (!$createdBy) {
                $bar->advance();
                continue;
            }

            Project::updateOrCreate(
                ['bitrix_id' => $bxId],
                [
                    'name'               => $g['NAME'] ?? "Group $bxId",
                    'description'        => $g['DESCRIPTION'] ?? null,
                    'created_by'         => $createdBy,
                    'type'               => 'workgroup',
                    'status'             => empty($g['CLOSED']) || $g['CLOSED'] === 'N' ? 'active' : 'completed',
                    'owner_id'           => $owner?->id,
                    'number_of_members'  => (int)($g['NUMBER_OF_MEMBERS'] ?? 0),
                    'bitrix_date_create' => $this->parseDateTime($g['DATE_CREATE'] ?? null),
                    'bitrix_date_update' => $this->parseDateTime($g['DATE_UPDATE'] ?? null),
                    'site_id'            => $g['SITE_ID'] ?? null,
                ]
            );

            $done++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. $done workgroups imported as projects.");
        return 0;
    }

    private function parseDateTime(?string $v): ?string
    {
        if (!$v) return null;
        try { return (new \DateTime($v))->format('Y-m-d H:i:s'); }
        catch (\Throwable) { return null; }
    }
}
