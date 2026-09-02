//

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import './echo';

import './chat-widget.js';

import JsSIP from 'jssip';

window.JsSIP = JsSIP;

window.formatNumber = function(value) {
    // Logika format angka Abang di sini
    return new Intl.NumberFormat('id-ID').format(value);
};
