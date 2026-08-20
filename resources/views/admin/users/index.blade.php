@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    
    <!-- Header Title -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">User Management</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola data pengguna sistem, hak akses, dan level akun (Admin, Supervisor, Agent).</p>
        </div>
    </div>

    <!-- Tabel Daftar User -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-bold text-slate-500 uppercase tracking-wider">
                    <th class="p-4">Nama</th>
                    <th class="p-4">Email</th>
                    <th class="p-4">Role / Hak Akses</th>
                    <th class="p-4">Status Akun</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm">
                @foreach($users as $user)
                <tr class="hover:bg-slate-50 transition">
                    <td class="p-4 font-semibold text-slate-800">{{ $user->name }}</td>
                    <td class="p-4 text-slate-600">{{ $user->email }}</td>
                    <td class="p-4">
                        <!-- Badge Warna Berdasarkan Role -->
                        <span class="px-2.5 py-1 text-xs font-bold rounded-full uppercase
                            @if($user->role === 'admin') bg-purple-100 text-purple-700
                            @elseif($user->role === 'supervisor') bg-blue-100 text-blue-700
                            @else bg-emerald-100 text-emerald-700 @endif">
                            {{ $user->role ?? 'agent' }}
                        </span>
                    </td>
                    <td class="p-4">
                        <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2 py-1 rounded">Aktif</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>
@endsection