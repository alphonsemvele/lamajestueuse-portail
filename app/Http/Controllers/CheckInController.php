<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $checkIn = $request->user()->checkIns()->firstOrNew(['work_date' => today()]);

        if ($checkIn->checked_in_at && ! $checkIn->checked_out_at) {
            $checkIn->checked_out_at = now();
            $message = __('Sortie enregistrée.');
        } elseif (! $checkIn->checked_in_at) {
            $checkIn->checked_in_at = now();
            $message = __('Arrivée enregistrée.');
        } else {
            return back()->with('status', __('Votre journée est déjà clôturée.'));
        }

        $checkIn->save();

        return back()->with('status', $message);
    }
}
