<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubstitutionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $substitutions = $request->user()
            ->substituteFor()
            ->with('user:id,name,email')
            ->where('from_date', '<=', now()->toDateString())
            ->where('to_date', '>=', now()->toDateString())
            ->get();

        return response()->json($substitutions);
    }
}
