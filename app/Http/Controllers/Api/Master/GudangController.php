<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Models\Gudang;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class GudangController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $page = request()->integer('page', 1);
        $version = Cache::get('master:gudang:index:version', 1);
        $cacheKey = "master:gudang:index:page:{$page}:v{$version}";

        return $this->success(Cache::remember($cacheKey, now()->addMinutes(10), fn () => Gudang::paginate(15)));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_gudang' => 'required|string',
            'lokasi_gudang' => 'required|string',
            'kode_area' => 'required|string',
        ]);

        $gudang = Gudang::create($request->all());
        Cache::increment('master:gudang:index:version');
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
        Cache::increment('master:gudang:index:version');
        return $this->success($gudang, 'Gudang updated successfully');
    }

    public function destroy(string $id)
    {
        Gudang::findOrFail($id)->delete();
        Cache::increment('master:gudang:index:version');
        return $this->success(null, 'Gudang deleted successfully');
    }
}
