<?php

namespace App\Http\Controllers;

use App\Models\Buku;
use App\Models\Peminjaman;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PeminjamanController extends Controller
{
    public function index()
    {
        $peminjamans = Peminjaman::with('buku')->latest()->paginate(10);
        return view('peminjaman.index', compact('peminjamans'));
    }

    public function pinjam(Request $request, int $id)
    {
        if (is_string($request->nim_peminjam)) {
            $request->merge(['nim_peminjam' => strtoupper(trim($request->nim_peminjam))]);
        }
        $data = $request->validate([
            'nim_peminjam' => 'required|string|min:6|max:15',
            'nama_peminjam' => 'required|string|min:3|max:150',
        ], [
            'required' => ':attribute wajib diisi.',
            'string' => ':attribute harus berupa teks.',
            'min' => ':attribute minimal :min karakter.',
            'max' => ':attribute maksimal :max karakter.',
        ]);
        try {
            DB::transaction(function () use ($id, $data) {
                $buku = Buku::where('id', $id)->lockForUpdate()->firstOrFail();
                if ($buku->stok < 1) {
                    throw new RuntimeException('Stok buku habis. Peminjaman ditolak.');
                }
                if ($buku->peminjamans()->where('nim_peminjam', $data['nim_peminjam'])->where('status', 'dipinjam')->exists()) {
                    throw new RuntimeException('NIM ini masih meminjam buku yang sama.');
                }
                $buku->decrement('stok');
                $buku->peminjamans()->create($data + [
                    'kode_pinjam' => 'PJM-'.strtoupper(Str::random(8)),
                    'tanggal_pinjam' => today(),
                    'tanggal_jatuh_tempo' => today()->addDays(7),
                    'status' => 'dipinjam',
                ]);
            }, 3);
        } catch (QueryException $e) {
            Log::error('[DB EXCEPTION PINJAM]: '.$e->getMessage());
            return back()->withInput()->with('error', 'Peminjaman gagal karena gangguan database. Silakan coba lagi.');
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
        $request->session()->put('last_borrower', $data);
        return back()->with('success', 'Buku berhasil dipinjam. Batas pengembalian 7 hari.');
    }

    public function kembali(int $id)
    {
        try {
            DB::transaction(function () use ($id) {
                // Semua transaksi mengunci buku lebih dahulu untuk mengurangi deadlock.
                $bukuId = Peminjaman::findOrFail($id)->buku_id;
                $buku = Buku::where('id', $bukuId)->lockForUpdate()->firstOrFail();
                $peminjaman = Peminjaman::where('id', $id)->lockForUpdate()->firstOrFail();
                if ($peminjaman->status === 'dikembalikan') {
                    throw new RuntimeException('Buku ini sudah dikembalikan.');
                }
                $peminjaman->update(['status' => 'dikembalikan', 'tanggal_kembali' => today()]);
                $buku->increment('stok');
            }, 3);
        } catch (QueryException $e) {
            Log::error('[DB EXCEPTION KEMBALI]: '.$e->getMessage());
            return back()->with('error', 'Pengembalian gagal karena gangguan database. Silakan coba lagi.');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        return back()->with('success', 'Buku berhasil dikembalikan.');
    }
}
