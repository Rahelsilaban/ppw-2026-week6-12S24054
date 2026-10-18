<details class="mt-2">
    <summary>Pinjam</summary>
    <form method="POST" action="{{ route('peminjaman.pinjam', $buku) }}" class="mt-2">
        @csrf
        <label for="nim-{{ $buku->id }}" class="form-label">NIM Peminjam</label>
        <input id="nim-{{ $buku->id }}" name="nim_peminjam" class="form-control" required minlength="6" maxlength="15" value="{{ old('nim_peminjam', session('last_borrower.nim_peminjam')) }}">
        <label for="nama-{{ $buku->id }}" class="form-label">Nama Peminjam</label>
        <input id="nama-{{ $buku->id }}" name="nama_peminjam" class="form-control" required minlength="3" maxlength="150" value="{{ old('nama_peminjam', session('last_borrower.nama_peminjam')) }}">
        <button class="btn btn-sm btn-primary mt-2">Pinjam</button>
    </form>
</details>
