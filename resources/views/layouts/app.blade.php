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
                <span class="text-lg font-bold text-white tracking-wider">Koda</span>
                <span class="text-[10px] text-slate-400">PT Dankom Mitra Abadi</span>
            </div>

            <!-- Navigasi Menu -->
            <nav class="p-4 space-y-6 overflow-y-auto">
    <div>
        <a href="/workspace" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('workspace*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
            Overview
        </a>
    </div>

    <div>
        <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Contact Center</p>
        <div class="space-y-1">
            <!-- Menu Live Agents -->
            <a href="/supervisor/dashboard" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('supervisor/dashboard') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 100-6 3 3 0 000 6z"></path></svg>
                Live Agents
            </a>
            
            <!-- Menu Call History -->
            <a href="/supervisor/call-history" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('supervisor/call-history') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Call History
            </a>
        </div>
    </div>

    <div>
        <p class="px-3 text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-2">Organization</p>
        <div class="space-y-1">
            <a href="/supervisor/provisioning" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm transition {{ request()->is('supervisor/provisioning*') ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'hover:bg-slate-800 text-slate-300' }}">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path></svg>
                Provisioning Agent
            </a>
        </div>
    </div>
</nav>

        <!-- User Profile di Bawah Sidebar -->
        <div class="p-4 bg-slate-950 border-t border-slate-800 flex items-center justify-between">
            <div>
                <p class="text-xs font-bold text-white">Admin Dankom</p>
                <p class="text-[10px] text-green-400 font-semibold flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-green-500 inline-block animate-pulse"></span> Ready
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