<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BarangController extends Controller
{
    use ApiResponse;

    public function index()
    {
        return $this->success(Barang::paginate(15));
    }

    public function options()
    {
        $cacheKey = 'master:barang:options:v1';

        return $this->success(Cache::remember($cacheKey, now()->addMinutes(10), fn () => Barang::query()
            ->select('ID_barang', 'part_code', 'part_name', 'nama_barang', 'satuan')
            ->orderBy('nama_barang')
            ->get()));
    }

    public function store(Request $request)
    {
        $request->validate([
            'part_code' => 'required|string|unique:tabel_barang,part_code',
            'part_name' => 'required|string',
            'nama_barang' => 'required|string',
            'berat_gram' => 'nullable|numeric',
            'satuan' => 'required|string',
            'deskripsi' => 'nullable|string',
        ]);

        $barang = Barang::create($request->all());
        Cache::forget('master:barang:options:v1');
        return $this->success($barang, 'Barang created successfully', 201);
    }

    public function show(string $id)
    {
        $barang = Barang::findOrFail($id);
        return $this->success($barang);
    }

    public function update(Request $request, string $id)
    {
        $barang = Barang::findOrFail($id);
        $request->validate([
            'part_code' => "required|string|unique:tabel_barang,part_code,{$id},ID_barang",
            'part_name' => 'required|string',
            'nama_barang' => 'required|string',
            'berat_gram' => 'nullable|numeric',
            'satuan' => 'required|string',
            'deskripsi' => 'nullable|string',
        ]);

        $barang->update($request->all());
        Cache::forget('master:barang:options:v1');
        return $this->success($barang, 'Barang updated successfully');
    }

    public function destroy(string $id)
    {
        Barang::findOrFail($id)->delete();
        Cache::forget('master:barang:options:v1');
        return $this->success(null, 'Barang deleted successfully');
    }
}
