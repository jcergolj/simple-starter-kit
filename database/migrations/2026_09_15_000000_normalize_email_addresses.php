<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'invitations'] as $table) {
            $collisions = $this->caseCollisions($table);

            if ($collisions->isNotEmpty()) {
                throw new RuntimeException(
                    "Cannot normalize {$table}. Resolve duplicate email addresses first: "
                    .$collisions->pluck('emails')->implode('; ')
                );
            }

            DB::table($table)->update([
                'email' => DB::raw('LOWER(TRIM(email))'),
            ]);
        }
    }

    public function down(): void
    {
        // Email normalization is intentionally irreversible.
    }

    private function caseCollisions(string $table): Collection
    {
        return DB::table($table)
            ->selectRaw('LOWER(TRIM(email)) AS normalized_email, GROUP_CONCAT(email) AS emails')
            ->whereNotNull('email')
            ->groupBy('normalized_email')
            ->havingRaw('COUNT(*) > 1')
            ->get();
    }
};
