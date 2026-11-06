<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ProblemDetails;
use App\Http\Controllers\Controller;
use App\Http\Resources\BukuResource;
use App\Models\Buku;
use App\Traits\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BukuApiController extends Controller
{
    use ApiResponse;

    /**
     * Menampilkan katalog buku publik dengan pencarian, filter, dan pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Buku::with('kategori')->withCount('peminjamans');

        // Pencarian berdasarkan judul, penulis, dan ISBN
        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('judul', 'like', '%'.$search.'%')
                    ->orWhere('penulis', 'like', '%'.$search.'%')
                    ->orWhere('isbn', 'like', '%'.$search.'%');
            });
        }

        // Filter berdasarkan kategori_id
        if ($request->filled('kategori_id')) {
            $query->where('kategori_id', $request->query('kategori_id'));
        }

        // Filter ketersediaan (tersedia=1 berarti stok > 0)
        if ($request->query('tersedia') === '1') {
            $query->where('stok', '>', 0);
        }

        // Sorting
        $sort = $request->query('sort');
        match ($sort) {
            'judul_asc' => $query->orderBy('judul', 'asc'),
            'judul_desc' => $query->orderBy('judul', 'desc'),
            'tahun_terbit_asc' => $query->orderBy('tahun_terbit', 'asc'),
            'tahun_terbit_desc' => $query->orderBy('tahun_terbit', 'desc'),
            'stok_asc' => $query->orderBy('stok', 'asc'),
            'stok_desc' => $query->orderBy('stok', 'desc'),
            default => $query->latest('id'),
        };

        $perPage = (int) $request->query('per_page', 10);
        $bukus = $query->paginate(max(1, min(100, $perPage)))->withQueryString();

        return BukuResource::collection($bukus);
    }

    /**
     * Menampilkan detail satu buku.
     */
    public function show(int $id): BukuResource
    {
        $buku = Buku::with(['kategori', 'peminjamans'])->withCount('peminjamans')->findOrFail($id);

        return new BukuResource($buku);
    }

    /**
     * Menyimpan data buku baru (Wajib Bearer Token).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'isbn' => ['required', 'string', 'digits_between:10,13', 'unique:bukus,isbn'],
            'judul' => ['required', 'string', 'min:5', 'max:255'],
            'penulis' => ['required', 'string', 'max:150'],
            'penerbit' => ['required', 'string', 'max:150'],
            'tahun_terbit' => ['required', 'integer', 'between:1900,'.now()->year],
            'kategori_id' => ['required', 'integer', 'exists:kategoris,id'],
            'stok' => ['required', 'integer', 'min:0'],
            'sinopsis' => ['nullable', 'string'],
        ]);

        $buku = Buku::create($validated);
        $buku->load('kategori');

        $location = url('/api/v1/bukus/'.$buku->id);

        return (new BukuResource($buku))
            ->response()
            ->setStatusCode(201)
            ->header('Location', $location);
    }

    /**
     * Full Replacement data buku (PUT).
     * Seluruh atribut wajib dikirimkan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $buku = Buku::findOrFail($id);

        $validated = $request->validate([
            'isbn' => ['required', 'string', 'digits_between:10,13', Rule::unique('bukus', 'isbn')->ignore($buku->id)],
            'judul' => ['required', 'string', 'min:5', 'max:255'],
            'penulis' => ['required', 'string', 'max:150'],
            'penerbit' => ['required', 'string', 'max:150'],
            'tahun_terbit' => ['required', 'integer', 'between:1900,'.now()->year],
            'kategori_id' => ['required', 'integer', 'exists:kategoris,id'],
            'stok' => ['required', 'integer', 'min:0'],
            'sinopsis' => ['nullable', 'string'],
        ]);

        $buku->update($validated);
        $buku->load('kategori');

        return (new BukuResource($buku))->response()->setStatusCode(200);
    }

    /**
     * Partial Update data buku (PATCH).
     * Memperbolehkan pembaruan sebagian atribut saja.
     */
    public function patch(Request $request, int $id): JsonResponse
    {
        $buku = Buku::findOrFail($id);

        $validated = $request->validate([
            'isbn' => ['sometimes', 'required', 'string', 'digits_between:10,13', Rule::unique('bukus', 'isbn')->ignore($buku->id)],
            'judul' => ['sometimes', 'required', 'string', 'min:5', 'max:255'],
            'penulis' => ['sometimes', 'required', 'string', 'max:150'],
            'penerbit' => ['sometimes', 'required', 'string', 'max:150'],
            'tahun_terbit' => ['sometimes', 'required', 'integer', 'between:1900,'.now()->year],
            'kategori_id' => ['sometimes', 'required', 'integer', 'exists:kategoris,id'],
            'stok' => ['sometimes', 'required', 'integer', 'min:0'],
            'sinopsis' => ['sometimes', 'nullable', 'string'],
        ]);

        $buku->update($validated);
        $buku->load('kategori');

        return (new BukuResource($buku))->response()->setStatusCode(200);
    }

    /**
     * Menghapus buku (DELETE).
     * Menolak penghapusan jika ada peminjaman aktif (409 Conflict).
     */
    public function destroy(Request $request, int $id): Response|JsonResponse
    {
        $buku = Buku::findOrFail($id);

        $hasActiveLoans = $buku->peminjamans()->where('status', 'dipinjam')->exists();
        if ($hasActiveLoans) {
            return ProblemDetails::create(
                409,
                'Conflict',
                'Buku tidak dapat dihapus karena masih ada transaksi peminjaman yang berstatus dipinjam.',
                $request
            );
        }

        $buku->delete();

        return response()->noContent();
    }

    /**
     * REST Service Consumer: Mengambil metadata referensi eksternal dari OpenLibrary API.
     */
    public function referensiEksternal(Request $request, int $id): JsonResponse
    {
        $buku = Buku::findOrFail($id);
        $cleanIsbn = preg_replace('/[^0-9X]/i', '', $buku->isbn);
        $bibKey = 'ISBN:'.$cleanIsbn;
        $url = 'https://openlibrary.org/api/books?bibkeys='.urlencode($bibKey).'&format=json&jscmd=data';

        $source = 'openlibrary';
        $found = false;
        $externalData = null;

        try {
            $response = Http::timeout(8)->get($url);

            if ($response->successful()) {
                $json = $response->json();
                if (isset($json[$bibKey])) {
                    $item = $json[$bibKey];
                    $found = true;
                    $externalData = [
                        'bibkey' => $bibKey,
                        'title' => $item['title'] ?? null,
                        'publishers' => isset($item['publishers']) ? array_column($item['publishers'], 'name') : [],
                        'publish_date' => $item['publish_date'] ?? null,
                        'number_of_pages' => $item['number_of_pages'] ?? null,
                        'edition_name' => $item['edition_name'] ?? null,
                        'preview_url' => $item['preview_url'] ?? ($item['url'] ?? null),
                        'cover' => $item['cover'] ?? null,
                    ];
                }
            }
        } catch (Exception $e) {
            Log::warning('[REST CONSUMER OPENLIBRARY TIMEOUT/ERROR]: '.$e->getMessage());
        }

        return response()->json([
            'buku_local' => [
                'id' => $buku->id,
                'isbn' => $buku->isbn,
                'judul' => $buku->judul,
                'penulis' => $buku->penulis,
            ],
            'external_source' => $source,
            'found' => $found,
            'metadata' => $externalData,
            'fallback_applied' => ! $found,
            'note' => $found
                ? 'Metadata buku eksternal berhasil diambil dari OpenLibrary.'
                : 'Referensi buku tidak ditemukan di OpenLibrary atau layanan sedang tidak dapat dijangkau. Menampilkan data lokal SIPUS.',
        ], 200);
    }
}
