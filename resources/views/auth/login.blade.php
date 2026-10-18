<x-layout title="Masuk Pustakawan">
    <div class="card shadow-sm mx-auto" style="max-width: 460px"><div class="card-body">
        <h1 class="h3 mb-4">Masuk Pustakawan</h1>
        <form method="POST" action="{{ route('login.store') }}">
            @csrf
            <label for="email" class="form-label">Email</label>
            <input id="email" name="email" type="email" class="form-control mb-3" value="{{ old('email', $email) }}" autocomplete="username" required>
            <label for="password" class="form-label">Kata Sandi</label>
            <input id="password" name="password" type="password" class="form-control mb-3" autocomplete="current-password" required>
            <div class="form-check mb-3"><input id="remember" name="remember" type="checkbox" value="1" class="form-check-input"><label for="remember" class="form-check-label">Ingat saya</label></div>
            <button class="btn btn-primary">Masuk</button>
        </form>
    </div></div>
</x-layout>
