<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk Admin · reaple.id</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #111827, #1e3a8a); min-height: 100vh; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
<div class="card shadow border-0" style="width: 100%; max-width: 400px;">
    <div class="card-body p-4">
        <div class="text-center mb-4">
            <i class="bi bi-phone-vibrate text-primary" style="font-size: 2.5rem;"></i>
            <h1 class="h4 mt-2 mb-0">reaple.id</h1>
            <small class="text-secondary">Panel Admin</small>
        </div>

        @if (session('success'))
            <div class="alert alert-success py-2">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.submit') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}"
                       class="form-control @error('email') is-invalid @enderror" autocomplete="username" autofocus required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Kata Sandi</label>
                <input type="password" id="password" name="password"
                       class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="remember" id="remember" value="1">
                <label class="form-check-label" for="remember">Ingat saya</label>
            </div>
            <button class="btn btn-primary w-100" type="submit">Masuk</button>
        </form>
    </div>
</div>
</body>
</html>