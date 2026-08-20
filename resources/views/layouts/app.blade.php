<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Koda - Contact Center Dashboard</title>
    
    <!-- Tailwind & Alpine -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite(['resources/js/app.js'])
</head>
<body class="bg-slate-100 flex h-screen overflow-hidden text-slate-800">

    <!-- SIDEBAR GELAP KAS KODA -->
    <aside class="w-64 bg-slate-900 text-slate-300 flex flex-col h-full shadow-xl hidden md:flex justify-between select-none">
        <div>
            <!-- Brand -->
            <div class="h-16 flex flex-col justify-center px-6 bg-slate-950 border-b border-slate-800">
                <span class="text-lg font-bold text-white tracking-wider">SKYKOM - CRM</span>
                <span class="text-[10px] text-slate-400">PT Dankom Mitra Abadi</span>
            </div>

            <!-- Navigasi Menu -->
            <nav class="p-4 space-y-6 overflow-y-auto">
                
                <!-- Dashboard / Overview -->
                <div>
                    <a href="/supervisor/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('supervisor/dashboard*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                        <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                        Live Monitoring
                    </a>
                </div>

                <!-- Contact Center / Supervisor Area -->
                @if(auth()->check() && in_array(auth()->user()->role, ['supervisor', 'admin']))
                <div>
                    <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Contact Center</p>
                    <div class="space-y-1">
                        <!-- Call History -->
                        <a href="/supervisor/call-history" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('supervisor/call-history*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Call History
                        </a>
                    </div>
                </div>
                @endif

                <!-- Admin Area -->
                @if(auth()->check() && auth()->user()->role === 'admin')
                <div>
                    <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Admin Area</p>
                    <div class="space-y-1">
                        <!-- User Management -->
                        <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('admin/users*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            User Management
                        </a>
                        
                        <!-- Agent Management -->
                        <a href="{{ route('admin.agents.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('admin/agents*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                            Agent Management
                        </a>
                    </div>
                </div>
                @endif

            </nav>
        </div>

        <!-- User Profile di Bawah Sidebar (Dibuat Dinamis) -->
        <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-white">{{ auth()->user()->name ?? 'User' }}</p>
                <p class="text-[10px] text-green-400 font-semibold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block animate-pulse"></span> {{ ucfirst(auth()->user()->role ?? 'Online') }}
                </p>
            </div>
            <form action="/logout" method="POST">
                @csrf
                <button type="submit" class="text-xs text-slate-400 hover:text-white transition">Logout</button>
            </form>
        </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 flex flex-col h-screen overflow-hidden">
        <main class="flex-1 overflow-y-auto p-8">
            @yield('content')
        </main>
    </div>

    @yield('scripts')
</body>
</html>