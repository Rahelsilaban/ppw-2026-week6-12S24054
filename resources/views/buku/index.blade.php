<x-layout title="Daftar Buku">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0">Daftar Buku</h1>
        <a href="{{ route('buku.create') }}" class="btn btn-primary">Tambah Buku</a>
    </div>
    <div class="card shadow-sm mb-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <caption class="px-3">Koleksi buku perpustakaan kampus</caption>
                <thead><tr><th scope="col">ISBN</th><th scope="col">Judul</th><th scope="col">Penulis</th><th scope="col">Kategori</th><th scope="col">Stok</th><th scope="col">Aksi</th></tr></thead>
                <tbody>
                    @forelse ($bukus as $buku)
                        <tr>
                            <td>{{ $buku->isbn }}</td>
                            <td>{{ $buku->judul }}</td>
                            <td>{{ $buku->penulis }}</td>
                            <td>{{ $buku->kategori->nama_kategori }}</td>
                            <td>{{ $buku->stok }}</td>
                            <td class="text-nowrap">
                                <a href="{{ route('buku.show', $buku) }}" class="btn btn-sm btn-outline-primary" aria-label="Detail {{ $buku->judul }}">Detail</a>
                                <a href="{{ route('buku.edit', $buku) }}" class="btn btn-sm btn-outline-secondary" aria-label="Edit {{ $buku->judul }}">Edit</a>
                                <form action="{{ route('buku.destroy', $buku) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus buku ini?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Hapus {{ $buku->judul }}">Hapus</button>
                        </form>
                                @include('buku._pinjam')
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-secondary py-4">Belum ada buku. Tambahkan buku pertama Anda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    {{ $bukus->links() }}
</x-layout>
