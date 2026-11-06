<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ProblemDetails;
use App\Http\Controllers\Controller;
use App\Http\Resources\PeminjamanResource;
use App\Models\Buku;
use App\Models\Peminjaman;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PeminjamanApiController extends Controller
{
    use ApiResponse;

    /**
     * Menampilkan daftar transaksi peminjaman (Wajib Bearer Token).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Peminjaman::with('buku');

        // Filter berdasarkan status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter berdasarkan NIM peminjam
        if ($request->filled('nim_peminjam')) {
            $query->where('nim_peminjam', strtoupper(trim($request->query('nim_peminjam'))));
        }

        $perPage = (int) $request->query('per_page', 10);
        $peminjamans = $query->latest('id')->paginate(max(1, min(100, $perPage)))->withQueryString();

        return PeminjamanResource::collection($peminjamans);
    }

    /**
     * Menampilkan detail satu transaksi peminjaman.
     */
    public function show(int $id): PeminjamanResource
    {
        $peminjaman = Peminjaman::with('buku')->findOrFail($id);

        return new PeminjamanResource($peminjaman);
    }

    /**
     * Membuat transaksi peminjaman baru berbasis ACID transaction dan lockForUpdate.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'buku_id' => ['required', 'integer'],
            'nim_peminjam' => ['required', 'string', 'min:6', 'max:15'],
            'nama_peminjam' => ['required', 'string', 'min:3', 'max:150'],
            'durasi_hari' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $bukuId = $validated['buku_id'];
        $nim = strtoupper(trim($validated['nim_peminjam']));
        $nama = trim($validated['nama_peminjam']);
        $durasiHari = (int) ($validated['durasi_hari'] ?? 7);

        // Eksekusi transaksi database ACID
        return DB::transaction(function () use ($request, $bukuId, $nim, $nama, $durasiHari) {
            // 1. Kunci baris buku secara eksklusif (Pessimistic Locking)
            $buku = Buku::where('id', $bukuId)->lockForUpdate()->first();

            if (! $buku) {
                return ProblemDetails::create(
                    404,
                    'Not Found',
                    "Buku dengan ID {$bukuId} tidak ditemukan.",
                    $request
                );
            }

            // 2. Periksa ketersediaan stok
            if ($buku->stok < 1) {
                return ProblemDetails::create(
                    422,
                    'Unprocessable Content',
                    'Stok buku habis. Transaksi peminjaman ditolak.',
                    $request
                );
            }

            // 3. Periksa apakah mahasiswa masih meminjam buku yang sama
            $hasActiveLoan = $buku->peminjamans()
                ->where('nim_peminjam', $nim)
                ->where('status', 'dipinjam')
                ->exists();

            if ($hasActiveLoan) {
                return ProblemDetails::create(
                    409,
                    'Conflict',
                    "Mahasiswa dengan NIM {$nim} masih memiliki pinjaman aktif untuk buku ini.",
                    $request
                );
            }

            // 4. Kurangi stok buku
            $buku->decrement('stok');

            // 5. Catat transaksi peminjaman baru
            $peminjaman = $buku->peminjamans()->create([
                'kode_pinjam' => 'PJM-'.strtoupper(Str::random(8)),
                'nim_peminjam' => $nim,
                'nama_peminjam' => $nama,
                'tanggal_pinjam' => today(),
                'tanggal_jatuh_tempo' => today()->addDays($durasiHari),
                'status' => 'dipinjam',
            ]);

            $peminjaman->load('buku');
            $location = url('/api/v1/peminjamans/'.$peminjaman->id);

            return (new PeminjamanResource($peminjaman))
                ->response()
                ->setStatusCode(201)
                ->header('Location', $location);
        }, 3);
    }

    /**
     * Memproses pengembalian buku berbasis ACID transaction dan lockForUpdate.
     */
    public function kembalikan(Request $request, int $id): JsonResponse
    {
        return DB::transaction(function () use ($request, $id) {
            $peminjaman = Peminjaman::find($id);

            if (! $peminjaman) {
                return ProblemDetails::create(
                    404,
                    'Not Found',
                    "Transaksi peminjaman dengan ID {$id} tidak ditemukan.",
                    $request
                );
            }

            // Kunci baris buku terlebih dahulu untuk konsistensi urutan kunci (mencegah deadlock)
            $buku = Buku::where('id', $peminjaman->buku_id)->lockForUpdate()->firstOrFail();

            // Kunci baris peminjaman
            $peminjamanLocked = Peminjaman::where('id', $id)->lockForUpdate()->firstOrFail();

            // Cegah double return (konflik 409)
            if ($peminjamanLocked->status === 'dikembalikan') {
                return ProblemDetails::create(
                    409,
                    'Conflict',
                    'Buku pada transaksi ini sudah dikembalikan sebelumnya. Operasi ditolak.',
                    $request
                );
            }

            // Update status pengembalian
            $peminjamanLocked->update([
                'status' => 'dikembalikan',
                'tanggal_kembali' => today(),
            ]);

            // Pulihkan stok buku
            $buku->increment('stok');

            $peminjamanLocked->load('buku');

            return (new PeminjamanResource($peminjamanLocked))
                ->response()
                ->setStatusCode(200);
        }, 3);
    }
}
