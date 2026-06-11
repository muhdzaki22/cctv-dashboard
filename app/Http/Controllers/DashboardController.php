<?php

namespace App\Http\Controllers;

use App\Models\NvrRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * Display the dashboard page
     */
    public function index()
    {
        // Get oldest date with actual data (first data available)
        $firstAvailableDate = NvrRecording::select('recording_date')
            ->orderBy('recording_date', 'asc')
            ->pluck('recording_date')
            ->first();

        // Get all available dates
        $availableDates = NvrRecording::select('recording_date')
            ->distinct()
            ->orderBy('recording_date', 'desc')
            ->pluck('recording_date');

        return view('dashboard', [
            'availableDates' => $availableDates,
            'defaultDate' => $firstAvailableDate ? $firstAvailableDate->format('Y-m-d') : now()->format('Y-m-d'),
        ]);
    }

    /**
     * API: Get hourly data for a specific date
     */
    public function hourly(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');
        $cacheKey = "hourly_{$date}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($date) {
            $records = FootTrafficRecord::getHourlyTotals($date);

            return [
                'labels' => $records->pluck('hour')->map(fn($h) => sprintf('%02d:00', $h)),
                'male' => $records->pluck('male_count'),
                'female' => $records->pluck('female_count'),
                'total' => $records->pluck('total_count'),
            ];
        });

        return response()->json($data);
    }

    /**
     * API: Get daily data for a date range
     */
    public function daily(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date', $startDate);

        $cacheKey = "daily_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            $records = FootTrafficRecord::getDailyTotals($startDate, $endDate);

            return [
                'labels' => $records->pluck('date')->map(fn($d) => $d->format('M d')),
                'male' => $records->pluck('male_count'),
                'female' => $records->pluck('female_count'),
                'total' => $records->pluck('total_count'),
            ];
        });

        return response()->json($data);
    }

    /**
     * API: Get weekly data for a date range
     */
    public function weekly(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date', now()->addDays(30)->format('Y-m-d'));

        $cacheKey = "weekly_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            $records = FootTrafficRecord::getWeeklyTotals($startDate, $endDate);

            return [
                'labels' => $records->pluck('week')->map(function($week) {
                    // Convert YEARWEEK format (YYYYWW) to date
                    $year = (int)substr($week, 0, 4);
                    $weekNum = (int)substr($week, 4);
                    $date = \Carbon\Carbon::create($year, 1, 1)->addWeeks($weekNum - 1)->startOfWeek();
                    return 'Week of ' . $date->format('M d');
                }),
                'male' => $records->pluck('male_count'),
                'female' => $records->pluck('female_count'),
                'total' => $records->pluck('total_count'),
            ];
        });

        return response()->json($data);
    }

    /**
     * API: Get gender statistics
     */
    public function genderStats(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date', $startDate);

        $cacheKey = "gender_{$startDate}_{$endDate}";

        $data = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($startDate, $endDate) {
            return FootTrafficRecord::getGenderStats($startDate, $endDate);
        });

        return response()->json($data);
    }

    /**
     * API: Get peak hour for a specific date
     */
    public function peakHour(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        $date = $request->input('date');

        $peak = FootTrafficRecord::forDate($date)
            ->orderByDesc('total_count')
            ->first(['hour', 'total_count', 'male_count', 'female_count']);

        if (!$peak) {
            return response()->json(['message' => 'No data found for this date'], 404);
        }

        return response()->json([
            'hour' => sprintf('%02d:00', $peak->hour),
            'total_count' => $peak->total_count,
            'male_count' => $peak->male_count,
            'female_count' => $peak->female_count,
        ]);
    }
}
