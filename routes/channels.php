<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
Broadcast::channel('supervisor.dashboard', function ($user) {
    return true; // Untuk sementara true agar lolos, atau sesuaikan validasi role admin/spv Abang
});

// Pastikan channel workgroup juga ada izinkan jika dipakai
Broadcast::channel('workgroup.{name}', function ($user, $name) {
    return true;
});