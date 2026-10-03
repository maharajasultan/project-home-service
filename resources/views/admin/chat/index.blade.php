@extends('admin.layouts.app')

@section('title', 'Chat')
@section('page_title', 'Chat Pelanggan')

@section('content')
<div class="row g-3">
    {{-- ===== Daftar percakapan ===== --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body border-bottom">
                <form method="GET" class="d-flex gap-2">
                    @if ($active)<input type="hidden" name="user" value="{{ $active->id }}">@endif
                    <input type="text" name="q" value="{{ $term }}" class="form-control" placeholder="Cari pelanggan (mulai chat baru)">
                    <button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button>
                </form>
                @if ($term !== '')
                    <a href="{{ route('admin.chat.index', array_filter(['user' => $active?->id])) }}" class="small">Hapus pencarian</a>
                @endif
            </div>
            <div class="list-group list-group-flush" style="max-height: 68vh; overflow-y: auto;">
                @forelse ($conversations as $c)
                    @php $isActive = $active && $active->id === $c['user']->id; @endphp
                    <a href="{{ route('admin.chat.index', array_filter(['user' => $c['user']->id, 'q' => $term])) }}"
                       class="list-group-item list-group-item-action {{ $isActive ? 'active' : '' }}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div class="fw-semibold text-truncate">{{ $c['user']->name }}</div>
                            @if ($c['unread'] > 0)<span class="badge rounded-pill text-bg-danger">{{ $c['unread'] }}</span>@endif
                        </div>
                        @if ($c['last'])
                            <div class="small text-truncate {{ $isActive ? 'text-white-50' : 'text-secondary' }}">{{ $c['last'] }}</div>
                            <div class="small {{ $isActive ? 'text-white-50' : 'text-secondary' }}">{{ $c['last_at']?->format('d/m H:i') }}</div>
                        @else
                            <div class="small {{ $isActive ? 'text-white-50' : 'text-secondary' }}">{{ $c['user']->email }}</div>
                        @endif
                    </a>
                @empty
                    <div class="list-group-item text-center text-secondary py-5">
                        {{ $term !== '' ? 'Pelanggan tidak ditemukan.' : 'Belum ada pesan dari pelanggan.' }}
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ===== Jendela chat ===== --}}
    <div class="col-lg-8">
        @if ($active)
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $active->name }}</div>
                        <div class="small text-secondary">{{ $active->email }} · {{ $active->phone ?? '-' }}</div>
                    </div>
                    @if (! $active->is_active)<span class="badge text-bg-secondary">Nonaktif</span>@endif
                </div>
                <div class="text-center bg-light">
                    <button type="button" id="olderBtn" class="btn btn-link btn-sm d-none">Muat pesan sebelumnya</button>
                </div>
                <div id="msgBox" class="p-3 bg-light" style="height: 52vh; overflow-y: auto;"></div>
                <div class="card-footer bg-white">
                    <form id="sendForm" class="d-flex gap-2" autocomplete="off">
                        <textarea id="msgInput" rows="1" maxlength="1000" class="form-control" placeholder="Tulis balasan... (Enter kirim, Shift+Enter baris baru)"></textarea>
                        <button id="sendBtn" class="btn btn-primary" type="submit"><i class="bi bi-send"></i></button>
                    </form>
                </div>
            </div>
        @else
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center text-secondary py-5">
                    <i class="bi bi-chat-dots fs-1"></i>
                    <div class="mt-2">Pilih percakapan di sebelah kiri, atau cari pelanggan untuk memulai chat baru.</div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@if ($active)
@push('scripts')
<script>
(function () {
    const cfg = {{ Illuminate\Support\Js::from(['url' => route('admin.chat.messages', $active->id), 'send' => route('admin.chat.send', $active->id)]) }};
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const box = document.getElementById('msgBox');
    const olderBtn = document.getElementById('olderBtn');
    const form = document.getElementById('sendForm');
    const input = document.getElementById('msgInput');
    const sendBtn = document.getElementById('sendBtn');
    let firstId = null, lastId = 0, busy = false;

    function bubble(m) {
        const wrap = document.createElement('div');
        wrap.className = 'd-flex mb-2 ' + (m.is_mine ? 'justify-content-end' : 'justify-content-start');
        wrap.dataset.id = m.id;
        const b = document.createElement('div');
        b.className = 'px-3 py-2 rounded-3 ' + (m.is_mine ? 'bg-primary text-white' : 'bg-white border');
        b.style.maxWidth = '75%';
        b.style.whiteSpace = 'pre-wrap';
        b.style.wordBreak = 'break-word';
        const t = document.createElement('div');
        t.textContent = m.message;              // textContent: aman dari XSS
        const s = document.createElement('div');
        s.className = 'small ' + (m.is_mine ? 'text-white-50' : 'text-secondary');
        s.textContent = m.time;
        b.append(t, s);
        wrap.append(b);
        return wrap;
    }

    const nearBottom = () => box.scrollHeight - box.scrollTop - box.clientHeight < 80;

    async function api(url, opts = {}) {
        const res = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            ...opts,
        });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Terjadi kesalahan.');
        return json;
    }

    async function poll(initial = false) {
        if (busy) return;
        busy = true;
        try {
            const json = await api(cfg.url + (lastId ? '?after_id=' + lastId : ''));
            if (json.data.length) {
                const stick = initial || nearBottom();
                json.data.forEach((m) => {
                    if (!box.querySelector('[data-id="' + m.id + '"]')) box.append(bubble(m));
                    lastId = Math.max(lastId, m.id);
                    if (firstId === null || m.id < firstId) firstId = m.id;
                });
                if (stick) box.scrollTop = box.scrollHeight;
            }
            if (initial) olderBtn.classList.toggle('d-none', !json.meta.has_more);
        } catch (e) { /* koneksi sesaat putus: coba lagi di polling berikutnya */ }
        finally { busy = false; }
    }

    olderBtn.addEventListener('click', async () => {
        if (firstId === null) return;
        try {
            const json = await api(cfg.url + '?before_id=' + firstId);
            const prevHeight = box.scrollHeight;
            [...json.data].reverse().forEach((m) => { box.prepend(bubble(m)); firstId = Math.min(firstId, m.id); });
            box.scrollTop = box.scrollHeight - prevHeight;
            olderBtn.classList.toggle('d-none', !json.meta.has_more);
        } catch (e) { alert(e.message); }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = input.value.trim();
        if (!text) return;
        sendBtn.disabled = true;
        try {
            const json = await api(cfg.send, { method: 'POST', body: JSON.stringify({ message: text }) });
            input.value = '';
            if (!box.querySelector('[data-id="' + json.data.id + '"]')) box.append(bubble(json.data));
            lastId = Math.max(lastId, json.data.id);
            if (firstId === null) firstId = json.data.id;
            box.scrollTop = box.scrollHeight;
        } catch (err) { alert(err.message); }
        finally { sendBtn.disabled = false; input.focus(); }
    });

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
    });

    poll(true);
    setInterval(() => { if (!document.hidden) poll(); }, 4000);
})();
</script>
@endpush
@endif