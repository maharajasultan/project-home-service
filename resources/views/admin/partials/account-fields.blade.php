@php
    use App\Support\MediaUrl;
    $isEdit = $account !== null;
    $avatar = $isEdit ? MediaUrl::resolve($account->avatar) : null;
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label for="name" class="form-label">Nama Lengkap</label>
        <input type="text" id="name" name="name" value="{{ old('name', $account->name ?? '') }}"
               class="form-control @error('name') is-invalid @enderror" maxlength="100" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" id="email" name="email" value="{{ old('email', $account->email ?? '') }}"
               class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="phone" class="form-label">Nomor HP {{ $phoneRequired ? '' : '(opsional)' }}</label>
        <input type="text" id="phone" name="phone" value="{{ old('phone', $account->phone ?? '') }}"
               class="form-control @error('phone') is-invalid @enderror" placeholder="081234567890" {{ $phoneRequired ? 'required' : '' }}>
        @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-6">
        <label for="avatar" class="form-label">Foto Profil (opsional)</label>
        <input type="file" id="avatar" name="avatar" accept=".jpg,.jpeg,.png,.webp"
               class="form-control @error('avatar') is-invalid @enderror">
        @error('avatar')<div class="invalid-feedback">{{ $message }}</div>@enderror
        @if ($avatar)<img src="{{ $avatar }}" alt="Foto saat ini" class="rounded mt-2" style="width:64px;height:64px;object-fit:cover">@endif
    </div>
    <div class="col-md-6">
        <label for="password" class="form-label">Kata Sandi {{ $isEdit ? '(kosongkan jika tidak diubah)' : '' }}</label>
        <input type="password" id="password" name="password" autocomplete="new-password"
               class="form-control @error('password') is-invalid @enderror" {{ $isEdit ? '' : 'required' }}>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
        <div class="form-text">Minimal 8 karakter, mengandung huruf dan angka.</div>
    </div>
    <div class="col-md-6">
        <label for="password_confirmation" class="form-label">Konfirmasi Kata Sandi</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
    </div>
    <div class="col-12">
        <input type="hidden" name="is_active" value="0">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="is_active" name="is_active" value="1"
                   {{ old('is_active', $account->is_active ?? true) ? 'checked' : '' }}>
            <label class="form-check-label" for="is_active">Akun aktif (bisa login di aplikasi)</label>
        </div>
    </div>
</div>