@props(['title' => 'SIPUS-Del'])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} | SIPUS-Del</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <style>
        .navbar-sipus { background-color: #4C1D95; }
        .navbar-sipus .nav-link { color: #fff; border-radius: .375rem; padding: .5rem .75rem; }
        .navbar-sipus .nav-link:hover, .navbar-sipus .nav-link:focus,
        .navbar-sipus .nav-link.active { background-color: #3B0764; color: #fff; }
        .sinopsis { white-space: pre-wrap; overflow-wrap: anywhere; }
    </style>
</head>
<body class="bg-light">
    <a class="visually-hidden-focusable" href="#konten">Lewati ke konten</a>
    <nav class="navbar navbar-dark navbar-sipus" aria-label="Navigasi utama">
        <div class="container gap-3">
            <a class="navbar-brand fw-bold" href="{{ route('buku.index') }}">SIPUS-Del</a>
            <div class="navbar-nav flex-row gap-2">
                @auth
                    <a class="nav-link" href="{{ route('peminjaman.index') }}">Peminjaman</a>
                    <span class="text-white small">{{ auth()->user()->name }}<br>Masuk: {{ session('login_timestamp', '—') }}</span>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-light">Keluar</button></form>
                @else
                    <a class="nav-link" href="{{ route('login') }}">Masuk</a>
                @endauth
                <a class="nav-link {{ request()->routeIs('buku.*') ? 'active' : '' }}" href="{{ route('buku.index') }}" @if(request()->routeIs('buku.*')) aria-current="page" @endif>Buku</a>
                <a class="nav-link {{ request()->routeIs('kategori.*') ? 'active' : '' }}" href="{{ route('kategori.index') }}" @if(request()->routeIs('kategori.*')) aria-current="page" @endif>Kategori</a>
            </div>
        </div>
    </nav>
    <main id="konten" class="container py-4">
        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger" role="alert">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif
        {{ $slot }}
    </main>
    <footer class="container pb-4 text-secondary small">SIPUS-Del · Sistem Informasi Perpustakaan Kampus</footer>
</body>
</html>
