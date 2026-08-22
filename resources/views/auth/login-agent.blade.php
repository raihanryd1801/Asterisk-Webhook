<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent/Supervisor Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                    colors: {
                        brand: { 50:'#f0fdfa',100:'#ccfbf1',500:'#14b8a6',600:'#0d9488',700:'#0f766e' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-4 font-sans antialiased">

    <div class="bg-white rounded-3xl shadow-xl w-full max-w-md overflow-hidden border border-slate-200/80">
        <!-- Header Section -->
        <div class="bg-slate-900 p-8 text-center relative overflow-hidden">
            <div class="w-12 h-12 rounded-2xl bg-brand-500/10 border border-brand-500/20 text-brand-500 flex items-center justify-center text-xl mx-auto mb-3 shadow-inner">
                <i class="fa-solid fa-headset"></i>
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight">User & Supervisor Login</h1>
            <p class="text-slate-400 text-xs mt-1">PT. Dankom Mitra Abadi</p>
        </div>

        <!-- Body Section -->
        <div class="p-8">
            @if(session('error'))
                <div class="bg-rose-50 text-rose-600 p-3.5 rounded-xl text-xs mb-6 border border-rose-100 text-center font-semibold flex items-center justify-center gap-2">
                    <i class="fa-solid fa-triangle-exclamation"></i> {{ session('error') }}
                </div>
            @endif

            <form action="{{ url('/agent/login') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Nomor Ekstensi</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-phone text-xs"></i></span>
                        <input type="text" name="extension" required placeholder="Contoh: 101" 
                               value="{{ old('extension') }}"
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition text-sm bg-slate-50/50 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password SIP (Secret)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400"><i class="fa-solid fa-key text-xs"></i></span>
                        <input type="password" name="password" required placeholder="Masukkan password SIP..." 
                               class="w-full pl-10 pr-4 py-3 rounded-xl border border-slate-200 focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 outline-none transition text-sm bg-slate-50/50">
                    </div>
                </div>

                <button type="submit" class="w-full bg-slate-900 hover:bg-slate-800 text-white font-semibold py-3 px-4 rounded-xl transition shadow-sm text-sm mt-2">
                    Masuk ke Workspace
                </button>
            </form>

            <!-- Jembatan Switch Login -->
            <p class="text-center text-xs text-slate-500 mt-6 pt-5 border-t border-slate-100">
                Bukan Agent/Supervisor? 
                <a href="/login" class="text-brand-600 font-bold hover:underline ml-1">Login Admin via Email</a>
            </p>
        </div>
    </div>

</body>
</html>