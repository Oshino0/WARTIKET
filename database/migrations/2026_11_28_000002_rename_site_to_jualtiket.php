<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Carries the new site name into data that was already saved (admin e-mail settings, site name,
 * per-event e-mail settings, the seeded admin's display name). The code defaults are changed separately.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rename = fn (?string $text) => $text === null ? null : preg_replace('/wartiket/i', 'JualTiket', $text);

        foreach (DB::table('settings')->whereIn('key', ['site_name', 'email_template'])->get() as $row) {
            DB::table('settings')->where('id', $row->id)->update(['value' => $rename($row->value)]);
        }

        foreach (DB::table('products')->whereNotNull('email_template')->get(['id', 'email_template']) as $row) {
            DB::table('products')->where('id', $row->id)->update(['email_template' => $rename($row->email_template)]);
        }

        DB::table('users')->where('name', 'Admin WARTIKET')->update(['name' => 'Admin JualTiket']);
    }

    public function down(): void
    {
        // The previous name is not restored.
    }
};
