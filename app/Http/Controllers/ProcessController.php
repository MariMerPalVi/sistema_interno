<?php

namespace App\Http\Controllers;

use App\Models\Process;

class ProcessController extends Controller
{
    public function index()
    {
        return view('processes.index', [
            'processes' => Process::orderByDesc('is_enabled')->orderBy('name')->get(),
        ]);
    }
}
