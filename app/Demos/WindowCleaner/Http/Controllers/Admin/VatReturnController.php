<?php

namespace App\Demos\WindowCleaner\Http\Controllers\Admin;

use App\Demos\WindowCleaner\Support\VatReturn;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class VatReturnController
{
    public function show(Request $request): View
    {
        $quarters = VatReturn::quarters();

        $quarter = $request->query('quarter', $quarters[0] ?? null);

        abort_unless($quarter === null || in_array($quarter, $quarters, true), 404);

        return view('demos.window-cleaner.admin.vat-return', [
            'quarters' => $quarters,
            'report' => $quarter === null ? null : VatReturn::for($quarter),
        ]);
    }
}
