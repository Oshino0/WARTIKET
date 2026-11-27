<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\TicketType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketTypeController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $this->validated($request);
        $data['product_id'] = $product->id;

        $type = TicketType::create($data);
        AuditLog::record('ticket_type.created', $type, ['price' => $type->price, 'quota' => $type->quota]);

        return redirect()->route('admin.products.edit', $product)->with('status', 'Tipe tiket ditambahkan.');
    }

    public function edit(TicketType $ticketType)
    {
        return view('admin.ticket-types.edit', ['ticketType' => $ticketType->load('product')]);
    }

    public function update(Request $request, TicketType $ticketType)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($ticketType, $data) {
            // Lock the row so a concurrent checkout cannot slip between the check and the write.
            $locked = TicketType::whereKey($ticketType->id)->lockForUpdate()->firstOrFail();
            $inUse = $locked->sold + $locked->reserved;

            if ($data['quota'] < $inUse) {
                throw ValidationException::withMessages([
                    'quota' => "Kuota tidak boleh di bawah $inUse (sudah terjual atau sedang dicadangkan).",
                ]);
            }

            $locked->update($data);
            AuditLog::record('ticket_type.updated', $locked, ['price' => $locked->price, 'quota' => $locked->quota]);
        });

        return redirect()->route('admin.products.edit', $ticketType->product_id)->with('status', 'Tipe tiket disimpan.');
    }

    public function destroy(TicketType $ticketType)
    {
        if ($ticketType->reserved > 0) {
            return back()->withErrors(['ticket_type' => 'Masih ada pesanan menunggu pembayaran untuk tipe tiket ini.']);
        }

        $ticketType->delete(); // soft delete keeps past orders intact
        AuditLog::record('ticket_type.deleted', $ticketType);

        return back()->with('status', 'Tipe tiket dihapus.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'quota' => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_per_order' => ['required', 'integer', 'min:1', 'max:100'],
            'max_per_order' => ['required', 'integer', 'gte:min_per_order', 'max:100'],
            'sale_start' => ['nullable', 'date'],
            'sale_end' => ['nullable', 'date', 'after:sale_start'],
            'status' => ['required', Rule::in([TicketType::ACTIVE, TicketType::INACTIVE])],
        ]);
    }
}
