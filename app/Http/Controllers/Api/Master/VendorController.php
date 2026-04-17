<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Vendor::paginate(15));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_vendor' => 'required|string',
            'lokasi_vendor' => 'required|string',
            'kontak' => 'required|string',
            'email_vendor' => 'required|email',
            'aktif' => 'boolean',
        ]);

        $vendor = Vendor::create($request->all());
        return $this->success($vendor, 'Vendor created successfully', 201);
    }

    public function show(string $id)
    {
        $vendor = Vendor::findOrFail($id);
        return $this->success($vendor);
    }

    public function update(Request $request, string $id)
    {
        $vendor = Vendor::findOrFail($id);
        $request->validate([
            'nama_vendor' => 'required|string',
            'lokasi_vendor' => 'required|string',
            'kontak' => 'required|string',
            'email_vendor' => 'required|email',
            'aktif' => 'boolean',
        ]);

        $vendor->update($request->all());
        return $this->success($vendor, 'Vendor updated successfully');
    }

    public function destroy(string $id)
    {
        Vendor::findOrFail($id)->delete();
        return $this->success(null, 'Vendor deleted successfully');
    }
}
