<?php

namespace App\Http\Controllers;

use App\Services\CsmSheetChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CsmController extends Controller
{
    public function check(string $ref, CsmSheetChecker $checker): JsonResponse
    {
        $res = Cache::remember("csm-check:$ref", 300, fn () => $checker->hasReference($ref));

        return response()->json([
            'ok' => true,
            'ref' => $ref,
            'submitted' => (bool) ($res['found'] ?? false),
            'submitted_at' => $res['submitted_at'] ?? null,
            'error' => $res['error'] ?? null,
        ]);
    }
}
