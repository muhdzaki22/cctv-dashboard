@extends('layouts.app')

@section('title', 'CCTV Foot Traffic Dashboard')

@section('content')
<div x-data="dashboard()" x-init="init()" class="min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">CCTV Foot Traffic Dashboard</h1>
                    <p class="text-blue-100 mt-1">Store entrance monitoring and analytics</p>
                </div>

                <div class="flex flex-col items-end gap-3">
                    <div class="flex items-center gap-4">
                        <span class="text-white text-sm">{{ Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg text-white text-sm transition">
                                Logout
                            </button>
                        </form>
                    </div>

                    <div class="flex flex-wrap gap-3 items-end justify-end">
                        <div>
                            <label class="text-sm text-white block mb-1">Start Date</label>
                            <input type="date" x-model="selectedDate" @change="onStartDateChange"
                                class="px-3 py-2 rounded-lg text-white bg-white/10 focus:ring-2 focus:ring-blue-300 focus:outline-none">
                        </div>
                        <div>
                            <label class="text-sm text-white block mb-1">End Date</label>
                            <input type="date" x-model="endDate" @change="onEndDateChange"
                                class="px-3 py-2 rounded-lg text-white bg-white/10 focus:ring-2 focus:ring-blue-300 focus:outline-none">
                        </div>
                        <button @click="fetchNvrData" :disabled="nvrLoading"
                            class="px-4 py-2 bg-emerald-500 hover:bg-emerald-400 rounded-lg transition">
                            <span x-show="!nvrLoading">🔄 Fetch NVR Data</span>
                            <span x-show="nvrLoading">Fetching...</span>
                        </button>
                        <button @click="refreshAll" :disabled="loading"
                            class="px-4 py-2 bg-blue-500 hover:bg-blue-400 rounded-lg transition">
                            <span x-show="!loading">Refresh</span>
                            <span x-show="loading">Loading...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <!-- Peak Hour Card -->
        <div class="mb-8">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-2xl shadow-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 text-sm uppercase tracking-wide">Peak Hour for <span x-text="formatDate(selectedDate)"></span></p>
                        <template x-if="peakHour">
                            <div>
                                <p class="text-5xl font-bold mt-2" x-text="peakHour.hour"></p>
                                <p class="text-emerald-100 mt-1">
                                    <span x-text="peakHour.total_count"></span> visits
                                    (<span x-text="peakHour.total_duration"></span> seconds)
                                </p>
                            </div>
                        </template>
                        <template x-if="!peakHour">
                            <div>
                                <p class="text-3xl font-bold mt-2">No data available</p>
                                <p class="text-emerald-100 mt-1">Select a date with recordings to see peak hour</p>
                            </div>
                        </template>
                    </div>
                    <div class="text-6xl">🚶</div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Visits</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="todayStats.total">0</p>
                    </div>
                    <div class="text-3xl">👥</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-emerald-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Total Duration</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="Math.round(todayStats.total_duration / 60)">0</p>
                        <p class="text-xs text-gray-400">minutes</p>
                    </div>
                    <div class="text-3xl">⏱️</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Avg Visit Duration</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="todayStats.avg_duration">0</p>
                        <p class="text-xs text-gray-400">seconds</p>
                    </div>
                    <div class="text-3xl">📊</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">This Week</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="weeklyTotal">0</p>
                    </div>
                    <div class="text-3xl">📅</div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Hourly Traffic Chart -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Hourly Traffic</h2>
                    <span class="text-sm text-gray-500" x-text="formatDate(selectedDate) + ' — ' + formatDate(endDate)"></span>
                </div>
                <div class="relative h-80">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Duration Categories -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Visit Duration Categories</h2>
                    <span class="text-sm text-gray-500" x-text="formatDate(selectedDate) + ' — ' + formatDate(endDate)"></span>
                </div>
                <div class="relative h-80">
                    <canvas id="genderChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Daily Traffic Chart -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Daily Traffic (Last 30 Days)</h2>
                <div class="flex gap-2">
                    <button @click="setPeriod(7)" :class="period === 7 ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                        class="px-3 py-1 rounded-lg text-sm transition">7 Days</button>
                    <button @click="setPeriod(30)" :class="period === 30 ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                        class="px-3 py-1 rounded-lg text-sm transition">30 Days</button>
                    <button @click="setPeriod(90)" :class="period === 90 ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                        class="px-3 py-1 rounded-lg text-sm transition">90 Days</button>
                </div>
            </div>
            <div class="relative h-80">
                <canvas id="dailyChart"></canvas>
            </div>
        </div>

        <!-- Weekly Traffic Chart -->
        <div class="bg-white rounded-xl shadow-md p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-800">Weekly Traffic Overview</h2>
            </div>
            <div class="relative h-80">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
    </main>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
function dashboard() {
    return {
        selectedDate: '{{ $defaultDate }}',
        endDate: '',
        period: 30,
        loading: false,
        nvrLoading: false,
        peakHour: null,
        isLoadingDaily: false,
        dailyUpdateTimer: null,
        isUpdatingDailyChart: false,
        todayStats: { male: 0, female: 0, total: 0 },
        weeklyTotal: 0,

        // Chart instances
        hourlyChart: null,
        genderChart: null,
        dailyChart: null,
        weeklyChart: null,

        init() {
            console.log('Dashboard initialized with date:', this.selectedDate);
            // Initialize endDate to current date
            const today = new Date();
            this.endDate = today.toISOString().split('T')[0];
            // Wait for DOM to be fully loaded before initializing charts
            this.$nextTick(() => {
                console.log('DOM loaded, refreshing all data');
                this.refreshAll();
            });
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        },

        async onStartDateChange() {
            console.log('Start date changed to:', this.selectedDate);
            // Auto-refresh data that depends on start date
            await Promise.all([
                this.loadHourlyData(),
                this.loadPeakHour(),
                this.loadGenderStats(),
                this.loadSummaryStats(), // Load summary stats
                this.loadDailyData(), // This will use the current period
                this.loadWeeklyData(),
                this.loadWeeklyCard(),
            ]);
        },

        async onEndDateChange() {
            console.log('End date changed to:', this.endDate);
            // Auto-refresh data that depends on date range
            await Promise.all([
                this.loadDailyData(),
                this.loadHourlyData(),
                this.loadGenderStats(),
                this.loadSummaryStats(),
                this.loadWeeklyData(),
                this.loadWeeklyCard(),
            ]);
        },

        setPeriod(days) {
            console.log('setPeriod called with', days, 'days');
            this.period = days;
            const end = new Date(this.selectedDate);
            const start = new Date(end);
            start.setDate(start.getDate() - days);
            // Don't change endDate - only change the start date based on period

            // Clear any pending daily data update
            if (this.dailyUpdateTimer) {
                clearTimeout(this.dailyUpdateTimer);
            }

            // Debounce the daily data update to prevent rapid chart updates
            this.dailyUpdateTimer = setTimeout(() => {
                console.log('Executing debounced daily data update');
                this.loadDailyData(start.toISOString().split('T')[0], this.endDate);
            }, 500); // Increased from 300ms to 500ms
        },

        async fetchNvrData() {
            this.nvrLoading = true;
            try {
                const response = await fetch('/api/recordings/fetch', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ date: this.endDate })
                });

                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                if (!response.ok) {
                    const errorText = await response.text();
                    console.error('Error response:', errorText);
                    throw new Error(`Failed to fetch NVR data (${response.status}): ${errorText}`);
                }

                const result = await response.json();
                console.log('Fetch result:', result);

                if (result.error) {
                    alert(result.error);
                } else {
                    let message = `Successfully processed ${result.total_visits} visits from ${result.total_recordings} NVR recordings!`;
                    if (result.authenticated) {
                        message += ' (Auto-authenticated)';
                    }
                    alert(message);
                    // Refresh the charts with new data
                    this.refreshAll();
                }
            } catch (error) {
                console.error('Error fetching NVR data:', error);
                alert('Failed to fetch NVR data: ' + error.message);
            } finally {
                this.nvrLoading = false;
            }
        },

        async refreshAll() {
            console.log('refreshAll called, loading all data');
            console.log('Checking canvas elements:');
            console.log('hourlyChart exists:', !!document.getElementById('hourlyChart'));
            console.log('genderChart exists:', !!document.getElementById('genderChart'));
            console.log('dailyChart exists:', !!document.getElementById('dailyChart'));
            console.log('weeklyChart exists:', !!document.getElementById('weeklyChart'));

            this.loading = true;
            try {
                // Wait for DOM to be ready
                await this.$nextTick();
                // Add additional delay to ensure DOM is fully rendered
                await new Promise(resolve => setTimeout(resolve, 100));

                await Promise.all([
                    this.loadHourlyData(),
                    this.loadDailyData(),
                    this.loadWeeklyData(),
                    this.loadWeeklyCard(),
                    this.loadPeakHour(),
                    this.loadGenderStats(),
                    this.loadSummaryStats(),
                ]);
                console.log('All data loaded successfully');
            } finally {
                this.loading = false;
            }
        },

        async loadHourlyData() {
            try {
                const response = await fetch(`/api/recordings/hourly?start_date=${this.selectedDate}&end_date=${this.endDate}`);
                const data = await response.json();
                console.log('Hourly data loaded:', data);
                this.updateHourlyChart(data);
            } catch (error) {
                console.error('Error loading hourly data:', error);
            }
        },

        async loadPeakHour() {
            try {
                const response = await fetch(`/api/recordings/peak-hour?date=${this.selectedDate}`);
                if (response.ok) {
                    this.peakHour = await response.json();
                    console.log('Peak hour loaded:', this.peakHour);
                } else {
                    console.log('Peak hour response not ok:', response.status);
                    this.peakHour = null;
                }
            } catch (error) {
                console.error('Error loading peak hour:', error);
                this.peakHour = null;
            }
        },

        async loadGenderStats() {
            try {
                const response = await fetch(`/api/recordings/duration-categories?start_date=${this.selectedDate}&end_date=${this.endDate}`);
                const data = await response.json();
                console.log('Duration categories loaded:', data);
                this.updateGenderChart(data);
            } catch (error) {
                console.error('Error loading duration categories:', error);
            }
        },

        async loadSummaryStats() {
            try {
                const response = await fetch(`/api/recordings/stats?start_date=${this.selectedDate}&end_date=${this.endDate}`);
                const data = await response.json();
                console.log('Summary stats loaded:', data);
                this.todayStats = {
                    total: data.total || 0,
                    total_duration: data.total_duration || 0,
                    avg_duration: data.avg_duration || 0,
                    median_duration: data.median_duration || 0,
                    male: data.male || 0,
                    female: data.female || 0
                };
            } catch (error) {
                console.error('Error loading summary stats:', error);
            }
        },

        async loadDailyData(startDate, endDate) {
            // Prevent concurrent updates
            if (this.isLoadingDaily) {
                console.log('Daily data already loading, skipping');
                return;
            }

            this.isLoadingDaily = true;

            if (!startDate) {
                const end = new Date(this.endDate || this.selectedDate);
                const start = new Date(end);
                start.setDate(start.getDate() - this.period);
                startDate = start.toISOString().split('T')[0];
                endDate = (this.endDate || this.selectedDate);
            }
            console.log('Loading daily data from', startDate, 'to', endDate);
            try {
                const response = await fetch(`/api/recordings/daily?start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                console.log('Daily data loaded:', data);
                this.updateDailyChart(data);
            } catch (error) {
                console.error('Error loading daily data:', error);
            } finally {
                this.isLoadingDaily = false;
            }
        },

        async loadWeeklyData() {
            console.log('Loading weekly data from', this.selectedDate, 'to', this.endDate);
            try {
                const response = await fetch(`/api/recordings/weekly?start_date=${this.selectedDate}&end_date=${this.endDate}`);
                const data = await response.json();
                console.log('Weekly data loaded:', data);
                this.updateWeeklyChart(data);
            } catch (error) {
                console.error('Error loading weekly data:', error);
            }
        },

        async loadWeeklyCard() {
            const end = new Date(this.endDate);
            const weekStart = new Date(end);
            weekStart.setDate(end.getDate() - 6);
            console.log('Loading weekly card from', weekStart.toISOString().split('T')[0], 'to', this.endDate);
            try {
                const response = await fetch(`/api/recordings/weekly?start_date=${weekStart.toISOString().split('T')[0]}&end_date=${this.endDate}`);
                const data = await response.json();
                console.log('Weekly card loaded:', data);
                this.weeklyTotal = data.recording_count?.reduce((a, b) => a + b, 0) || 0;
            } catch (error) {
                console.error('Error loading weekly card:', error);
            }
        },

        updateHourlyChart(data) {
            console.log('updateHourlyChart called with data:', data);
            const canvas = document.getElementById('hourlyChart');
            if (!canvas) {
                console.error('Hourly chart canvas not found');
                return;
            }
            if (this.hourlyChart) {
                try { this.hourlyChart.destroy(); } catch (e) {}
                this.hourlyChart = null;
            }

            // Handle empty data
            if (!data.labels || data.labels.length === 0) {
                console.log('Hourly chart has no data, showing empty state');
                this.hourlyChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            label: 'Total Visits',
                            data: [0],
                            backgroundColor: 'rgba(156, 163, 175, 0.5)',
                            borderColor: 'rgb(156, 163, 175)',
                            borderWidth: 1,
                            borderRadius: 4
                        }]
                    },
                    options: { animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            title: {
                                display: true,
                                text: 'No data available for this date',
                                color: '#6b7280'
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
                return;
            }

            console.log('Creating hourly chart with', data.labels.length, 'data points');
            this.hourlyChart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Total Visits',
                        data: data.recording_count,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: { animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        },

        updateGenderChart(data) {
            console.log('updateDurationChart called with data:', data);
            const canvas = document.getElementById('genderChart');
            if (!canvas) {
                console.error('Duration chart canvas not found');
                return;
            }
            if (this.genderChart) {
                try {
                    this.genderChart.destroy();
                    this.genderChart = null;
                } catch (error) {
                    console.error('Error destroying duration chart:', error);
                    this.genderChart = null;
                }
            }

            // Handle empty data
            if (!data.data || data.data.every(val => val === 0)) {
                console.log('Duration chart has no data, showing empty state');
                this.genderChart = new Chart(canvas, {
                    type: 'doughnut',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            data: [1],
                            backgroundColor: ['rgba(156, 163, 175, 0.5)'],
                            borderColor: ['rgb(156, 163, 175)'],
                            borderWidth: 2
                        }]
                    },
                    options: { animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { padding: 20 }
                            },
                            title: {
                                display: true,
                                text: 'No data available for this date',
                                color: '#6b7280'
                            }
                        }
                    }
                });
                return;
            }

            console.log('Creating duration chart with categories:', data.labels);
            this.genderChart = new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: [
                            'rgba(34, 197, 94, 0.8)',   // Green for short visits
                            'rgba(234, 179, 8, 0.8)',  // Yellow for medium visits
                            'rgba(239, 68, 68, 0.8)'    // Red for long visits
                        ],
                        borderColor: [
                            'rgb(34, 197, 94)',
                            'rgb(234, 179, 8)',
                            'rgb(239, 68, 68)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: { animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
            console.log('Duration chart created successfully');
        },

        updateDailyChart(data) {
            console.log('updateDailyChart called with data:', data);

            // Prevent concurrent chart updates
            if (this.isUpdatingDailyChart) {
                console.log('Daily chart already updating, skipping');
                return;
            }

            this.isUpdatingDailyChart = true;

            // Wrap in try-catch to handle any errors
            try {
                const canvas = document.getElementById('dailyChart');
                if (!canvas) {
                    console.error('Daily chart canvas not found!');
                    return;
                }

                console.log('Canvas element found:', canvas);

                // Destroy existing chart if it exists
                if (this.dailyChart) {
                    console.log('Destroying existing daily chart');
                    try {
                        this.dailyChart.destroy();
                        this.dailyChart = null;
                    } catch (error) {
                        console.error('Error destroying daily chart:', error);
                        this.dailyChart = null;
                    }
                }

                // Handle empty data
                if (!data.labels || data.labels.length === 0) {
                    console.log('Daily chart has no data, showing empty state');
                    this.dailyChart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            labels: ['No Data'],
                            datasets: [{
                                label: 'Total Visitors',
                                data: [0],
                                borderColor: 'rgb(156, 163, 175)',
                                backgroundColor: 'rgba(156, 163, 175, 0.1)',
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: 'rgb(156, 163, 175)',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4
                            }]
                        },
                        options: { animation: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                title: {
                                    display: true,
                                    text: 'No data available for this date range',
                                    color: '#6b7280'
                                }
                            },
                            scales: {
                                y: { beginAtZero: true }
                            },
                            interaction: {
                                intersect: false,
                                mode: 'index'
                            }
                        }
                    });
                    console.log('Empty daily chart created successfully');
                    return;
                }

                console.log('Creating daily chart with', data.labels.length, 'data points');
                this.dailyChart = new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            label: 'Total Visitors',
                            data: data.total,
                            borderColor: 'rgb(59, 130, 246)',
                            backgroundColor: 'rgba(59, 130, 246, 0.1)',
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: 'rgb(59, 130, 246)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4
                        }]
                    },
                    options: { animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        },
                        interaction: {
                            intersect: false,
                            mode: 'index'
                        }
                    }
                });
                console.log('Daily chart created successfully');
            } catch (error) {
                console.error('Error in updateDailyChart:', error);
            } finally {
                this.isUpdatingDailyChart = false;
            }
        },

        updateWeeklyChart(data) {
            console.log('updateWeeklyChart called with data:', data);
            const canvas = document.getElementById('weeklyChart');
            if (!canvas) {
                console.error('Weekly chart canvas not found!');
                return;
            }
            if (this.weeklyChart) {
                try { this.weeklyChart.destroy(); } catch (e) {}
                this.weeklyChart = null;
            }

            // Handle empty data
            if (!data.labels || data.labels.length === 0) {
                console.log('Weekly chart has no data, showing empty state');
                this.weeklyChart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: ['No Data'],
                        datasets: [{
                            label: 'Total Visits',
                            data: [0],
                            backgroundColor: 'rgba(156, 163, 175, 0.7)',
                            borderRadius: 4
                        }]
                    },
                    options: { animation: false,
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { padding: 20 }
                            },
                            title: {
                                display: true,
                                text: 'No data available for this date range',
                                color: '#6b7280'
                            }
                        },
                        scales: {
                            y: { beginAtZero: true }
                        }
                    }
                });
                return;
            }

            console.log('Creating weekly chart with', data.labels.length, 'data points');
            this.weeklyChart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Total Visits',
                        data: data.recording_count || data.male,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: { animation: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { padding: 20 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        }
    };
}
</script>
@endpush
@endsection
