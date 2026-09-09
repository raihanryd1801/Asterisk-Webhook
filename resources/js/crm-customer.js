// resources/js/crm-customers.js

window.crmCustomers = function () {
    return {
        customers: window.crmCustomerData?.customers || [],
        pagination: window.crmCustomerData?.pagination || {},
        agents: window.crmCustomerData?.agents || [],
        statuses: window.crmCustomerData?.statuses || [],
        search: '',
        statusFilter: '',
        agentFilter: '',
        showModal: false,
        showCallHistoryModal: false,
        modalTitle: '',
        form: { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' },
        selectedCustomer: null,
        paymentStatusFilter: '',
        callHistory: [],
        callHistoryLoading: false,
        submitting: false,

        async fetchCustomers(page = 1) {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.statusFilter) params.append('status', this.statusFilter);
            if (this.paymentStatusFilter) params.append('payment_status', this.paymentStatusFilter);
            if (this.agentFilter) params.append('agent_id', this.agentFilter);
            params.append('page', page);
            
            try {
                const response = await fetch(`${window.crmCustomerData.indexUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.customers = data.data;
                this.pagination = {
                    current_page: data.current_page,
                    last_page: data.last_page,
                    per_page: data.per_page,
                    total: data.total,
                    from: data.from,
                    to: data.to,
                    prev_page_url: data.prev_page_url,
                    next_page_url: data.next_page_url,
                };
            } catch (e) {
                console.error(e);
            }
        },

        prevPage() {
            if (this.pagination.prev_page_url) this.fetchCustomers(this.pagination.current_page - 1);
        },
        nextPage() {
            if (this.pagination.next_page_url) this.fetchCustomers(this.pagination.current_page + 1);
        },

        openCreateModal() {
            this.modalTitle = 'Tambah Customer';
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' };
            this.showModal = true;
        },

        openEditModal(customer) {
            this.modalTitle = 'Edit Customer';
            this.form = {
                id: customer.id,
                name: customer.name,
                phone: customer.phone,
                email: customer.email || '',
                company: customer.company || '',
                status: customer.status,
                assigned_agent_id: customer.assigned_agent_id || '',
                notes: customer.notes || '',
                total_amount: customer.total_amount || '',
                paid_amount: customer.paid_amount || '',
                discount_amount: customer.discount_amount || '',
                payment_status: customer.payment_status || 'unpaid',
                payment_notes: customer.payment_notes || '',
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '' };
        },

        async submitForm() {
            this.submitting = true;
            const isEdit = !!this.form.id;
            const url = isEdit ? `${window.crmCustomerData.indexUrl}/${this.form.id}` : window.crmCustomerData.indexUrl;
            const method = isEdit ? 'PUT' : 'POST';
            
            const formData = new FormData();
            Object.keys(this.form).forEach(key => {
                if (this.form[key] !== '' && this.form[key] !== null) formData.append(key, this.form[key]);
            });
            formData.append('_method', method);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            try {
                const response = await fetch(url, { method: 'POST', body: formData });
                const data = await response.json();
                if (data.status === 'success') {
                    this.closeModal();
                    this.fetchCustomers(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.submitting = false;
            }
        },

        async deleteCustomer(id) {
            if (!confirm('Yakin ingin menghapus customer ini?')) return;
            try {
                const response = await fetch(`${window.crmCustomerData.indexUrl}/${id}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ _method: 'DELETE' })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.fetchCustomers(this.pagination.current_page);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            }
        },

        async openCallHistoryModal(customer) {
            this.selectedCustomer = customer;
            this.showCallHistoryModal = true;
            this.callHistoryLoading = true;
            this.callHistory = [];
            
            try {
                const response = await fetch(`${window.crmCustomerData.indexUrl}/${customer.id}/calls`, {
                    headers: { 'Accept': 'application/json' }
                });
                this.callHistory = await response.json();
            } catch (e) {
                console.error(e);
            } finally {
                this.callHistoryLoading = false;
            }
        },

        closeCallHistoryModal() {
            this.showCallHistoryModal = false;
            this.selectedCustomer = null;
            this.callHistory = [];
        },

        formatStatus(status) {
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

        getStatusClass(status) {
            const classes = {
                'new': 'bg-blue-100 text-blue-800',
                'contacted': 'bg-yellow-100 text-yellow-800',
                'qualified': 'bg-purple-100 text-purple-800',
                'proposal': 'bg-indigo-100 text-indigo-800',
                'closed_won': 'bg-green-100 text-green-800',
                'closed_lost': 'bg-red-100 text-red-800',
            };
            return classes[status] || 'bg-slate-100 text-slate-800';
        },

        getDispositionClass(disposition) {
            const classes = {
                'ANSWERED': 'bg-green-100 text-green-800',
                'NO ANSWER': 'bg-yellow-100 text-yellow-800',
                'BUSY': 'bg-orange-100 text-orange-800',
                'FAILED': 'bg-red-100 text-red-800',
                'CANCEL': 'bg-slate-100 text-slate-800',
            };
            return classes[disposition] || 'bg-slate-100 text-slate-800';
        },

        formatDate(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        formatDateTime(dateStr) {
            const d = new Date(dateStr);
            return d.toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },

        formatDuration(seconds) {
            const h = Math.floor(seconds / 3600);
            const m = Math.floor((seconds % 3600) / 60);
            const s = seconds % 60;
            if (h > 0) return `${h}h ${m}m ${s}s`;
            if (m > 0) return `${m}m ${s}s`;
            return `${s}s`;
        },

        formatCurrency(value) {
            if (!value) return '0';
            return new Intl.NumberFormat('id-ID').format(value);
        },

        formatPaymentStatus(status) {
            const labels = {
                'unpaid': 'Belum Bayar',
                'partial': 'Cicilan',
                'paid': 'Lunas'
            };
            return labels[status] || status;
        },

        getPaymentStatusClass(status) {
            const classes = {
                'unpaid': 'bg-red-50 text-red-700 border-red-200',
                'partial': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'paid': 'bg-green-50 text-green-700 border-green-200'
            };
            return classes[status] || 'bg-slate-100 text-slate-800 border-slate-200';
        }
    };
};