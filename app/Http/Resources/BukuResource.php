<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BukuResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $links = [
            'self' => [
                'href' => url('/api/v1/bukus/'.$this->id),
                'method' => 'GET',
            ],
            'kategori' => [
                'href' => url('/kategori/'.$this->kategori_id),
                'method' => 'GET',
            ],
            'referensi_eksternal' => [
                'href' => url('/api/v1/bukus/'.$this->id.'/referensi-eksternal'),
                'method' => 'GET',
            ],
        ];

        // HATEOAS Dinamis: Aksi pinjam hanya tersedia bila stok > 0
        if ($this->stok > 0) {
            $links['pinjam_buku'] = [
                'href' => url('/api/v1/peminjamans'),
                'method' => 'POST',
                'description' => 'Membuat transaksi peminjaman untuk buku ini',
            ];
        }

        return [
            'id' => $this->id,
            'isbn' => $this->isbn,
            'judul' => $this->judul,
            'penulis' => $this->penulis,
            'penerbit' => $this->penerbit,
            'tahun_terbit' => (int) $this->tahun_terbit,
            'stok' => (int) $this->stok,
            'sinopsis' => $this->sinopsis,
            'kategori_id' => $this->kategori_id,
            'kategori' => $this->whenLoaded('kategori', function () {
                return [
                    'id' => $this->kategori->id,
                    'kode_kategori' => $this->kategori->kode_kategori,
                    'nama_kategori' => $this->kategori->nama_kategori,
                ];
            }),
            'total_dipinjam' => $this->peminjamans_count ?? ($this->relationLoaded('peminjamans') ? $this->peminjamans->count() : null),
            'riwayat_peminjaman' => PeminjamanResource::collection($this->whenLoaded('peminjamans')),
            '_links' => $links,
        ];
    }
}

