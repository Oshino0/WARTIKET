<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BannerController extends Controller
{
    public function index()
    {
        return view('admin.banners.index', ['banners' => Banner::orderBy('sort_order')->orderBy('id')->get()]);
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'image' => ['required', 'image', 'max:3072'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'image_position_x' => ['nullable', 'integer', 'between:0,100'],
            'image_position_y' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $banner = Banner::create([
            'title' => $data['title'],
            'image' => $this->storeImage($request),
            'link_url' => $data['link_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'image_position_x' => $data['image_position_x'] ?? 50,
            'image_position_y' => $data['image_position_y'] ?? 50,
            'is_active' => true,
        ]);
        AuditLog::record('banner.created', $banner);

        return back()->with('status', 'Banner ditambahkan.');
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'image' => ['nullable', 'image', 'max:3072'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'image_position_x' => ['nullable', 'integer', 'between:0,100'],
            'image_position_y' => ['nullable', 'integer', 'between:0,100'],
        ]);

        $oldImage = $banner->image;
        $image = $request->hasFile('image') ? $this->storeImage($request) : $oldImage;

        $banner->update([
            'title' => $data['title'],
            'image' => $image,
            'link_url' => $data['link_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? $banner->sort_order,
            'image_position_x' => $data['image_position_x'] ?? $banner->image_position_x,
            'image_position_y' => $data['image_position_y'] ?? $banner->image_position_y,
        ]);

        if ($image !== $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        AuditLog::record('banner.updated', $banner);

        return redirect()->route('admin.banners.index')->with('status', 'Banner diperbarui.');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => ! $banner->is_active]);

        return back()->with('status', $banner->is_active ? 'Banner diaktifkan.' : 'Banner dinonaktifkan.');
    }

    public function destroy(Banner $banner)
    {
        Storage::disk('public')->delete($banner->image);
        $banner->delete();
        AuditLog::record('banner.deleted', $banner);

        return back()->with('status', 'Banner dihapus.');
    }

    private function storeImage(Request $request): string
    {
        $image = $request->file('image');
        if (! $image instanceof UploadedFile) {
            throw new RuntimeException('Gambar banner tidak tersedia untuk disimpan.');
        }

        $path = $image->store('banners', 'public');

        if ($path === false) {
            throw new RuntimeException('Gambar banner gagal disimpan.');
        }

        return $path;
    }
}
