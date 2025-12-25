<?php

namespace App\Http\Controllers;

use App\Models\Stop;
use Illuminate\Http\JsonResponse;

class StopController extends Controller
{
    /**
     * Display a listing of the stops.
     */
    public function index(): JsonResponse
    {
        return response()->json(Stop::all());
    }
}
