<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Portal - NOC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-emerald-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">Agent Workspace Login</h1>
            <p class="text-emerald-100 text-sm mt-1">PT. Dankom Mitra Abadi</p>
        </div>

        <div class="p-8">
            <!-- Menampilkan Error jika login gagal -->
            @if(session('error'))
                <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-200 text-center font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ url('/agent/login') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nomor Ekstensi</label>
                    <input type="text" name="extension" required placeholder="Contoh: 101" 
                           value="{{ old('extension') }}"
                           class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Password SIP (Secret)</label>
                    <input type="password" name="password" required placeholder="Masukkan password SIP..." 
                           class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition">
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 shadow-md">
                    Masuk ke Workspace Agent
                </button>
            </form>
        </div>
    </div>

</body>
</html>