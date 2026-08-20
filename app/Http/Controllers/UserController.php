<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        // Ambil semua data user untuk ditampilkan ke admin
        $users = User::all();

        // Nanti Abang bisa buat view-nya di resources/views/admin/users/index.blade.php
        return view('admin.users.index', compact('users'));
    }
}