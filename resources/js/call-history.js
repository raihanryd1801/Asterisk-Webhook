// resources/js/call-history.js

window.callHistoryPage = function() {
    return {
        logs: [],
        agents: [],
        playingFile: null,
        currentAudio: null,
        audioProgress: '0:00 / 0:00',
        progressInterval: null,
        
        pagination: { current_page: 1, last_page: 1, total: 0 },
        gotoPage: '',
        
        filters: { start_date: '', end_date: '', search: '', agent_extension: '', sort: 'newest' },
        
        isExporting: false, 
        isExportingZip: false,
        exportFormat: 'xlsx',
        isSyncing: false,
        isLoading: false, 
        
        // Ambil cache dari sessionStorage browser
        queryCache: JSON.parse(sessionStorage.getItem('cdr_query_cache') || '{}'), 
        
        init() {
            this.fetchAgentsList();
            this.fetchLogs(1);
        },

        async syncAndRefresh() {
            this.isSyncing = true;
            this.queryCache = {}; 
            sessionStorage.removeItem('cdr_query_cache');
            try {
                let res = await fetch('/dashboard/api/cdr-sync', { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                let result = await res.json();
                if (result.status === 'success') {
                    this.fetchLogs(1);
                } else {
                    alert('Gagal melakukan sinkronisasi data.');
                }
            } catch (err) {
                console.error("Sync Error:", err);
                alert("Terjadi kesalahan pada server saat sinkronisasi.");
            } finally {
                this.isSyncing = false; 
            }
        },
        
        formatTime(seconds) {
            let m = Math.floor(seconds / 60);
            let s = Math.floor(seconds % 60);
            return m + ':' + (s < 10 ? '0' : '') + s;
        },

        playAudio(filename) {
            if (this.currentAudio) {
                this.currentAudio.pause();
                clearInterval(this.progressInterval);
            }

            this.playingFile = filename;
            let audioUrl = '/dashboard/api/play-recording?file=' + filename;
            this.currentAudio = new Audio(audioUrl);
            
            this.currentAudio.play().then(() => {
                this.currentAudio.onloadedmetadata = () => {
                    let dur = this.currentAudio.duration;
                    this.audioProgress = '0:00 / ' + this.formatTime(dur);
                };

                this.progressInterval = setInterval(() => {
                    if (this.currentAudio) {
                        let curr = this.currentAudio.currentTime;
                        let dur = this.currentAudio.duration || 0;
                        this.audioProgress = this.formatTime(curr) + ' / ' + this.formatTime(dur);
                    }
                }, 500);
            }).catch(err => {
                alert('Gagal memutar audio: File tidak ditemukan.');
                this.stopAudio();
            });

            this.currentAudio.onended = () => {
                this.stopAudio();
            };
        },

        stopAudio() {
            if (this.currentAudio) {
                this.currentAudio.pause();
                this.currentAudio = null;
            }
            clearInterval(this.progressInterval);
            this.playingFile = null;
            this.audioProgress = '0:00 / 0:00';
        },

        fetchAgentsList() {
            fetch('/dashboard/api/live-agents', { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(data => { this.agents = data.agents || []; });
        },

        fetchLogs(page) {
            if (page < 1) page = 1;
            if (page > this.pagination.last_page && this.pagination.last_page > 0) {
                page = this.pagination.last_page;
            }

            this.isLoading = true; 

            let params = new URLSearchParams({
                page: page,
                start_date: this.filters.start_date,
                end_date: this.filters.end_date,
                search: this.filters.search,
                agent_extension: this.filters.agent_extension,
                sort: this.filters.sort 
            });

            let cacheKey = params.toString();

            if (this.queryCache[cacheKey]) {
                this.logs = this.queryCache[cacheKey].logs;
                this.pagination = this.queryCache[cacheKey].pagination;
                this.isLoading = false;
                return; 
            }

            fetch(`/dashboard/api/call-logs?${cacheKey}`, { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    this.logs = response.data.data;
                    this.pagination = {
                        current_page: response.data.current_page,
                        last_page: response.data.last_page,
                        total: response.data.total
                    };

                    this.queryCache[cacheKey] = {
                        logs: this.logs,
                        pagination: this.pagination
                    };
                    sessionStorage.setItem('cdr_query_cache', JSON.stringify(this.queryCache));
                }
            })
            .finally(() => {
                this.isLoading = false; 
            });
        },

        getPaginationPages() {
            let current = this.pagination.current_page;
            let last = this.pagination.last_page;
            let delta = 2;
            let range = [];
            
            for (let i = 1; i <= last; i++) {
                if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
                    range.push(i);
                } else if (range[range.length - 1] !== '...') {
                    range.push('...');
                }
            }
            return range;
        },

        jumpToPage() {
            let p = parseInt(this.gotoPage);
            if (p >= 1 && p <= this.pagination.last_page) {
                this.fetchLogs(p);
                this.gotoPage = '';
            } else {
                alert('Nomor halaman tidak valid (1 - ' + this.pagination.last_page + ')');
            }
        },

        resetFilters() {
            this.stopAudio();
            this.filters = { start_date: '', end_date: '', search: '', agent_extension: '', sort: 'oldest' };
            this.queryCache = {}; 
            sessionStorage.removeItem('cdr_query_cache');
            this.fetchLogs(1);
        },

        async exportRecordingsZip() {
    this.isExportingZip = true; 
    try {
        // Mengambil parameter filter yang sedang aktif
        let params = new URLSearchParams(this.filters).toString();
        let url = `/dashboard/api/call-logs/export-zip?${params}`;
        
        let response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
        if (!response.ok) throw new Error('Gagal memulai proses zip rekaman di server.');
        
        let data = await response.json();
        
        // Poling status berkala (karena proses zip butuh waktu)
        let checkInterval = setInterval(async () => {
            try {
                let res = await fetch(`/dashboard/api/call-logs/export-zip-status?filename=${data.filename}`);
                if (!res.ok) {
                    clearInterval(checkInterval);
                    throw new Error('Server error saat cek status zip.');
                }

                let status = await res.json();
                if (status.ready) {
                    clearInterval(checkInterval);
                    window.location.href = status.url; // Otomatis download file .zip
                    this.isExportingZip = false; 
                }
            } catch (err) {
                clearInterval(checkInterval);
                console.error("Polling ZIP Error:", err);
                this.isExportingZip = false;
                alert("Proses pengunduhan ZIP terhenti karena error server.");
            }
        }, 5000); // Cek tiap 5 detik

    } catch (error) {
        console.error("Export ZIP Error:", error);
        this.isExportingZip = false; 
        alert("Gagal memulai proses arsip rekaman.");
    }
},

        async exportData() {
            this.isExporting = true; 
            try {
                let params = new URLSearchParams(this.filters).toString();
                let url = `/dashboard/api/call-logs/export?${params}&format=${this.exportFormat}`;
                let response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                
                if (!response.ok) throw new Error('Gagal memicu export di server.');
                
                let data = await response.json();
                let checkInterval = setInterval(async () => {
                    try {
                        let res = await fetch(`/dashboard/api/call-logs/export-status?filename=${data.filename}`);
                        
                        if (!res.ok) {
                            clearInterval(checkInterval);
                            throw new Error('Server error ' + res.status);
                        }

                        let status = await res.json();
                        if (status.ready) {
                            clearInterval(checkInterval);
                            window.location.href = status.url; 
                            this.isExporting = false; 
                        }
                    } catch (err) {
                        clearInterval(checkInterval);
                        console.error("Polling Error:", err);
                        this.isExporting = false;
                        alert("Proses terhenti karena error server. Silakan cek log Laravel.");
                    }
                }, 5000); 

            } catch (error) {
                console.error("Export Error:", error);
                this.isExporting = false; 
                alert("Gagal memulai ekspor data.");
            }
        }
        
    };

    
}