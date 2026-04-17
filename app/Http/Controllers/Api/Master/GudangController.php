<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class GudangController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Gudang::paginate(15));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_gudang' => 'required|string',
            'lokasi_gudang' => 'required|string',
            'kode_area' => 'required|string',
        ]);

        $gudang = Gudang::create($request->all());
        return $this->success($gudang, 'Gudang created successfully', 201);
    }

    public function show(string $id)
    {
        $gudang = Gudang::findOrFail($id);
        return $this->success($gudang);
    }

    public function update(Request $request, string $id)
    {
        $gudang = Gudang::findOrFail($id);
        $request->validate([
            'nama_gudang' => 'required|string',
            'lokasi_gudang' => 'required|string',
            'kode_area' => 'required|string',
        ]);

        $gudang->update($request->all());
        return $this->success($gudang, 'Gudang updated successfully');
    }

    public function destroy(string $id)
    {
        Gudang::findOrFail($id)->delete();
        return $this->success(null, 'Gudang deleted successfully');
    }
}
