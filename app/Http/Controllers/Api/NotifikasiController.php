<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotifikasiResource;
use App\Models\Notifikasi;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $notifs = Notifikasi::where('ID_user', $request->user()->ID_user)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return $this->success(NotifikasiResource::collection($notifs)->response()->getData(true));
    }

    public function markRead(Request $request, string $id)
    {
        $notif = Notifikasi::where('ID_user', $request->user()->ID_user)->findOrFail($id);
        $notif->update(['sudah_dibaca' => true]);

        return $this->success(new NotifikasiResource($notif), 'Notification marked as read');
    }

    public function markAllRead(Request $request)
    {
        Notifikasi::where('ID_user', $request->user()->ID_user)
            ->where('sudah_dibaca', false)
            ->update(['sudah_dibaca' => true]);

        return $this->success(null, 'All notifications marked as read');
    }

    public function unreadCount(Request $request)
    {
        $count = Notifikasi::where('ID_user', $request->user()->ID_user)
            ->where('sudah_dibaca', false)
            ->count();

        return $this->success(['unread_count' => $count]);
    }
}
