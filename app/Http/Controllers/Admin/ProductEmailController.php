<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Services\EmailTemplate;
use App\Support\SampleOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Ticket e-mail settings for one event (product). Overrides the global template when enabled. */
class ProductEmailController extends Controller
{
    public function edit(Product $product): View
    {
        $own = is_array($product->email_template) ? $product->email_template : [];
        $global = EmailTemplate::all();

        return view('admin.products.email', [
            'product' => $product,
            // Show the event's own values, or a copy of the global ones as a starting point.
            'template' => array_merge(Arr::only($global, EmailTemplate::TEXT_KEYS), Arr::only($own, EmailTemplate::TEXT_KEYS)),
            'placeholders' => EmailTemplate::PLACEHOLDERS,
            'enabled' => ! empty($own['enabled']),
            'mode' => $own['qr_background_mode'] ?? 'global',
            'customBackground' => $own['qr_background'] ?? null,
            'globalBackground' => $global['qr_background'],
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate(EmailTemplate::textRules() + [
            'enabled' => ['nullable', 'boolean'],
            'qr_background_mode' => ['required', Rule::in(['global', 'custom', 'none'])],
        ]);

        $own = is_array($product->email_template) ? $product->email_template : [];
        $custom = $own['qr_background'] ?? null;
        $mode = $data['qr_background_mode'];

        if ($request->hasFile('qr_background')) {
            // Uploading an image means "use my own image".
            if ($custom) {
                Storage::disk('public')->delete($custom);
            }
            $custom = $request->file('qr_background')->store('email', 'public');
            $mode = 'custom';
        } elseif ($mode === 'custom' && ! $custom) {
            throw ValidationException::withMessages(['qr_background' => 'Unggah gambar latar untuk acara ini, atau pilih opsi lain.']);
        }

        if ($mode !== 'custom' && $custom) {
            Storage::disk('public')->delete($custom); // no orphan files
            $custom = null;
        }

        $product->email_template = EmailTemplate::textValues($data) + [
            'enabled' => $request->boolean('enabled'),
            'qr_background_mode' => $mode,
            'qr_background' => $custom,
        ];
        $product->save();

        AuditLog::record('product_email_template.updated', $product, ['enabled' => $request->boolean('enabled')]);

        return redirect()->route('admin.products.email.edit', $product)->with('status', 'Pengaturan email acara disimpan.');
    }

    public function reset(Product $product): RedirectResponse
    {
        $custom = is_array($product->email_template) ? ($product->email_template['qr_background'] ?? null) : null;

        if ($custom) {
            Storage::disk('public')->delete($custom);
        }

        $product->email_template = null;
        $product->save();

        AuditLog::record('product_email_template.reset', $product);

        return redirect()->route('admin.products.email.edit', $product)->with('status', 'Acara ini kembali memakai pengaturan email umum.');
    }

    public function preview(Request $request, Product $product): View
    {
        return view('admin.email.preview', SampleOrder::previewData(
            $request->user(),
            EmailTemplate::forProduct($product),
            $product->name,
        ));
    }
}
