@extends('layouts.app')

@section('title', 'CCTV Foot Traffic Dashboard')

@section('content')
<div x-data="dashboard()" x-init="init()" class="min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-to-r from-blue-600 to-blue-800 text-white shadow-lg">
        <div class="container mx-auto px-4 py-6">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">CCTV Foot Traffic Dashboard</h1>
                    <p class="text-blue-100 mt-1">Store entrance monitoring and analytics</p>
                </div>

                <!-- Date Range Selector -->
                <div class="flex flex-wrap gap-3 items-center">
                    <div>
                        <label class="text-sm text-blue-100 block mb-1">Select Date</label>
                        <input type="date" x-model="selectedDate" @change="onDateChange"
                            class="px-3 py-2 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="text-sm text-blue-100 block mb-1">End Date</label>
                        <input type="date" x-model="endDate"
                            class="px-3 py-2 rounded-lg text-gray-800 focus:ring-2 focus:ring-blue-300 focus:outline-none">
                    </div>
                    <button @click="refreshAll" :disabled="loading"
                        class="px-4 py-2 bg-blue-500 hover:bg-blue-400 rounded-lg transition mt-5">
                        <span x-show="!loading">Refresh</span>
                        <span x-show="loading">Loading...</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        <!-- Peak Hour Card -->
        <div class="mb-8" x-show="peakHour">
            <div class="bg-gradient-to-r from-emerald-500 to-emerald-600 rounded-2xl shadow-xl p-6 text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-emerald-100 text-sm uppercase tracking-wide">Peak Hour for <span x-text="formatDate(selectedDate)"></span></p>
                        <p class="text-5xl font-bold mt-2" x-text="peakHour?.hour || '--:00'"></p>
                        <p class="text-emerald-100 mt-1">
                            <span x-text="peakHour?.total_count || 0"></span> visitors
                            (<span x-text="peakHour?.male_count || 0"></span> male,
                            <span x-text="peakHour?.female_count || 0"></span> female)
                        </p>
                    </div>
                    <div class="text-6xl opacity-20">🚶</div>
                </div>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Today's Visitors</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="todayStats.total">0</p>
                    </div>
                    <div class="text-3xl opacity-20">👥</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Male Visitors</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="todayStats.male">0</p>
                    </div>
                    <div class="text-3xl opacity-20">👨</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-pink-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">Female Visitors</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="todayStats.female">0</p>
                    </div>
                    <div class="text-3xl opacity-20">👩</div>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-amber-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm">This Week</p>
                        <p class="text-3xl font-bold text-gray-800" x-text="weeklyTotal">0</p>
                    </div>
                    <div class="text-3xl opacity-20">📊</div>
                </div>
            </div>
        </div>

        <!-- Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Hourly Traffic Chart -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Hourly Traffic</h2>
                    <span class="text-sm text-gray-500" x-text="formatDate(selectedDate)"></span>
                </div>
                <div class="relative h-80">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>

            <!-- Gender Distribution -->
            <div class="bg-white rounded-xl shadow-md p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800">Gender Distribution</h2>
                    <span class="text-sm text-gray-500" x-text="formatDate(selectedDate)"></span>
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.3/dist/cdn.min.js" defer></script>
<script>
function dashboard() {
    return {
        selectedDate: '{{ $availableDates->first()?->format('Y-m-d') ?? now()->format('Y-m-d') }}',
        endDate: '',
        period: 30,
        loading: false,
        peakHour: null,
        todayStats: { male: 0, female: 0, total: 0 },
        weeklyTotal: 0,

        // Chart instances
        hourlyChart: null,
        genderChart: null,
        dailyChart: null,
        weeklyChart: null,

        init() {
            this.endDate = this.selectedDate;
            // Wait for DOM to be fully loaded before initializing charts
            this.$nextTick(() => {
                this.refreshAll();
            });
        },

        formatDate(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
        },

        async onDateChange() {
            this.endDate = this.selectedDate;
            await this.loadHourlyData();
            await this.loadPeakHour();
            await this.loadGenderStats();
        },

        setPeriod(days) {
            this.period = days;
            const end = new Date(this.selectedDate);
            const start = new Date(end);
            start.setDate(start.getDate() - days);
            this.endDate = end.toISOString().split('T')[0];
            this.loadDailyData(start.toISOString().split('T')[0], this.endDate);
        },

        async refreshAll() {
            this.loading = true;
            try {
                await Promise.all([
                    this.loadHourlyData(),
                    this.loadPeakHour(),
                    this.loadGenderStats(),
                    this.loadDailyData(),
                    this.loadWeeklyData(),
                ]);
            } finally {
                this.loading = false;
            }
        },

        async loadHourlyData() {
            try {
                const response = await fetch(`/api/dashboard/hourly?date=${this.selectedDate}`);
                const data = await response.json();
                this.updateHourlyChart(data);
            } catch (error) {
                console.error('Error loading hourly data:', error);
            }
        },

        async loadPeakHour() {
            try {
                const response = await fetch(`/api/dashboard/peak-hour?date=${this.selectedDate}`);
                if (response.ok) {
                    this.peakHour = await response.json();
                }
            } catch (error) {
                console.error('Error loading peak hour:', error);
            }
        },

        async loadGenderStats() {
            try {
                const response = await fetch(`/api/dashboard/gender-stats?start_date=${this.selectedDate}&end_date=${this.endDate}`);
                const data = await response.json();
                this.todayStats = data;
                this.updateGenderChart(data);
            } catch (error) {
                console.error('Error loading gender stats:', error);
            }
        },

        async loadDailyData(startDate, endDate) {
            if (!startDate) {
                const end = new Date(this.selectedDate);
                const start = new Date(end);
                start.setDate(start.getDate() - this.period);
                startDate = start.toISOString().split('T')[0];
                endDate = this.selectedDate;
            }
            try {
                const response = await fetch(`/api/dashboard/daily?start_date=${startDate}&end_date=${endDate}`);
                const data = await response.json();
                this.updateDailyChart(data);
            } catch (error) {
                console.error('Error loading daily data:', error);
            }
        },

        async loadWeeklyData() {
            const end = new Date();
            const start = new Date();
            start.setDate(start.getDate() - 90);
            try {
                const response = await fetch(`/api/dashboard/weekly?start_date=${start.toISOString().split('T')[0]}&end_date=${end.toISOString().split('T')[0]}`);
                const data = await response.json();
                this.weeklyTotal = data.total?.reduce((a, b) => a + b, 0) || 0;
                this.updateWeeklyChart(data);
            } catch (error) {
                console.error('Error loading weekly data:', error);
            }
        },

        updateHourlyChart(data) {
            const canvas = document.getElementById('hourlyChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (this.hourlyChart) this.hourlyChart.destroy();
            this.hourlyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Total Visitors',
                        data: data.total,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        borderRadius: 4
                    }]
                },
                options: {
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
            const canvas = document.getElementById('genderChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (this.genderChart) this.genderChart.destroy();
            this.genderChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['Male', 'Female'],
                    datasets: [{
                        data: [data.male, data.female],
                        backgroundColor: [
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)'
                        ],
                        borderColor: [
                            'rgb(59, 130, 246)',
                            'rgb(236, 72, 153)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 20 }
                        }
                    }
                }
            });
        },

        updateDailyChart(data) {
            const canvas = document.getElementById('dailyChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (this.dailyChart) this.dailyChart.destroy();
            this.dailyChart = new Chart(ctx, {
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
                options: {
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
        },

        updateWeeklyChart(data) {
            const canvas = document.getElementById('weeklyChart');
            if (!canvas) return;
            const ctx = canvas.getContext('2d');
            if (this.weeklyChart) this.weeklyChart.destroy();
            this.weeklyChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: 'Male',
                        data: data.male,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderRadius: 4
                    }, {
                        label: 'Female',
                        data: data.female,
                        backgroundColor: 'rgba(236, 72, 153, 0.7)',
                        borderRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { padding: 20 }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true },
                        x: { stacked: true }
                    }
                }
            });
        }
    };
}
</script>
@endpush
@endsection
