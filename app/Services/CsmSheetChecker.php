<?php

// app/Services/CsmSheetChecker.php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CsmSheetChecker
{
    private Google_Service_Sheets $sheets;

    private string $spreadsheetId;

    private string $range;

    public function __construct()
    {
        $client = new Google_Client;
        $client->setAuthConfig(config('services.csm.cred'));
        $client->addScope(Google_Service_Sheets::SPREADSHEETS_READONLY);

        $this->sheets = new Google_Service_Sheets($client);
        $this->spreadsheetId = config('services.csm.sheet_id');
        $this->range = config('services.csm.range');
    }

    /** Read sheet once and return a hash set of all Reference Numbers present */
    public function referenceSet(): array
    {
        return Cache::remember('csm:refset', 600, function () {
            $resp = $this->sheets->spreadsheets_values->get($this->spreadsheetId, $this->range, [
                'valueRenderOption' => 'FORMATTED_VALUE',
            ]);
            $rows = $resp->getValues() ?? [];
            if (count($rows) < 2) {
                return [];
            }

            $header = array_map('strval', $rows[0]);
            $refIdx = $this->findCol($header, ['Reference Number:', 'Reference Number', 'Reference No', 'Reference No.']);
            if ($refIdx === null) {
                return [];
            }

            $set = [];
            for ($i = 1; $i < count($rows); $i++) {
                $v = isset($rows[$i][$refIdx]) ? trim((string) $rows[$i][$refIdx]) : '';
                if ($v !== '') {
                    $set[$v] = true;
                }
            }

            return $set;
        });
    }

    /** Optional: quick check for a single reference */
    public function hasReference(string $ref): bool
    {
        $set = $this->referenceSet();

        return isset($set[$ref]);
    }

    /** Return the subset of $tickets NOT present in the sheet */
    public function ticketsMissing(array $tickets, string $refField = 'client_referenceNo', ?callable $predicate = null): array
    {
        $inSheet = $this->referenceSet();
        $out = [];
        foreach ($tickets as $t) {
            // object or array support
            $ref = is_array($t) ? ($t[$refField] ?? null) : ($t->$refField ?? null);
            $ok = $predicate ? $predicate($t) : true;     // optional filter (e.g., Completed only)
            if ($ok && $ref && ! isset($inSheet[$ref])) {
                $out[] = $t;
            }
        }

        return $out;
    }

    /** NEW: Return the subset of $tickets that ARE present in the sheet (Has CSM) */
    public function ticketsPresent(array $tickets, string $refField = 'client_referenceNo', ?callable $predicate = null): array
    {
        $inSheet = $this->referenceSet();
        $out = [];
        foreach ($tickets as $t) {
            $ref = is_array($t) ? ($t[$refField] ?? null) : ($t->$refField ?? null);
            $ok = $predicate ? $predicate($t) : true;
            if ($ok && $ref && isset($inSheet[$ref])) {
                $out[] = $t;
            }
        }

        return $out;
    }

    private function findCol(array $header, array $candidates): ?int
    {
        $norm = fn ($s) => Str::of((string) $s)->trim()->lower()
            ->replaceMatches('/[\s\xa0]+/u', ' ')->rtrim(':.')->value();
        $normalized = array_map($norm, $header);
        foreach ($candidates as $want) {
            $ix = array_search($norm($want), $normalized, true);
            if ($ix !== false) {
                return $ix;
            }
        }

        return null;
    }
}
