<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - NOC System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-800 min-h-screen flex items-center justify-center p-4">

    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
        <div class="bg-blue-600 p-6 text-center">
            <h1 class="text-2xl font-bold text-white">System Admin Login</h1>
            <p class="text-blue-200 text-sm mt-1">PT. Dankom Mitra Abadi</p>
        </div>

        <div class="p-8">
            @if(session('error'))
                <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-200 text-center font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            <form action="{{ url('/login') }}" method="POST" class="space-y-5">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Email Administrator</label>
                    <input type="email" name="email" required placeholder="admin@dankom.co.id" 
                           value="{{ old('email') }}"
                           class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" 
                           class="w-full px-4 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-blue-500 outline-none transition">
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition shadow-md">
                    Login ke Dashboard
                </button>
            </form>

            <!-- JEMBATAN -->
            <p class="text-center text-xs text-slate-500 mt-6 pt-4 border-t border-slate-100">
                Supervisor atau Agent? 
                <a href="/agent/login" class="text-blue-600 font-bold hover:underline">Login via Ekstensi di sini</a>
            </p>
        </div>
    </div>

</body>
</html>