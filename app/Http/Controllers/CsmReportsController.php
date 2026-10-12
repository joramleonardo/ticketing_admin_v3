<?php

namespace App\Http\Controllers;

use App\Services\CsmSheetChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class CsmReportsController extends Controller
{
    public function index(Request $request, CsmSheetChecker $checker)
    {
        $month = $request->integer('month');
        $year = $request->integer('year');

        $ticketsTable = 'v2_ticket_details';
        $clientsTable = 'v2_ticket_client_details';
        $logsTable = 'v2_ticket_activity_logs'; // <-- NEW

        $selects = [
            't.client_referenceNo',
            't.date_completed',
            DB::raw("NULLIF(TRIM(t.ticket_assigned_staff), '') as assigned_staff"),
            DB::raw('l.date_created as date_created'), // <-- NEW
        ];

        $query = DB::table("$ticketsTable as t")->whereNotNull('t.date_completed');

        if ($year) {
            $query->whereYear('t.date_completed', $year);
        }
        if ($month) {
            $query->whereMonth('t.date_completed', $month);
        }

        // NEW: subquery to get earliest "Ticket Created" per reference
        if (\Illuminate\Support\Facades\Schema::hasTable($logsTable)) {
            $createdSub = DB::table($logsTable)
                ->select('client_referenceNo', DB::raw('MIN(activity_date) as date_created'))
                ->where('activity_name', 'Ticket Created')
                ->groupBy('client_referenceNo');

            $query->leftJoinSub($createdSub, 'l', 'l.client_referenceNo', '=', 't.client_referenceNo');
        }

        // ----- existing client_name logic (unchanged except for where we append)
        $clientNameExpr = "'-'";
        $clientSexExpr = 'NULL';

        if (Schema::hasTable($clientsTable)) {
            $query->leftJoin("$clientsTable as c", 'c.client_referenceNo', '=', 't.client_referenceNo');
            $exists = fn ($col) => Schema::hasColumn($clientsTable, $col);

            $namePieces = [];
            foreach (['client_name', 'requestor_name', 'full_name', 'contact_person'] as $col) {
                if ($exists($col)) {
                    $namePieces[] = "c.$col";
                }
            }
            if ($exists('first_name') && $exists('last_name')) {
                $namePieces[] = "CONCAT(TRIM(IFNULL(c.first_name,'')),
                           CASE WHEN TRIM(IFNULL(c.first_name,''))<>'' AND TRIM(IFNULL(c.last_name,''))<>'' THEN ' ' ELSE '' END,
                           TRIM(IFNULL(c.last_name,'')))";
            }
            if ($namePieces) {
                $clientNameExpr = 'NULLIF(TRIM(COALESCE('.implode(',', $namePieces).")), '')";
            }

            $sexPieces = [];
            foreach (['client_sex', 'sex', 'gender', 'client_gender'] as $col) {
                if ($exists($col)) {
                    $sexPieces[] = "LOWER(TRIM(c.$col))";
                }
            }
            if ($sexPieces) {
                $sexCoalesce = 'NULLIF(TRIM(LOWER(COALESCE('.implode(',', $sexPieces).", ''))), '')";
                $clientSexExpr = "CASE
                WHEN $sexCoalesce IN ('f','female','woman','babae','feminine') THEN 'Female'
                WHEN $sexCoalesce IN ('m','male','man','lalaki','masculine') THEN 'Male'
                ELSE NULL
            END";
            }
        }

        $selects[] = DB::raw("$clientNameExpr as client_name");
        $selects[] = DB::raw("$clientSexExpr  as client_sex");

        $rows = $query->select($selects)->orderByDesc('t.date_completed')->get();

        foreach ($rows as $r) {
            if (! isset($r->client_name) || trim((string) $r->client_name) === '') {
                $r->client_name = '-';
            }
            if (! isset($r->assigned_staff) || trim((string) $r->assigned_staff) === '') {
                $r->assigned_staff = '-';
            }
            if (! isset($r->client_sex) || trim((string) $r->client_sex) === '') {
                $r->client_sex = '-';
            }
        }

        $noCsmObjects = $checker->ticketsMissing($rows->all(), 'client_referenceNo');
        $hasCsmObjects = $checker->ticketsPresent($rows->all(), 'client_referenceNo');

        $map = fn ($t) => [
            'client_referenceNo' => is_array($t) ? ($t['client_referenceNo'] ?? '') : ($t->client_referenceNo ?? ''),
            'client_name' => is_array($t) ? ($t['client_name'] ?? '-') : ($t->client_name ?? '-'),
            'client_sex' => is_array($t) ? ($t['client_sex'] ?? '-') : ($t->client_sex ?? '-'),
            'date_completed' => is_array($t) ? ($t['date_completed'] ?? null) : ($t->date_completed ?? null),
            'assigned_staff' => is_array($t) ? ($t['assigned_staff'] ?? '-') : ($t->assigned_staff ?? '-'),
            'date_created' => is_array($t) ? ($t['date_created'] ?? null) : ($t->date_created ?? null), // <-- NEW
        ];

        $noCsm = array_map($map, $noCsmObjects);
        $hasCsm = array_map($map, $hasCsmObjects);

        return Inertia::render('TicketsV2/CSMDashboard', [
            'noCsmTicketsAll' => $noCsm,
            'hasCsmTicketsAll' => $hasCsm,
            'filters' => ['month' => $month, 'year' => $year],
            'ticketStats' => (object) [],
        ]);
    }
}
