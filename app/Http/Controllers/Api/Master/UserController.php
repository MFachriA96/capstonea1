<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\Cache;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $payload = Cache::remember('master:users:index:v1', now()->addMinutes(2), function () {
            $users = User::query()
                ->select([
                    'ID_user',
                    'nama',
                    'email',
                    'role',
                    'ID_vendor',
                    'ID_gudang',
                    'created_at',
                ])
                ->with([
                    'vendor:ID_vendor,nama_vendor,aktif',
                    'warehouse:ID_gudang,nama_gudang,lokasi_gudang,kode_area',
                ])
                ->orderByDesc('created_at')
                ->paginate(50);

            return UserResource::collection($users)->response()->getData(true);
        });

        return $this->success($payload);
    }
}
