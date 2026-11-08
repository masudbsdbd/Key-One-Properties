<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

/**
 * Starter expense categories. Materials and contractor work are not expense categories:
 * they are recorded through their own modules. Safe to run more than once.
 */
class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['ADM', 'Administration', 'general', [
                ['ADM-RENT', 'Office Rent', 'general'],
                ['ADM-SAL', 'Office Salaries', 'general'],
                ['ADM-STAT', 'Stationery & Printing', 'both'],
            ]],
            ['UTL', 'Utilities', 'both', [
                ['UTL-ELEC', 'Electricity', 'both'],
                ['UTL-WATER', 'Water', 'both'],
                ['UTL-NET', 'Internet & Phone', 'both'],
            ]],
            ['SITE', 'Site Running', 'project', [
                ['SITE-SEC', 'Site Security', 'project'],
                ['SITE-TRN', 'Transport & Carriage', 'project'],
                ['SITE-TEST', 'Testing & Inspection', 'project'],
                ['SITE-MISC', 'Site Miscellaneous', 'project'],
            ]],
            ['FEE', 'Fees & Permits', 'both', [
                ['FEE-RAJUK', 'Approval & Permit Fees', 'project'],
                ['FEE-LEGAL', 'Legal & Professional', 'both'],
            ]],
            ['MKT', 'Marketing', 'both', [
                ['MKT-ADS', 'Advertising', 'both'],
                ['MKT-EVT', 'Events & Fairs', 'general'],
            ]],
        ];

        foreach ($tree as [$code, $name, $scope, $children]) {
            $parent = ExpenseCategory::query()->firstOrCreate(['code' => $code], ['name' => $name, 'allowed_scope' => $scope]);
            foreach ($children as [$childCode, $childName, $childScope]) {
                $child = ExpenseCategory::query()->firstOrNew(['code' => $childCode]);
                if (! $child->exists) {
                    $child->fill(['name' => $childName, 'allowed_scope' => $childScope]);
                    $child->parent_id = $parent->id;
                    $child->save();
                }
            }
        }
    }
}
