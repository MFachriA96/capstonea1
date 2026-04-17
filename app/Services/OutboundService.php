<?php

namespace App\Services;

use App\Models\Outbound;
use App\Models\OutboundDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OutboundService
{
    public function createOutbound(array $data, User $user): Outbound
    {
        return DB::transaction(function () use ($data, $user) {
            $nextSequence = DB::table('tabel_outbound')->count() + 1;
            $noPengiriman = 'DO-' . date('Ymd') . '-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

            $outbound = Outbound::create([
                'no_pengiriman' => $noPengiriman,
                'ID_vendor' => $data['ID_vendor'],
                'waktu_kirim' => $data['waktu_kirim'],
                'estimasi_tiba' => $data['estimasi_tiba'] ?? null,
                'lokasi_asal' => $data['lokasi_asal'],
                'status' => 'draft',
                'dibuat_oleh' => $user->ID_user,
            ]);

            foreach ($data['details'] as $detail) {
                OutboundDetail::create([
                    'ID_outbound' => $outbound->ID_outbound,
                    'ID_barang' => $detail['ID_barang'],
                    'quantity_outbound' => $detail['quantity_outbound'],
                    'quantity_per_box' => $detail['quantity_per_box'],
                    'jumlah_box' => $detail['jumlah_box'],
                ]);
            }

            return $outbound->load('details');
        });
    }

    public function submitOutbound(int $id, User $user): Outbound
    {
        return DB::transaction(function () use ($id, $user) {
            $outbound = Outbound::findOrFail($id);

            if ($outbound->status !== 'draft') {
                abort(400, 'Cannot submit outbound that is not in draft status.');
            }

            if ($user->role === 'vendor' && $user->ID_vendor !== $outbound->ID_vendor) {
                abort(403, 'Unauthorized to submit this outbound.');
            }

            $outbound->update([
                'status' => 'submitted',
            ]);

            foreach ($outbound->details as $detail) {
                $detail->update([
                    'qr_token' => Str::uuid()->toString(),
                ]);
            }

            return $outbound;
        });
    }
}
