<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\EmailTemplate;
use App\Support\SampleOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmailSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.email.edit', [
            'template' => EmailTemplate::all(),
            'placeholders' => EmailTemplate::PLACEHOLDERS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(EmailTemplate::textRules() + [
            'remove_qr_background' => ['nullable', 'boolean'],
        ]);

        $current = EmailTemplate::all();
        $background = $current['qr_background'];

        if ($request->hasFile('qr_background')) {
            if ($background) {
                Storage::disk('public')->delete($background);
            }
            $background = $request->file('qr_background')->store('email', 'public');
        } elseif ($request->boolean('remove_qr_background') && $background) {
            Storage::disk('public')->delete($background);
            $background = null;
        }

        EmailTemplate::save(EmailTemplate::textValues($data) + ['qr_background' => $background]);

        AuditLog::record('email_template.updated');

        return redirect()->route('admin.email.edit')->with('status', 'Pengaturan email disimpan.');
    }

    public function reset(): RedirectResponse
    {
        EmailTemplate::reset();
        AuditLog::record('email_template.reset');

        return redirect()->route('admin.email.edit')->with('status', 'Email dikembalikan ke pengaturan awal.');
    }

    /** Renders the e-mail with the saved settings and a sample order (nothing is saved or sent). */
    public function preview(Request $request): View
    {
        return view('admin.email.preview', SampleOrder::previewData($request->user(), EmailTemplate::all()));
    }
}
