<!DOCTYPE html>
<html lang="en">
<head>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>OmniDial - Contact Center Dashboard</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    

    <script type="module" src="https://cdn.jsdelivr.net/npm/@hotwired/turbo@8.0.4/dist/turbo.es2017-esm.js"></script>

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
        .turbo-progress-bar { height: 4px; background-color: #14b8a6; }
    </style>
    @yield('styles')
</head>
<body class="bg-[#f1f5f9] flex h-screen overflow-hidden text-slate-800 font-sans antialiased">
    
    @php
        // 🚀 SMART DETECTION LOGIC (Memisahkan murni antara tabel users & agents)
        $userType = 'guest';
        $myChatUserId = 99999; 
        $profileName = 'Unknown';
        $profileRole = 'Unknown';
        $profileExt = '';

        // Deteksi Session dari tabel agents (Berlaku untuk Agent & SPV)
        $ext = session('agent_extension') ?? session('supervisor_extension') ?? session('extension');
        
        if ($ext) {
            $agentData = \App\Models\Agent::where('extension', $ext)->first();
            if ($agentData) {
                $userType = $agentData->role; // Akan terdeteksi sbg 'agent' atau 'supervisor'
                $myChatUserId = $agentData->id;
                $profileName = $agentData->name;
                $profileRole = ucfirst($agentData->role);
                $profileExt = $agentData->extension;
            }
        } 
        // Deteksi Auth dari tabel users (Khusus Administrator Utama)
        elseif (auth()->check()) {
            $userType = 'admin';
            $myChatUserId = 99999; // ID dummy agar Admin tidak masuk ke sistem chat Agent-SPV
            $profileName = auth()->user()->name ?? 'Administrator';
            $profileRole = 'Administrator';
        }
    @endphp

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
            @if($userType === 'agent')
                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-2">Contact Center</p>
                <a href="{{ route('dashboard.overview', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.overview') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center"></i> Overview
                </a>
                <a href="{{ route('dashboard.workspace', $profileExt, false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.workspace') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
    <i class="fa-solid fa-border-all w-5 text-center"></i> Workspace
</a>
            @else
                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-2">Main Menu</p>
                <a href="{{ route('dashboard.overview', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.overview') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-layer-group w-5 text-center"></i> Overview
                </a>

                @if($userType === 'admin')
                    <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-6">Admin Management</p>
                    <a href="{{ route('dashboard.agents.index', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.agents.*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                        <i class="fa-solid fa-headset w-5 text-center"></i> Agent Management
                    </a>
                    <a href="{{ route('dashboard.users.index', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.users.*') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                        <i class="fa-solid fa-users w-5 text-center"></i> User Management
                    </a>
                @endif

                <p class="px-3 text-[11px] font-semibold text-slate-500 uppercase tracking-wider mb-3 mt-6">Monitoring & Reports</p>
                <a href="{{ route('dashboard.live-monitoring', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.live-monitoring') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-chart-pie w-5 text-center"></i> Live Monitoring
                </a>
                <a href="{{ route('dashboard.call-history', [], false) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all duration-200 {{ request()->routeIs('dashboard.call-history') ? 'bg-slate-800/50 text-brand-500 border-l-2 border-brand-500 shadow-sm' : 'text-slate-300 hover:bg-slate-800 hover:text-white border-transparent border-l-2' }}">
                    <i class="fa-solid fa-clock-rotate-left w-5 text-center"></i> Call History
                </a>
            @endif
        </nav>

        <div class="p-3 border-t border-slate-800 shrink-0">
            <div class="flex items-center gap-3 p-2 rounded-lg relative group">
                <div class="w-9 h-9 rounded-full bg-brand-500 flex items-center justify-center text-white font-semibold text-sm shrink-0">
                    {{ strtoupper(substr($profileName, 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate">{{ $profileName }}</p>
                    <p class="text-xs flex items-center gap-1 text-emerald-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> 
                        {{ $profileRole }} {!! $profileExt ? "(Ext: $profileExt)" : "" !!}
                    </p>
                </div>
                <form action="{{ $userType === 'admin' ? url('/logout') : url('/agent/logout') }}" method="POST" data-turbo="false">
                    @csrf
                    <button type="submit" class="text-slate-500 hover:text-red-400 transition-colors" title="Logout"><i class="fa-solid fa-right-from-bracket"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden relative z-10">
        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            @yield('content')
        </div>
    </main>

    {{-- Echo, Pusher, Alpine turbo-cache cleanup, dan chatWidget component sekarang
         di-bundle lewat @vite(['resources/js/app.js']) di <head> —
         lihat resources/js/echo.js dan resources/js/chat-widget.js --}}

    @if($userType !== 'admin')
    <div class="fixed bottom-6 right-6 z-50" x-data="chatWidget({{ $myChatUserId }})" x-bind:style="widgetStyle">
    <button 
        x-ref="chatButton"
        @mousedown="startDrag($event)"
        @touchstart="startDrag($event)"
        @click="handleButtonClick()"
        class="bg-brand-600 hover:bg-brand-700 text-white w-14 h-14 rounded-full shadow-xl flex items-center justify-center transition-all transform hover:scale-105 relative group cursor-grab active:cursor-grabbing select-none">
        <i class="fa-solid fa-comments text-xl pointer-events-none"></i>
        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center border-2 border-white shadow-sm" x-show="unreadCount > 0" x-text="unreadCount" x-cloak></span>
    </button>

    
         ...
         {{-- panel chat tetap sama persis seperti sebelumnya --}}

        <div x-show="isOpen" 
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0 scale-100"
             x-transition:leave-end="opacity-0 translate-y-4 scale-95"
             class="absolute bottom-16 right-0 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200 overflow-hidden flex flex-col h-[480px]" 
             @click.outside="isOpen = false" 
             style="display: none;" x-cloak>
            
            <div class="bg-slate-900 text-white p-4 flex items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-full bg-brand-500/20 border border-brand-400/30 flex items-center justify-center text-brand-400">
                        <i class="fa-solid fa-headset text-xs"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-xs" x-text="activePartner ? activePartner.name : 'Pusat Bimbingan Tim'"></h3>
                        <p class="text-[10px] text-slate-400 font-mono" x-text="activePartner ? 'Ext: ' + activePartner.extension : 'Pilih kontak untuk mulai chat'"></p>
                    </div>
                </div>
                <button @click="isOpen = false" class="text-slate-400 hover:text-white transition">
                    <i class="fa-solid fa-xmark text-sm"></i>
                </button>
            </div>

            <div class="flex flex-1 overflow-hidden">
                <div class="w-1/3 border-r border-slate-100 overflow-y-auto bg-slate-50 p-2 space-y-1">
                    <div class="text-[10px] font-bold text-slate-400 uppercase px-2 py-1">Kontak Tim</div>
                    <template x-for="contact in contacts" :key="contact.id">
                        <button @click="selectContact(contact)" 
                                class="w-full text-left p-2 rounded-xl transition text-xs flex flex-col gap-0.5"
                                :class="activePartner && activePartner.id === contact.id ? 'bg-brand-50 border border-brand-200 text-brand-900 font-semibold shadow-xs' : 'hover:bg-slate-200/50 text-slate-700'">
                            <span class="truncate font-bold" x-text="contact.name"></span>
                            <span class="text-[9px] text-slate-400 font-mono" x-text="'Ext: ' + contact.extension"></span>
                        </button>
                    </template>
                    <div x-show="contacts.length === 0" class="text-center py-6 text-[11px] text-slate-400 px-2">
                        Belum ada kontak.
                    </div>
                </div>

                <div class="w-2/3 flex flex-col bg-white">
                    <template x-if="!activePartner">
                        <div class="flex-1 flex flex-col items-center justify-center p-6 text-center text-slate-400">
                            <i class="fa-regular fa-comments text-2xl mb-2 text-slate-300"></i>
                            <p class="text-[11px] font-medium">Klik nama kontak di sebelah kiri untuk mulai mengirim pesan.</p>
                        </div>
                    </template>

                    <template x-if="activePartner">
                        <div class="flex flex-col h-full">
                            <div class="flex-1 p-3 overflow-y-auto space-y-2.5 text-xs" x-ref="messageContainer">
                                <template x-for="msg in messages" :key="msg.id">
                                    <div class="flex flex-col" :class="msg.sender_id == currentUserId ? 'items-end' : 'items-start'">
                                        
                                        <span class="text-[10px] text-slate-400 mb-1 px-1 font-medium">
                                            <span x-text="msg.sender_id == currentUserId ? 'Anda' : (activePartner.name + ' (Ext: ' + activePartner.extension + ')')"></span>
                                        </span>

                                        <div class="p-2.5 rounded-2xl max-w-[85%] text-xs shadow-xs"
                                             :class="msg.sender_id == currentUserId ? 'bg-brand-600 text-white rounded-br-none' : 'bg-slate-100 text-slate-800 border border-slate-200 rounded-bl-none'">
                                            <p class="break-words" x-text="msg.message"></p>
                                        </div>
                                 
                                        <span class="text-[9px] text-slate-400 mt-0.5 px-1 font-mono" x-text="formatTime(msg.created_at)"></span>
                                    </div>
                                </template>
                            </div>

                            <form @submit.prevent="sendMessage()" class="p-2.5 border-t border-slate-100 flex gap-1.5 bg-slate-50 items-center">
                                <input type="text" x-model="newMessage" placeholder="Tulis pesan..." class="flex-1 border border-slate-200 px-3 py-2 rounded-xl text-xs outline-none focus:ring-2 focus:ring-brand-500 bg-white">
                                
                                <button type="submit" class="bg-brand-600 hover:bg-brand-700 text-white w-9 h-9 rounded-xl text-xs transition flex items-center justify-center shadow-xs shrink-0 cursor-pointer">
                                    <i class="fa-solid fa-paper-plane text-[10px] pointer-events-none"></i>
                                </button>
                            </form>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
    @endif

    @yield('scripts')
</body>
</html>