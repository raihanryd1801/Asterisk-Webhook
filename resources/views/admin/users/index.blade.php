@extends('layouts.app')

@section('content')
<div class="space-y-6">
    
    <!-- Header Section -->
    <div class="bg-brand-50 border border-brand-100 rounded-2xl p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 flex items-center gap-2">
                <i class="fa-solid fa-user-shield text-brand-600"></i> User Management
            </h1>
            <p class="text-sm text-slate-500 mt-0.5">Kelola data pengguna sistem, hak akses, dan level akun (Admin, Supervisor, Agent).</p>
        </div>
    </div>

    <!-- Tabel Daftar User -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm whitespace-nowrap">
                <thead class="bg-slate-50/50 border-b border-slate-100">
                    <tr class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
                        <th class="px-6 py-4">Profil Pengguna</th>
                        <th class="px-6 py-4">Role / Hak Akses</th>
                        <th class="px-6 py-4">Status Akun</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($users as $user)
                    <tr class="hover:bg-slate-50/70 transition-colors">
                        
                        <!-- Kolom Nama & Email (Digabung jadi lebih modern) -->
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <!-- Avatar Inisial Otomatis -->
                                <div class="w-10 h-10 rounded-full bg-brand-50 text-brand-600 flex items-center justify-center font-bold text-sm shadow-sm border border-brand-100 shrink-0">
                                    {{ strtoupper(substr($user->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="font-bold text-slate-800">{{ $user->name }}</div>
                                    <div class="text-xs text-slate-500 mt-0.5">{{ $user->email }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Kolom Role dengan Ikon dan Warna Berbeda -->
                        <td class="px-6 py-4">
                            @if($user->role === 'admin')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-purple-50 text-purple-600 border-purple-200 shadow-sm">
                                    <i class="fa-solid fa-shield-halved text-[11px]"></i> Admin
                                </span>
                            @elseif($user->role === 'supervisor')
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-blue-50 text-blue-600 border-blue-200 shadow-sm">
                                    <i class="fa-solid fa-user-tie text-[11px]"></i> Supervisor
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-emerald-50 text-emerald-600 border-emerald-200 shadow-sm">
                                    <i class="fa-solid fa-headset text-[11px]"></i> Agent
                                </span>
                            @endif
                        </td>

                        <!-- Kolom Status Akun -->
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[10px] font-bold rounded-full border uppercase bg-emerald-50 text-emerald-600 border-emerald-100">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span> Aktif
                            </span>
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        
        <!-- Footer Tabel (Opsional jika ingin memberi info jumlah data) -->
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500 font-medium">
            <span>Total Pengguna: <strong class="text-slate-700">{{ count($users) }}</strong> data.</span>
        </div>
    </div>

</div>
@endsection