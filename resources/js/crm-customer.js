// resources/js/crm-customers.js

window.crmCustomers = function () {
    return {
        customers: window.crmCustomerData?.customers || [],
        pagination: window.crmCustomerData?.pagination || {},
        agents: window.crmCustomerData?.agents || [],
        statuses: window.crmCustomerData?.statuses || [],
        collectors: window.crmCustomerData?.collectors || [],
        search: '',
        statusFilter: '',
        agentFilter: '',
        showModal: false,
        showCallHistoryModal: false,
        showImportModal: false,
        importLoading: false,
        importResult: null,
        selectedIds: [],
        bulkCollectorId: '',
        bulkLoading: false,
        recalcLoading: false,
        modalTitle: '',
        form: { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '', due_date: '', collector_id: '', risk_level: 'low' },
        selectedCustomer: null,
        paymentStatusFilter: '',
        bucketFilter: '',
        handoverFilter: '',
        badDebtOnly: false,
        showHandoverModal: false,
        handoverLoading: false,
        handoverForm: { handover_to: '', handover_date: '', handover_notes: '' },
        callHistory: [],
        callHistoryLoading: false,
        submitting: false,

        init() {
            // Hydrate filter dari query string agar halaman lain (mis. Buckets)
            // bisa deep-link ke Customers yang sudah terfilter.
            try {
                const q = new URLSearchParams(window.location.search);
                const map = {
                    search: 'search', status: 'statusFilter',
                    payment_status: 'paymentStatusFilter', bucket: 'bucketFilter',
                    agent_id: 'agentFilter',
                    handover_status: 'handoverFilter',
                };
                let hasFilter = false;
                for (const [param, key] of Object.entries(map)) {
                    if (q.has(param)) {
                        this[key] = q.get(param);
                        hasFilter = true;
                    }
                }
                if (q.get('bad_debt_only') === '1') {
                    this.badDebtOnly = true;
                    hasFilter = true;
                }
                if (hasFilter) this.fetchCustomers(1);
            } catch (e) {
                console.error(e);
            }
        },

        async fetchCustomers(page = 1) {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.statusFilter) params.append('status', this.statusFilter);
            if (this.paymentStatusFilter) params.append('payment_status', this.paymentStatusFilter);
            if (this.bucketFilter) params.append('bucket', this.bucketFilter);
            if (this.agentFilter) params.append('agent_id', this.agentFilter);
            if (this.handoverFilter) params.append('handover_status', this.handoverFilter);
            if (this.badDebtOnly) params.append('bad_debt_only', '1');
            params.append('page', page);

            try {
                const response = await fetch(`${window.crmCustomerData.indexUrl}?${params}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const data = await response.json();
                this.customers = data.data;
                this.selectedIds = [];
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
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '', due_date: '', collector_id: '', risk_level: 'low' };
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
                due_date: customer.due_date ? String(customer.due_date).substring(0, 10) : '',
                collector_id: customer.collector_id || '',
                risk_level: customer.risk_level || 'low',
            };
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
            this.form = { id: '', name: '', phone: '', email: '', company: '', status: 'new', assigned_agent_id: '', notes: '', total_amount: '', paid_amount: '', discount_amount: '', payment_status: 'unpaid', payment_notes: '', due_date: '', collector_id: '', risk_level: 'low' };
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
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Accept': 'application/json' },
                });
                if (response.status === 403) {
                    const data = await response.json().catch(() => null);
                    alert(data?.message || 'Premium Feature — hubungi admin jika ingin menggunakannya.');
                    return;
                }
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

        isSelected(id) {
            return this.selectedIds.includes(id);
        },

        toggleSelect(id) {
            if (this.selectedIds.includes(id)) {
                this.selectedIds = this.selectedIds.filter(x => x !== id);
            } else {
                this.selectedIds.push(id);
            }
        },

        toggleSelectAll(event) {
            if (event.target.checked) {
                this.selectedIds = this.customers.map(c => c.id);
            } else {
                this.selectedIds = [];
            }
        },

        clearSelection() {
            this.selectedIds = [];
            this.bulkCollectorId = '';
        },

        async bulkAssign() {
            if (!this.bulkCollectorId) {
                alert('Pilih debt collector dulu');
                return;
            }
            if (this.selectedIds.length === 0) {
                alert('Pilih minimal 1 customer');
                return;
            }
            this.bulkLoading = true;
            try {
                const response = await fetch(window.crmCustomerData.bulkAssignUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_ids: this.selectedIds,
                        collector_id: this.bulkCollectorId,
                    })
                });
                const data = await response.json();
                if (data.status === 'success') {
                    alert(data.message);
                    this.clearSelection();
                    this.fetchCustomers(this.pagination.current_page || 1);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.bulkLoading = false;
            }
        },

        showResultModal: false,
        resultTitle: '',
        resultSummary: '',
        resultRows: [],
        resultTruncated: false,

        openResultModal(title, summary, rows, truncated) {
            this.resultTitle = title;
            this.resultSummary = summary;
            this.resultRows = rows || [];
            this.resultTruncated = !!truncated;
            this.showResultModal = true;
        },

        closeResultModal() {
            this.showResultModal = false;
            this.resultRows = [];
        },

        async recalculateBuckets() {
            if (!confirm('Hitung ulang DPD / Bucket / Risk semua customer dari due_date?')) return;
            this.recalcLoading = true;
            try {
                const response = await fetch(window.crmCustomerData.recalcUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.openResultModal(
                        'Hasil Recalculate Bucket',
                        data.message || 'Selesai',
                        (data.changes || []).map(c => ({ name: c.name, phone: c.phone, from: c.from, to: c.to })),
                        data.truncated
                    );
                } else {
                    alert(data.message || 'Error');
                }
                this.fetchCustomers(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.recalcLoading = false;
            }
        },

        exportHref() {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.statusFilter) params.append('status', this.statusFilter);
            if (this.paymentStatusFilter) params.append('payment_status', this.paymentStatusFilter);
            if (this.bucketFilter) params.append('bucket', this.bucketFilter);
            if (this.agentFilter) params.append('agent_id', this.agentFilter);
            if (this.handoverFilter) params.append('handover_status', this.handoverFilter);
            if (this.badDebtOnly) params.append('bad_debt_only', '1');
            const q = params.toString();
            return window.crmCustomerData.exportUrl + (q ? `?${q}` : '');
        },

        exportHandoverHref() {
            const params = new URLSearchParams();
            if (this.search) params.append('search', this.search);
            if (this.bucketFilter) params.append('bucket', this.bucketFilter);
            if (this.handoverFilter) params.append('handover_status', this.handoverFilter);
            if (this.badDebtOnly) params.append('bad_debt_only', '1');
            const q = params.toString();
            return window.crmCustomerData.handoverExportUrl + (q ? `?${q}` : '');
        },

        async markHandoverReady() {
            if (this.selectedIds.length === 0) {
                alert('Pilih minimal 1 customer');
                return;
            }
            if (!confirm(`Tandai ${this.selectedIds.length} case sebagai SIAP handover ke pihak ketiga?`)) return;
            this.handoverLoading = true;
            try {
                const response = await fetch(window.crmCustomerData.handoverReadyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ customer_ids: this.selectedIds }),
                });
                const data = await response.json();
                alert(data.message || 'Selesai');
                this.clearSelection();
                this.fetchCustomers(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.handoverLoading = false;
            }
        },

        openHandoverModal() {
            if (this.selectedIds.length === 0) {
                alert('Pilih minimal 1 customer');
                return;
            }
            this.handoverForm = { handover_to: '', handover_date: new Date().toISOString().split('T')[0], handover_notes: '' };
            this.showHandoverModal = true;
        },

        closeHandoverModal() {
            this.showHandoverModal = false;
        },

        async submitHandover() {
            if (!this.handoverForm.handover_to) {
                alert('Nama pihak ketiga wajib diisi');
                return;
            }
            this.handoverLoading = true;
            try {
                const response = await fetch(window.crmCustomerData.handoverSubmitUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        customer_ids: this.selectedIds,
                        handover_to: this.handoverForm.handover_to,
                        handover_date: this.handoverForm.handover_date || null,
                        handover_notes: this.handoverForm.handover_notes || null,
                    }),
                });
                const data = await response.json();
                alert(data.message || 'Selesai');
                this.closeHandoverModal();
                this.clearSelection();
                this.fetchCustomers(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.handoverLoading = false;
            }
        },

        async handoverRecall() {
            if (this.selectedIds.length === 0) {
                alert('Pilih minimal 1 customer');
                return;
            }
            if (!confirm(`Tarik kembali ${this.selectedIds.length} case dari pihak ketiga?`)) return;
            this.handoverLoading = true;
            try {
                const response = await fetch(window.crmCustomerData.handoverRecallUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ customer_ids: this.selectedIds }),
                });
                const data = await response.json();
                alert(data.message || 'Selesai');
                this.clearSelection();
                this.fetchCustomers(this.pagination.current_page || 1);
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.handoverLoading = false;
            }
        },

        formatHandoverStatus(status) {
            const labels = {
                'none': '-',
                'ready': 'Siap Handover',
                'handed_over': 'Diserahkan',
                'returned': 'Ditarik Kembali',
            };
            return labels[status] || status;
        },

        getHandoverClass(status) {
            const classes = {
                'ready': 'bg-amber-50 text-amber-700 border-amber-200',
                'handed_over': 'bg-orange-50 text-orange-700 border-orange-200',
                'returned': 'bg-slate-100 text-slate-600 border-slate-300',
            };
            return classes[status] || 'bg-slate-100 text-slate-800 border-slate-200';
        },

        openImportModal() {
            this.importResult = null;
            this.showImportModal = true;
        },

        closeImportModal() {
            this.showImportModal = false;
        },

        async submitImport() {
            const file = this.$refs.importFile?.files?.[0];
            if (!file) {
                alert('Pilih file dulu');
                return;
            }
            this.importLoading = true;
            this.importResult = null;
            try {
                const formData = new FormData();
                formData.append('file', file);
                const response = await fetch(window.crmCustomerData.importUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
                const data = await response.json();
                if (data.status === 'success') {
                    this.importResult = data;
                    this.fetchCustomers(1);
                } else {
                    alert(data.message || 'Error');
                }
            } catch (e) {
                console.error(e);
                alert('Terjadi kesalahan');
            } finally {
                this.importLoading = false;
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
                'paid': 'Lunas',
                'discounted': 'Diskon Lunas'
            };
            return labels[status] || status;
        },

        getPaymentStatusClass(status) {
            const classes = {
                'unpaid': 'bg-red-50 text-red-700 border-red-200',
                'partial': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'paid': 'bg-green-50 text-green-700 border-green-200',
                'discounted': 'bg-purple-50 text-purple-700 border-purple-200'
            };
            return classes[status] || 'bg-slate-100 text-slate-800 border-slate-200';
        },

        getBucketClass(bucket) {
            const classes = {
                'Current': 'bg-blue-50 text-blue-700 border-blue-200',
                'Bucket 1': 'bg-green-50 text-green-700 border-green-200',
                'Bucket 2': 'bg-yellow-50 text-yellow-700 border-yellow-200',
                'Bucket 3': 'bg-orange-50 text-orange-700 border-orange-200',
                'NPL': 'bg-red-50 text-red-700 border-red-200'
            };
            return classes[bucket] || 'bg-slate-100 text-slate-800 border-slate-200';
        }
    };
};