<x-layout title="Daftar Buku">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <h1 class="h3 mb-0">Daftar Buku</h1>
        <a href="{{ route('buku.create') }}" class="btn btn-primary">Tambah Buku</a>
    </div>
    <div class="row g-3 mb-4">
        @foreach ($statistik as $label => $nilai)
            <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body"><div>{{ $label }}</div><strong class="fs-3">{{ $nilai }}</strong></div></div></div>
        @endforeach
    </div>
    <form method="GET" action="{{ route('buku.index') }}" class="row g-2 mb-3">
        <div class="col-md-6"><label for="search" class="form-label">Cari judul, penulis, atau ISBN</label><input id="search" name="search" class="form-control" value="{{ $search }}"></div>
        <div class="col-md-4"><label for="kategori" class="form-label">Kategori</label><select id="kategori" name="kategori" class="form-select"><option value="">Semua kategori</option>@foreach ($kategoris as $kategori)<option value="{{ $kategori->id }}" @selected(request('kategori') == $kategori->id)>{{ $kategori->nama_kategori }}</option>@endforeach</select></div>
        <div class="col-md-2 align-self-end"><button class="btn btn-primary">Cari</button></div>
    </form>
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
                            <td>{{ $buku->stok }}<br><small class="text-secondary">{{ $buku->peminjamans_count }} kali dipinjam</small></td>
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
