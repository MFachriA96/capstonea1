<?php

namespace App\Http\Controllers\Api\Master;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;

class UserController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $users = User::with('vendor')
            ->orderByDesc('created_at')
            ->paginate(50);

        return $this->success(UserResource::collection($users));
    }
}
