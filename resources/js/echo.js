import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,

    // 🚀 Ubah baris ini: Hapus import.meta.env agar murni mengikuti URL address bar
    wsHost: window.location.hostname,

    wsPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT || 8080),

    forceTLS: false,

    enabledTransports: ['ws', 'wss'],
});
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('[x-data]').forEach((el) => {
        if (window.Alpine) { window.Alpine.destroyTree(el); }
    });
});