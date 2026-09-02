document.addEventListener('alpine:init', () => {
    Alpine.data('chatWidget', (initUserId) => ({
        isOpen: false,
        contacts: [],
        activePartner: null,
        messages: [],
        newMessage: '',
        currentUserId: initUserId,
        unreadCount: 0,

        // Drag state
        isDragging: false,
        hasMoved: false,
        posX: null,
        posY: null,
        dragOffsetX: 0,
        dragOffsetY: 0,
        _boundOnDrag: null,
        _boundStopDrag: null,

        init() {
            // Cek unread count sekali di awal, lalu polling terus-menerus
            // TIDAK bergantung pada activePartner atau isOpen, biar badge
            // tetap muncul walau widget masih tertutup / belum pilih kontak.
            this.fetchUnreadCount();

            setInterval(() => {
                this.fetchUnreadCount();

                if (this.activePartner && this.isOpen) {
                    this.fetchMessages(true);
                }
            }, 5000);

            const saved = localStorage.getItem('chatWidgetPos');
            if (saved) {
                try {
                    const { x, y } = JSON.parse(saved);
                    this.posX = x;
                    this.posY = y;
                } catch (e) {
                    // ignore corrupt data
                }
            }

            this._boundOnDrag = this.onDrag.bind(this);
            this._boundStopDrag = this.stopDrag.bind(this);
        },

        get widgetStyle() {
            if (this.posX === null || this.posY === null) return '';
            return `left: ${this.posX}px; top: ${this.posY}px; right: auto; bottom: auto;`;
        },

        fetchUnreadCount() {
            fetch('/dashboard/api/chat/unread-count?my_id=' + this.currentUserId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(async res => {
                if (!res.ok) return null;
                return res.json();
            })
            .then(data => {
                if (data && data.status === 'success') {
                    this.unreadCount = data.unread_count;
                }
            })
            .catch(() => {
                // Diamkan saja kalau gagal, biar gak ganggu UX widget lain
            });
        },

        startDrag(e) {
            this.isDragging = true;
            this.hasMoved = false;

            const point = e.touches ? e.touches[0] : e;
            const rect = this.$refs.chatButton.getBoundingClientRect();

            if (this.posX === null) {
                this.posX = rect.left;
                this.posY = rect.top;
            }

            this.dragOffsetX = point.clientX - rect.left;
            this.dragOffsetY = point.clientY - rect.top;

            document.addEventListener('mousemove', this._boundOnDrag);
            document.addEventListener('touchmove', this._boundOnDrag, { passive: false });
            document.addEventListener('mouseup', this._boundStopDrag);
            document.addEventListener('touchend', this._boundStopDrag);
        },

        onDrag(e) {
            if (!this.isDragging) return;
            e.preventDefault();

            const point = e.touches ? e.touches[0] : e;
            let newX = point.clientX - this.dragOffsetX;
            let newY = point.clientY - this.dragOffsetY;

            const btnSize = this.$refs.chatButton.offsetWidth;
            const maxX = window.innerWidth - btnSize;
            const maxY = window.innerHeight - btnSize;
            newX = Math.max(0, Math.min(newX, maxX));
            newY = Math.max(0, Math.min(newY, maxY));

            this.posX = newX;
            this.posY = newY;
            this.hasMoved = true;
        },

        stopDrag() {
            this.isDragging = false;

            document.removeEventListener('mousemove', this._boundOnDrag);
            document.removeEventListener('touchmove', this._boundOnDrag);
            document.removeEventListener('mouseup', this._boundStopDrag);
            document.removeEventListener('touchend', this._boundStopDrag);

            if (this.posX !== null) {
                localStorage.setItem('chatWidgetPos', JSON.stringify({ x: this.posX, y: this.posY }));
            }
        },

        handleButtonClick() {
            if (!this.hasMoved) {
                this.toggleChat();
            }
        },

        toggleChat() {
            this.isOpen = !this.isOpen;
            if (this.isOpen) {
                this.fetchContacts();
                if (this.activePartner) {
                    this.fetchMessages();
                }
                // unreadCount TIDAK di-nol-in manual di sini lagi.
                // Backend yang nentuin (is_read di-update pas fetchMessages
                // jalan buat partner yang lagi dibuka), lalu fetchUnreadCount()
                // dari polling bakal otomatis sinkron ke angka yang bener.
            }
        },

        fetchContacts() {
            fetch('/dashboard/api/chat/contacts?my_id=' + this.currentUserId, {
                headers: { 'Accept': 'application/json' }
            })
            .then(async res => {
                if (!res.ok) throw new Error('Gagal memuat kontak.');
                return res.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    this.contacts = data.contacts.filter(c => c.id != this.currentUserId);
                }
            })
            .catch(() => {
                this.contacts = [];
            });
        },

        selectContact(contact) {
            this.activePartner = contact;
            this.fetchMessages();
        },

        fetchMessages(silent = false) {
            if (!this.activePartner) return;

            fetch(`/dashboard/api/chat/messages/${this.activePartner.id}?my_id=${this.currentUserId}`, {
                headers: { 'Accept': 'application/json' }
            })
            .then(async res => {
                if (!res.ok) return [];
                return res.json();
            })
            .then(messages => {
                if (Array.isArray(messages)) {
                    let isNew = messages.length > this.messages.length;

                    this.messages = messages;

                    if (isNew || !silent) {
                        this.$nextTick(() => {
                            let container = this.$refs.messageContainer;
                            if (container) container.scrollTop = container.scrollHeight;
                        });
                    }

                    // Setelah fetch (backend otomatis mark is_read=1 untuk
                    // pesan dari partner ini), sinkronkan ulang badge global.
                    this.fetchUnreadCount();
                }
            })
            .catch(err => console.error('Error fetch messages:', err));
        },

        sendMessage() {
            if (!this.newMessage || !this.newMessage.trim() || !this.activePartner) return;

            let textToSend = this.newMessage;
            this.newMessage = '';

            fetch('/dashboard/api/chat/send', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    receiver_id: this.activePartner.id,
                    message: textToSend,
                    sender_id: this.currentUserId
                })
            })
            .then(async res => {
                if (!res.ok) {
                    let err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Gagal mengirim pesan');
                }
                return res.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    this.messages.push(data.message);
                    this.$nextTick(() => {
                        let container = this.$refs.messageContainer;
                        if (container) container.scrollTop = container.scrollHeight;
                    });
                }
            })
            .catch(err => {
                console.error('Error send message:', err);
                alert(err.message);
            });
        },

        formatTime(timestamp) {
            if (!timestamp) return '';
            let formattedString = timestamp.replace(' ', 'T');
            let date = new Date(formattedString);
            if (isNaN(date.getTime())) return '';
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }));
});