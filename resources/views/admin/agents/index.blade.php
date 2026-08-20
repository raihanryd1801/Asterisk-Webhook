@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ openModal: false }">
    
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Agent Management</h1>
            <p class="text-xs text-slate-500 mt-1">Kelola data agen, ekstensi, dan grup antrian.</p>
        </div>
        <button @click="openModal = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-bold text-sm shadow-sm transition">
            + Tambah Agen
        </button>
    </div>

    <!-- Tabel Data Agen -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <table class="w-full text-left">
            <thead class="bg-slate-50 border-b">
                <tr class="text-xs font-bold text-slate-500 uppercase">
                    <th class="p-4">Nama</th>
                    <th class="p-4">Extension</th>
                    <th class="p-4">Group</th>
                    <th class="p-4">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 text-sm">
                @foreach($agents as $agent)
                <tr class="hover:bg-slate-50">
                    <td class="p-4 font-semibold text-slate-800">{{ $agent->name }}</td>
                    <td class="p-4 text-slate-600 font-mono">{{ $agent->extension }}</td>
                    <td class="p-4 text-slate-600">{{ $agent->group->name ?? 'None' }}</td>
                    <td class="p-4 flex gap-2">
                        <button class="text-blue-600 hover:underline text-xs font-bold">Edit</button>
                        <form action="/admin/agents/{{ $agent->id }}" method="POST">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline text-xs font-bold">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection