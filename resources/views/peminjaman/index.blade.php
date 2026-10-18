<x-layout title="Daftar Peminjaman">
    <h1 class="h3 mb-4">Daftar Peminjaman</h1>
    <div class="card shadow-sm table-responsive">
        <table class="table table-hover align-middle">
            <thead><tr><th>Kode</th><th>Buku</th><th>Peminjam</th><th>Tanggal Pinjam</th><th>Jatuh Tempo</th><th>Tanggal Kembali</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
            @forelse ($peminjamans as $peminjaman)
                <tr>
                    <td>{{ $peminjaman->kode_pinjam }}</td><td>{{ $peminjaman->buku->judul }}</td>
                    <td>{{ $peminjaman->nim_peminjam }} — {{ $peminjaman->nama_peminjam }}</td>
                    <td>{{ $peminjaman->tanggal_pinjam->format('d-m-Y') }}</td><td>{{ $peminjaman->tanggal_jatuh_tempo->format('d-m-Y') }}</td>
                    <td>{{ $peminjaman->tanggal_kembali?->format('d-m-Y') ?? '—' }}</td><td>{{ ucfirst($peminjaman->status) }}</td>
                    <td>@if ($peminjaman->status === 'dipinjam')
                        <form method="POST" action="{{ route('peminjaman.kembali', $peminjaman) }}">@csrf<button class="btn btn-sm btn-primary">Kembalikan</button></form>
                    @endif</td>
                </tr>
            @empty
                <tr><td colspan="8">Belum ada peminjaman.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    {{ $peminjamans->links() }}
</x-layout>
