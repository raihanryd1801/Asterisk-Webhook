<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OmniDial - Contact Center Dashboard</title>
    
    <!-- Tailwind, Alpine, FontAwesome -->
    <script>window.FontAwesomeConfig = { autoReplaceSvg: 'nest' };</script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/js/app.js'])

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdfa',100:'#ccfbf1',500:'#14b8a6',600:'#0d9488',700:'#0f766e' },
                        slate: { 50:'#f8fafc',100:'#f1f5f9',200:'#e2e8f0',300:'#cbd5e1',400:'#94a3b8',500:'#64748b',600:'#475569',700:'#334155',800:'#1e293b',900:'#0f172a' }
                    }
                }
            }
        }
    </script>
    <style>
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        [x-cloak] { display: none !important; }
    </style>
    @yield('styles')
    
</head>
<body class="bg-[#f1f5f9] flex h-screen overflow-hidden text-slate-800 font-sans antialiased">
    
    @php
        $isAgent = session()->has('agent_extension');
    @endphp

    <!-- SIDEBAR -->
    <aside class="w-[260px] bg-slate-900 text-white flex flex-col shrink-0 hidden md:flex relative z-20 transition-all duration-300 shadow-xl">
        <div class="h-[72px] flex items-center px-6 shrink-0 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-brand-500 flex items-center justify-center text-white"><i class="fa-solid fa-headset text-base"></i></div>
                <div class="flex flex-col">
                    <span class="text-lg font-bold text-white tracking-wider leading-tight">SKYKOM</span>
                    <span class="text-[10px] text-slate-400">PT Dankom Mitra Abadi</span>
                </div>
            </div>
        </div>

        <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
            @if($isAgent)
                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-2">Contact Center</p>
                <a href="/agent/overview" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('agent/overview*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center"></i> Overview
                </a>
                <a href="/agent/{{ session('agent_extension') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('agent/'.session('agent_extension')) ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-border-all w-5 text-center"></i> Workspace
                </a>
            @else
                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-2">Main Menu</p>
                <a href="/agent/overview" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('agent/overview*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center"></i> Overview
                </a>

                @if(auth()->check() && auth()->user()->role === 'admin')
                    <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-6">Admin Management</p>
                    <a href="{{ route('admin.agents.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('admin/agents*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                        <i class="fa-solid fa-headset w-5 text-center"></i> Agent Management
                    </a>
                    <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('admin/users*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                        <i class="fa-solid fa-users w-5 text-center"></i> User Management
                    </a>
                @endif

                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-6">Monitoring & Reports</p>
                <a href="/supervisor/dashboard" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('supervisor/dashboard*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i> Live Monitoring
                </a>
                <a href="/supervisor/call-history" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->is('supervisor/call-history*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i> Call History
                </a>
            @endif
        </nav>

        <div class="p-3 border-t border-slate-800 shrink-0">
            <div class="flex items-center gap-3 p-2 rounded-lg relative group">
                <img src="https://storage.googleapis.com/uxpilot-auth.appspot.com/avatars/avatar-2.jpg" alt="User" class="w-9 h-9 rounded-full object-cover">
                <div class="flex-1 min-w-0">
                    @if($isAgent)
                        <p class="text-sm font-medium truncate">Ext: {{ session('agent_extension') }}</p>
                        <p class="text-xs flex items-center gap-1 text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Ready
                        </p>
                    @else
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name ?? 'Administrator' }}</p>
                        <p class="text-xs flex items-center gap-1 text-emerald-400">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> {{ ucfirst(auth()->user()->role ?? 'Online') }}
                        </p>
                    @endif
                </div>
                <form action="{{ $isAgent ? url('/agent/logout') : (session()->has('supervisor_extension') ? route('supervisor.logout') : route('logout')) }}" method="POST">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-red-400 transition-colors" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative z-10">
        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            @yield('content')
        </div>
    </main>

    @yield('scripts')
</body>
</html>
