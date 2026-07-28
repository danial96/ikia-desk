<?php

namespace App\Console\Commands\Bitrix;

use App\Models\Department;
use App\Models\User;

class ImportDepartments extends BitrixCommand
{
    protected $signature   = 'bitrix:import-departments';
    protected $description = 'Import departments from Bitrix24';

    public function handle(): int
    {
        $this->info('Fetching departments from Bitrix24...');

        $deps = $this->bxAll('department.get', [], 'result');

        if (empty($deps)) {
            $this->warn('No departments returned.');
            return 0;
        }

        $this->info('Found ' . count($deps) . ' departments.');

        // First pass: create/update all departments (without parent FK yet)
        $idMap = []; // bitrix_id => local id
        foreach ($deps as $d) {
            $bxId    = (int)$d['ID'];
            $headBxId = (int)($d['UF_HEAD'] ?? 0);
            $headUser = $headBxId ? User::where('bitrix_id', $headBxId)->first() : null;

            $dep = Department::updateOrCreate(
                ['bitrix_id' => $bxId],
                [
                    'name'       => $d['NAME'] ?? "Department $bxId",
                    'sort_index' => (int)($d['SORT'] ?? 500),
                    'head_id'    => $headUser?->id,
                ]
            );
            $idMap[$bxId] = $dep->id;
            $this->line("  [{$bxId}] {$dep->name}");
        }

        // Second pass: set parent_id
        foreach ($deps as $d) {
            $bxId     = (int)$d['ID'];
            $parentBx = (int)($d['PARENT'] ?? 0);
            if ($parentBx && isset($idMap[$parentBx]) && isset($idMap[$bxId])) {
                Department::where('id', $idMap[$bxId])->update(['parent_id' => $idMap[$parentBx]]);
            }
        }

        // Sync user ↔ department based on users' WORK_DEPARTMENT
        $this->info('Syncing user-department memberships...');
        User::whereNotNull('bitrix_id')->each(function (User $user) use ($idMap, $deps) {
            $deptName = $user->department;
            if (!$deptName) return;
            // Find department by name
            $match = Department::where('name', $deptName)->first();
            if ($match) {
                $user->departments()->syncWithoutDetaching([$match->id]);
            }
        });

        $this->info('Departments imported successfully.');
        return 0;
    }
}
