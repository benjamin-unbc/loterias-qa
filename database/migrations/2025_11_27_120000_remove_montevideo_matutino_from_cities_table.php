<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('cities')
            ->where('code', 'ORO1500')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $records = [
            ['extract_id' => 2, 'name' => 'MONTEVIDEO', 'code' => 'ORO1500', 'time' => '15:00'],
            ['extract_id' => 3, 'name' => 'MONTEVIDEO', 'code' => 'ORO1500', 'time' => '15:00'],
        ];

        foreach ($records as $record) {
            $exists = DB::table('cities')
                ->where('code', $record['code'])
                ->where('extract_id', $record['extract_id'])
                ->exists();

            if (!$exists) {
                DB::table('cities')->insert(array_merge($record, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }
        }
    }
};

