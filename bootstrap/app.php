<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        api: __DIR__.'/../routes/api.php', // <-- TAMBAHKAN BARIS INI AGAR ROUTES/API.PHP DIBACA
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /// 🚀 DAFTARKAN ALIAS DI SINI
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // 419 Page Expired -> jangan tampilkan halaman error. Cukup refresh:
        // sesi masih hidup = redirect balik (token baru); sesi mati = ke login.
        // NOTE: Laravel memetakan TokenMismatchException menjadi HttpException(419)
        // SEBELUM callback render jalan, jadi match via status code.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Sesi kedaluwarsa. Muat ulang halaman lalu coba lagi.',
                ], 419);
            }

            if (auth()->check() || session()->has('supervisor_extension') || session()->has('agent_extension')) {
                return redirect()->to($request->fullUrl())
                    ->with('error', 'Sesi form kedaluwarsa, halaman sudah dimuat ulang. Silakan ulangi.');
            }

            $login = ($request->is('agent*') || $request->is('dashboard/workspace*'))
                ? '/agent/login'
                : '/login';

            return redirect($login)->with('error', 'Sesi berakhir. Silakan login kembali.');
        });
    })->create();
