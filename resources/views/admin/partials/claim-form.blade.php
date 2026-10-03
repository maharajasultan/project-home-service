<form method="POST" action="{{ route('admin.warranty.update', $claim->id) }}" class="border rounded p-3 mt-3 bg-light">
    @csrf
    @method('PATCH')
    <div class="mb-2">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="a{{ $claim->id }}" value="approved" required>
            <label class="form-check-label" for="a{{ $claim->id }}">Setujui</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="status" id="r{{ $claim->id }}" value="rejected">
            <label class="form-check-label" for="r{{ $claim->id }}">Tolak</label>
        </div>
    </div>
    <textarea name="admin_note" rows="2" maxlength="500" class="form-control mb-2"
              placeholder="Catatan untuk pelanggan (wajib jika ditolak, contoh: jadwal perbaikan ulang)"></textarea>
    <button class="btn btn-primary btn-sm" type="submit">Simpan Keputusan</button>
</form>