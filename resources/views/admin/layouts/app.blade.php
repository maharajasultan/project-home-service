<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') · reaple.id</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6fb;
        }

        .sidebar-wrap .offcanvas {
            --bs-offcanvas-bg: #111827;
            --bs-offcanvas-color: #fff;
            --bs-offcanvas-width: 270px;
        }

        @media (min-width: 992px) {
            .sidebar-wrap {
                width: 260px;
                flex-shrink: 0;
                background: #111827;
                color: #fff;
                position: sticky;
                top: 0;
                height: 100vh;
                overflow-y: auto;
                align-self: flex-start;
            }
        }

        .sidebar-wrap .brand {
            font-weight: 700;
            font-size: 1.25rem;
            letter-spacing: .3px;
        }

        .sidebar-wrap .nav-link {
            color: #9ca3af;
            border-radius: .5rem;
            padding: .55rem .85rem;
            display: flex;
            align-items: center;
            gap: .65rem;
        }

        .sidebar-wrap a.nav-link:hover {
            color: #fff;
            background: #1f2937;
        }

        .sidebar-wrap .nav-link.active {
            color: #fff;
            background: #0d6efd;
        }

        .sidebar-wrap .nav-link.disabled {
            color: #4b5563;
            pointer-events: none;
        }

        .nav-section {
            font-size: .7rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            color: #6b7280;
            margin: 1rem .85rem .35rem;
        }

        .content {
            min-width: 0;
        }

        .stat-icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
        }

        .table> :not(caption)>*>* {
            vertical-align: middle;
        }
    </style>
</head>

<body>
    @php
    $menu = [
    ['section' => 'Utama'],
    ['Dashboard', 'bi-speedometer2', 'admin.dashboard', 'admin.dashboard'],
    ['section' => 'Transaksi'],
    ['Transaksi', 'bi-receipt', 'admin.transactions.index', 'admin.transactions.*'],
    ['Klaim Garansi', 'bi-shield-check', 'admin.warranty.index', 'admin.warranty.*', $pendingClaimsCount ?? 0],
    ['section' => 'Pengguna'],
    ['Pelanggan', 'bi-people', 'admin.users.index', 'admin.users.*'],
    ['Teknisi', 'bi-person-gear', 'admin.technicians.index', 'admin.technicians.*'],
    ['section' => 'Katalog'],
    ['Barang & Stok', 'bi-box-seam', 'admin.products.index', 'admin.products.*'],
    ['Banner', 'bi-images', 'admin.banners.index', 'admin.banners.*'],
    ['Ulasan', 'bi-star', 'admin.reviews.index', 'admin.reviews.*'],
    ['Chat', 'bi-chat-dots', 'admin.chat.index', 'admin.chat.*', $unreadChatCount ?? 0],
    ['section' => 'Laporan'],
    ['Laporan Keuangan', 'bi-cash-coin', 'admin.reports.finance', 'admin.reports.finance*'],
    ['Laporan Inventory', 'bi-boxes', 'admin.reports.inventory', 'admin.reports.inventory*'],
    ['Kinerja Teknisi', 'bi-graph-up', 'admin.reports.technicians', 'admin.reports.technicians*'],
    ];
    @endphp

    <div class="d-flex">
        {{-- ===== Sidebar ===== --}}
        <div class="sidebar-wrap">
            <aside class="offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar">
                <div class="offcanvas-header border-bottom border-secondary-subtle">
                    <span class="brand"><i class="bi bi-phone-vibrate text-primary"></i> reaple.id</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebar" aria-label="Tutup"></button>
                </div>
                <div class="offcanvas-body d-flex flex-column p-3 w-100">
                    <div class="brand d-none d-lg-block px-2 pb-2"><i class="bi bi-phone-vibrate text-primary"></i> reaple.id <small class="text-secondary fs-6 fw-normal">admin</small></div>

                    <nav class="nav flex-column">
                        @foreach ($menu as $item)
                        @if (isset($item['section']))
                        <div class="nav-section">{{ $item['section'] }}</div>
                        @else
                        @php
                        [$label, $icon, $routeName, $pattern] = $item;
                        $badgeCount = $item[4] ?? 0;
                        @endphp
                        @if (Route::has($routeName))
                        <a class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}" href="{{ route($routeName) }}">
                            <i class="bi {{ $icon }}"></i>
                            <span class="flex-grow-1">{{ $label }}</span>
                            @if ($badgeCount > 0)
                            <span class="badge rounded-pill text-bg-danger">{{ $badgeCount }}</span>
                            @endif
                        </a>
                        @else
                        <span class="nav-link disabled" title="Segera hadir">
                            <i class="bi {{ $icon }}"></i>
                            <span class="flex-grow-1">{{ $label }}</span>
                            <small>segera</small>
                        </span>
                        @endif
                        @endif
                        @endforeach
                    </nav>
                </div>
            </aside>
        </div>

        {{-- ===== Konten ===== --}}
        <div class="content flex-grow-1">
            <header class="navbar bg-white border-bottom px-3 sticky-top">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-outline-secondary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Buka menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <h1 class="h5 mb-0">@yield('page_title', 'Admin')</h1>
                </div>
                <div class="dropdown">
                    <button class="btn btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> {{ auth()->user()->name }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</span></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right"></i> Keluar</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="p-3 p-lg-4">
                @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
                @endif
                @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
                @endif
                @if ($errors->any() && ! request()->routeIs('admin.users.*', 'admin.technicians.*'))
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>

</html>