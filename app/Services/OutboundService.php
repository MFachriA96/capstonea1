<?php

namespace App\Services;

use App\Models\Barang;
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
            $preparedData = $this->prepareOutboundData($data, $user);
            $nextSequence = DB::table('tabel_outbound')->count() + 1;
            $noPengiriman = 'DO-' . date('Ymd') . '-' . str_pad($nextSequence, 4, '0', STR_PAD_LEFT);

            $outbound = Outbound::create([
                'no_pengiriman' => $noPengiriman,
                'ID_vendor' => $preparedData['ID_vendor'],
                'waktu_kirim' => $preparedData['waktu_kirim'],
                'estimasi_tiba' => $preparedData['estimasi_tiba'] ?? null,
                'lokasi_asal' => $preparedData['lokasi_asal'],
                'status' => 'draft',
                'dibuat_oleh' => $user->ID_user,
            ]);

            foreach ($preparedData['details'] as $detail) {
                OutboundDetail::create([
                    'ID_outbound' => $outbound->ID_outbound,
                    'ID_barang' => $detail['ID_barang'],
                    'quantity_outbound' => $detail['quantity_outbound'],
                    'quantity_per_box' => $detail['quantity_per_box'],
                    'jumlah_box' => $detail['jumlah_box'],
                ]);
            }

            return $outbound->load('details.barang');
        });
    }

    public function updateOutbound(Outbound $outbound, array $data, User $user): Outbound
    {
        return DB::transaction(function () use ($outbound, $data, $user) {
            $preparedData = $this->prepareOutboundData($data, $user);

            $outbound->update([
                'ID_vendor' => $preparedData['ID_vendor'],
                'waktu_kirim' => $preparedData['waktu_kirim'],
                'estimasi_tiba' => $preparedData['estimasi_tiba'] ?? null,
                'lokasi_asal' => $preparedData['lokasi_asal'],
            ]);

            $outbound->details()->delete();

            foreach ($preparedData['details'] as $detail) {
                $outbound->details()->create([
                    'ID_barang' => $detail['ID_barang'],
                    'quantity_outbound' => $detail['quantity_outbound'],
                    'quantity_per_box' => $detail['quantity_per_box'],
                    'jumlah_box' => $detail['jumlah_box'],
                ]);
            }

            return $outbound->load('details.barang');
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

    protected function prepareOutboundData(array $data, User $user): array
    {
        $data['ID_vendor'] = $user->role === 'vendor'
            ? $user->ID_vendor
            : ($data['ID_vendor'] ?? null);

        $data['details'] = array_map(function (array $detail) {
            $detail['ID_barang'] = $this->resolveBarangId($detail);

            return $detail;
        }, $data['details']);

        return $data;
    }

    protected function resolveBarangId(array $detail): int
    {
        if (!empty($detail['ID_barang'])) {
            return (int) $detail['ID_barang'];
        }

        $namaBarang = trim((string) ($detail['nama_barang'] ?? ''));

        $barang = Barang::query()
            ->where('nama_barang', $namaBarang)
            ->orWhere('part_name', $namaBarang)
            ->first();

        if ($barang) {
            return $barang->ID_barang;
        }

        return Barang::create([
            'part_code' => $this->generatePartCode(),
            'part_name' => $namaBarang,
            'nama_barang' => $namaBarang,
            'satuan' => $detail['satuan'] ?? 'pcs',
            'deskripsi' => 'Auto-created from manual outbound input.',
        ])->ID_barang;
    }

    protected function generatePartCode(): string
    {
        do {
            $partCode = 'AUTO-' . Str::upper(Str::random(8));
        } while (Barang::where('part_code', $partCode)->exists());

        return $partCode;
    }
}
