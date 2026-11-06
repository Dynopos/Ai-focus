<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('audit.index', [
            'logs' => AuditLog::with('user')->where('business_id', $request->user()->business_id)->latest('id')->paginate(50),
        ]);
    }
}
