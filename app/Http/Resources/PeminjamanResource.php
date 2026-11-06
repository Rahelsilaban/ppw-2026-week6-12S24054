<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PeminjamanResource extends JsonResource
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
                'href' => url('/api/v1/peminjamans/'.$this->id),
                'method' => 'GET',
            ],
            'buku' => [
                'href' => url('/api/v1/bukus/'.$this->buku_id),
                'method' => 'GET',
            ],
        ];

        // HATEOAS Dinamis: Tampilkan aksi kembalikan hanya jika status masih 'dipinjam'
        if ($this->status === 'dipinjam') {
            $links['kembalikan_buku'] = [
                'href' => url('/api/v1/peminjamans/'.$this->id.'/kembalikan'),
                'method' => 'PATCH',
                'description' => 'Mengembalikan buku dan memulihkan stok perpustakaan',
            ];
        }

        return [
            'id' => $this->id,
            'kode_pinjam' => $this->kode_pinjam,
            'buku_id' => $this->buku_id,
            'nim_peminjam' => $this->nim_peminjam,
            'nama_peminjam' => $this->nama_peminjam,
            'tanggal_pinjam' => $this->tanggal_pinjam?->format('Y-m-d'),
            'tanggal_jatuh_tempo' => $this->tanggal_jatuh_tempo?->format('Y-m-d'),
            'tanggal_kembali' => $this->tanggal_kembali?->format('Y-m-d'),
            'status' => $this->status,
            'buku' => $this->whenLoaded('buku', function () {
                return [
                    'id' => $this->buku->id,
                    'isbn' => $this->buku->isbn,
                    'judul' => $this->buku->judul,
                    'penulis' => $this->buku->penulis,
                ];
            }),
            '_links' => $links,
        ];
    }
}
