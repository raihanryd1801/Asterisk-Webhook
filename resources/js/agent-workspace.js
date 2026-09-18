
    document.addEventListener('alpine:init', () => {
        Alpine.data('agentWorkspaceData', (extension) => ({
            extension: extension,
            currentStatus: 'offline',
            targetNumber: '',
            infoMessage: 'MikroSIP siap digunakan...',
            activeCall: null,
            wasCalling: false,
            logs: [],
            pagination: { current_page: 1, last_page: 1, total: 0 },
            filters: { search: '' },
            statusInterval: null, 
            isLoading: true,
            // 🚀 CUSTOMER ASSIGNED
            assignedCustomers: [],
            isLoadingCustomers: true,
            // 🚀 BUAT PTP
            showPtpModal: false,
            ptpCustomer: null,
            ptpForm: { amount: '', date: '', note: '' },
            ptpSaving: false,
            ptpMinDate: new Date().toISOString().split('T')[0],
            // 🚀 PDS ROTATION
            rotationJoined: false,
            rotationLoading: false,
            showRotationModal: false,

            init() {
                this.fetchAgentStatus();
                this.statusInterval = setInterval(() => { this.fetchAgentStatus(); }, 5000);
                this.fetchLogs(1);
                this.fetchAssignedCustomers();
                this.fetchRotationStatus();
            },

            destroy() {
                if (this.statusInterval) {
                    clearInterval(this.statusInterval);
                }
            },

            fetchAgentStatus() {
                fetch('/dashboard/api/live-agents', { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(data => {
                    let agentList = Array.isArray(data) ? data : (data.agents || []);
                    let currentAgent = agentList.find(a => String(a.extension) === String(this.extension));
                    if (currentAgent) {
                        this.currentStatus = currentAgent.status;
                        // 🚀 State call live dari ami:listen (sumber yang sama dengan live monitoring)
                        this.activeCall = currentAgent.is_calling
                            ? { status: currentAgent.call_status, destination: currentAgent.current_destination }
                            : null;
                        this.refreshInfoFromCall();
                    }
                }).catch(err => console.error("Gagal sinkronisasi status"));
            },

            // Samakan tulisan status web dengan live monitoring (ringing -> connected)
            refreshInfoFromCall() {
                if (this.activeCall) {
                    this.wasCalling = true;
                    if (this.activeCall.status === 'connected') {
                        this.infoMessage = `Terhubung dengan ${this.activeCall.destination || ''} — bicara via MicroSIP.`;
                    } else if (this.activeCall.status === 'ringing') {
                        this.infoMessage = `Berdering... menghubungi ${this.activeCall.destination || ''}.`;
                    } else if (this.activeCall.status) {
                        this.infoMessage = `Status panggilan: ${this.activeCall.status} ${this.activeCall.destination || ''}`.trim();
                    }
                } else if (this.wasCalling) {
                    // Call baru saja selesai -> kembalikan ke default
                    this.wasCalling = false;
                    this.infoMessage = 'MikroSIP siap digunakan...';
                }
            },

            updateStatus(newStatus) {
                fetch(`/dashboard/api/agent/${this.extension}/status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.currentStatus = newStatus;
                        this.infoMessage = `Status berhasil diubah menjadi ${newStatus}`;
                    }
                });
            },

            makeCall() {
                if (!this.targetNumber) {
                    alert('Masukkan nomor tujuan terlebih dahulu!');
                    return;
                }
                this.infoMessage = 'Menghubungkan panggilan ke MicroSIP...';
                
                fetch(`/dashboard/api/agent/click-to-call`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                    },
                    body: JSON.stringify({
                        extension: this.extension,
                        destination: this.targetNumber
                    })
                })
                .then(res => res.json().then(data => ({ ok: res.ok, data })))
                .then(({ ok, data }) => {
                    this.infoMessage = data.message;
                    // Refresh state call lebih cepat setelah originate (max 3x percobaan)
                    [1500, 3500, 6000].forEach(ms => setTimeout(() => { this.fetchAgentStatus(); }, ms));
                    setTimeout(() => { this.fetchLogs(1); }, 5000);
                })
                .catch(err => {
                    this.infoMessage = 'Gagal terhubung ke server backend.';
                });
            },

            fetchLogs(page) {
                this.isLoading = true; // 🚀 Aktifkan Loading
                
                let params = new URLSearchParams({
                    page: page,
                    search: this.filters.search
                });

                fetch(`/dashboard/api/call-logs?${params.toString()}`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.logs = response.data.data.map(log => ({ 
                            ...log, 
                            isSaving: false,
                            showNoteInput: false 
                        }));
                        this.pagination = { current_page: response.data.current_page, last_page: response.data.last_page, total: response.data.total };
                    }
                })
                .finally(() => {
                    this.isLoading = false; // 🚀 Matikan loading saat selesai (berhasil/gagal)
                });
            },
            
            async saveNote(log) {
                if (!log || !log.uniqueid) {
                    alert('Error: Unique ID panggilan ini tidak ditemukan!');
                    return;
                }

                log.isSaving = true;

                try {
                    let response = await fetch(`/dashboard/api/call-logs/${log.uniqueid}/note`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ notes: log.notes })
                    });

                    let rawText = await response.text();
                    
                    let data;
                    try {
                        data = JSON.parse(rawText);
                    } catch (e) {
                        throw new Error("Server mengembalikan HTML/Bukan JSON.");
                    }

                    if (response.ok && data.status === 'success') {
                        log.showNoteInput = false;
                    } else {
                        alert('Gagal dari Server: ' + (data.message || 'Pesan tidak diketahui'));
                    }

                } catch (err) {
                    alert('Gagal menyimpan catatan: ' + err.message);
                } finally {
                    log.isSaving = false; 
                }
            },

            getPaginationPages() {
                // O(1): jangan loop 1..last.
                const current = this.pagination.current_page || 1;
                const last = this.pagination.last_page || 1;
                const delta = 2;
                const set = new Set([1, last]);
                for (let i = current - delta; i <= current + delta; i++) {
                    if (i > 1 && i < last) set.add(i);
                }
                const sorted = [...set].sort((a, b) => a - b);
                const out = [];
                sorted.forEach((p, idx) => {
                    if (idx > 0 && p - sorted[idx - 1] > 1) out.push('...');
                    out.push(p);
                });
                return out;
            },

            // 🚀 CUSTOMER ASSIGNED METHODS
            fetchAssignedCustomers() {
                this.isLoadingCustomers = true;
                fetch(`/dashboard/crm/agent/${this.extension}/customers`, { headers: { 'Accept': 'application/json' } })
                .then(res => res.json())
                .then(response => {
                    if (response.status === 'success') {
                        this.assignedCustomers = response.data;
                    }
                })
                .catch(err => console.error('Gagal memuat customer assigned:', err))
                .finally(() => {
                    this.isLoadingCustomers = false;
                });
            },

            callCustomer(phone, name) {
                if (this.currentStatus !== 'online') {
                    this.infoMessage = 'Status harus Online untuk menelepon';
                    return;
                }
                this.targetNumber = phone;
                this.infoMessage = `Menghubungkan ke ${name} (${phone})...`;
                this.makeCall();
            },

            formatCustomerStatus(status) {
                const labels = {
                    'new': 'New',
                    'contacted': 'Contacted',
                    'qualified': 'Qualified',
                    'proposal': 'Proposal',
                    'closed_won': 'Closed Won',
                    'closed_lost': 'Closed Lost',
                };
                return labels[status] || status;
            },

            getCustomerStatusClass(status) {
                const classes = {
                    'new': 'bg-blue-50 text-blue-700 border-blue-200',
                    'contacted': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'qualified': 'bg-purple-50 text-purple-700 border-purple-200',
                    'proposal': 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'closed_won': 'bg-green-50 text-green-700 border-green-200',
                    'closed_lost': 'bg-red-50 text-red-700 border-red-200',
                };
                return classes[status] || 'bg-slate-50 text-slate-700 border-slate-200';
            },

            async fetchRotationStatus() {
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/status`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await response.json();
                    if (data.status === 'success') {
                        this.rotationJoined = !!data.joined;
                    }
                } catch (err) {
                    console.error('Gagal cek rotation:', err);
                }
            },

            openRotationModal() { this.showRotationModal = true; },
            closeRotationModal() { this.showRotationModal = false; },

            async joinRotation() {
                this.rotationLoading = true;
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/join`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.rotationJoined = true;
                        this.closeRotationModal();
                        this.infoMessage = data.message;
                    } else {
                        alert(data.message || 'Gagal join rotation');
                    }
                } catch (err) {
                    alert('Gagal join rotation: ' + err.message);
                } finally {
                    this.rotationLoading = false;
                }
            },

            async leaveRotation() {
                if (!confirm('Keluar dari rotation PDS? (kembali ke Manual Call)')) return;
                try {
                    const response = await fetch(`/dashboard/crm/dialer/rotation/leave`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.rotationJoined = false;
                        this.infoMessage = data.message;
                    } else {
                        alert(data.message || 'Gagal leave rotation');
                    }
                } catch (err) {
                    alert('Gagal leave rotation: ' + err.message);
                }
            },

            formatPaymentStatus(status) {
                const labels = {
                    'unpaid': 'Belum Bayar',
                    'partial': 'Cicilan',
                    'paid': 'Lunas',
                    'discounted': 'Diskon Lunas',
                };
                return labels[status] || status;
            },

            getPaymentStatusClass(status) {
                const classes = {
                    'unpaid': 'bg-red-50 text-red-700 border-red-200',
                    'partial': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                    'paid': 'bg-green-50 text-green-700 border-green-200',
                    'discounted': 'bg-purple-50 text-purple-700 border-purple-200',
                };
                return classes[status] || 'bg-slate-50 text-slate-700 border-slate-200';
            },

            openPtpModal(customer) {
                this.ptpCustomer = customer;
                const existing = customer.promise_to_pay || {};
                this.ptpForm = {
                    amount: existing.amount || '',
                    date: existing.date || '',
                    note: existing.note || '',
                };
                this.showPtpModal = true;
            },

            closePtpModal() {
                this.showPtpModal = false;
                this.ptpCustomer = null;
                this.ptpForm = { amount: '', date: '', note: '' };
            },

            async submitPtp() {
                if (!this.ptpCustomer) return;
                if (!this.ptpForm.amount || !this.ptpForm.date) {
                    alert('Nominal dan tanggal janji wajib diisi!');
                    return;
                }
                this.ptpSaving = true;
                try {
                    const response = await fetch(`/dashboard/crm/agent/${this.extension}/customers/${this.ptpCustomer.id}/ptp`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            ptp_amount: this.ptpForm.amount,
                            ptp_date: this.ptpForm.date,
                            ptp_note: this.ptpForm.note,
                        }),
                    });
                    const data = await response.json();
                    if (response.ok && data.status === 'success') {
                        this.closePtpModal();
                        this.fetchAssignedCustomers();
                    } else {
                        alert(data.message || 'Gagal menyimpan PTP');
                    }
                } catch (err) {
                    alert('Gagal menyimpan PTP: ' + err.message);
                } finally {
                    this.ptpSaving = false;
                }
            },

            ptpStatusLabel(ptp) {
                if (!ptp) return '';
                if (ptp.status === 'kept') return 'Ditepati';
                if (ptp.status === 'rolling' || ptp.status === 'broken') return 'Rolling';
                const today = new Date().toISOString().split('T')[0];
                return (ptp.date && ptp.date < today) ? 'Overdue' : 'New';
            },

            ptpBadgeClass(ptp) {
                if (!ptp) return 'bg-slate-50 text-slate-600 border-slate-200';
                if (ptp.status === 'kept') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
                if (ptp.status === 'rolling' || ptp.status === 'broken') return 'bg-rose-50 text-rose-700 border-rose-200';
                const today = new Date().toISOString().split('T')[0];
                return (ptp.date && ptp.date < today)
                    ? 'bg-red-50 text-red-700 border-red-200'
                    : 'bg-amber-50 text-amber-700 border-amber-200';
            },

            formatPtpDate(dateStr) {
                if (!dateStr) return '';
                const d = new Date(dateStr);
                return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
            },

            formatCurrency(amount) {
                return new Intl.NumberFormat('id-ID').format(amount || 0);
            },

            getPaymentProgress(customer) {
                if (!customer.total_amount || customer.total_amount <= 0) return 0;
                const paid = (customer.paid_amount || 0) + (customer.discount_amount || 0);
                return Math.min(100, Math.round((paid / customer.total_amount) * 100));
            }
        }));
    });
