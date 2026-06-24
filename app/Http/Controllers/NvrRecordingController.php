<?php

namespace App\Http\Controllers;

use App\Models\NvrRecording;
use App\Services\NvrAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NvrRecordingController extends Controller
{
    public function __construct(private NvrAuthService $nvrAuth)
    {
        //
    }

    /**
     * Authenticate with NVR and store access token
     */
    public function authenticate(Request $request)
    {
        try {
            $tokenData = $this->nvrAuth->authenticate();

            if (!isset($tokenData['access_token'])) {
                throw new \Exception("No access token in response");
            }

            // Store token in cache for 1 hour
            Cache::put('nvr_access_token', $tokenData['access_token'], now()->addHour());

            return response()->json([
                'message' => 'Authenticated successfully',
                'access_token' => $tokenData['access_token'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Authentication failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Fetch and store recordings from NVR
     */
    public function fetchAndStore(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $date = $request->input('date');

            // Get access token from cache, or authenticate if not present
            $token = Cache::get('nvr_access_token');
            if (!$token) {
                try {
                    $tokenData = $this->nvrAuth->authenticate();
                    if (!isset($tokenData['access_token'])) {
                        throw new \Exception("Authentication failed: No access token in response");
                    }
                    $token = $tokenData['access_token'];
                    Cache::put('nvr_access_token', $token, now()->addHour());
                } catch (\Exception $authError) {
                    return response()->json([
                        'error' => 'Authentication failed: ' . $authError->getMessage(),
                    ], 401);
                }
            }

            // Start recording search process
            $process = $this->nvrAuth->startRecordingSearch($token);

            // Wait a moment for the process to be ready
            usleep(500000); // 0.5 second delay

            // Get recording results
            $dateNum = str_replace('-', '', $date);
            $results = $this->nvrAuth->getRecordingResults($token, $process, $dateNum, 0, 50);

            if (isset($results['error_code']) && $results['error_code'] !== 0) {
                throw new \Exception("NVR API error: {$results['error_code']}");
            }

            $recordings = $results['results'] ?? [];
            $recordingCount = count($recordings);

            // Store recordings in database (merges consecutive into visits)
            NvrRecording::storeFromNvrData($recordings, $process);

            // Get hourly data for response
            $hourlyData = NvrRecording::getHourlyRecordings($date);
            $visitCount = array_sum($hourlyData->pluck('recording_count')->toArray());

            return response()->json([
                'message' => 'NVR visits fetched and stored successfully',
                'total_recordings' => $recordingCount,
                'total_visits' => $visitCount,
                'process_id' => $process,
                'hourly_data' => $hourlyData,
                'authenticated' => Cache::get('nvr_access_token') ? true : false,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch recordings: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get hourly recording data for a specific date
     */
    public function hourly(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date', $startDate);

            $data = NvrRecording::getHourlyRecordingsForRange($startDate, $endDate);

            return response()->json([
                'labels' => $data->pluck('recording_hour')->map(fn($h) => sprintf('%02d:00', $h)),
                'recording_count' => $data->pluck('recording_count')->map(fn($c) => (int)$c),
                'total_duration' => $data->pluck('total_duration')->map(fn($d) => (int)$d),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get hourly data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get daily recording data for a date range
     */
    public function daily(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date', $startDate);

            $data = NvrRecording::getDailyRecordings($startDate, $endDate);

            // Fill in missing dates with zeros
            $endDateTime = new \DateTime($endDate);
            $endDateTime->modify('+1 day');
            $period = new \DatePeriod(
                new \DateTime($startDate),
                new \DateInterval('P1D'),
                $endDateTime
            );

            $dateMap = [];
            foreach ($data as $record) {
                $dateMap[$record->recording_date->format('Y-m-d')] = $record;
            }

            $labels = [];
            $totals = [];
            $recordingCounts = [];
            $durations = [];

            foreach ($period as $day) {
                $dateStr = $day->format('Y-m-d');
                $labels[] = $day->format('M d');
                $totals[] = isset($dateMap[$dateStr]) ? (int)$dateMap[$dateStr]->recording_count : 0;
                $recordingCounts[] = isset($dateMap[$dateStr]) ? (int)$dateMap[$dateStr]->recording_count : 0;
                $durations[] = isset($dateMap[$dateStr]) ? (int)$dateMap[$dateStr]->total_duration : 0;
            }

            return response()->json([
                'labels' => $labels,
                'total' => $totals,
                'recording_count' => $recordingCounts,
                'total_duration' => $durations,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get daily data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get weekly recording data for a date range
     */
    public function weekly(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date', now()->addDays(30)->format('Y-m-d'));

            $records = NvrRecording::getWeeklyRecordings($startDate, $endDate);
            $counts = $records->pluck('recording_count')->map(fn($c) => (int)$c);

            return response()->json([
                'labels' => $records->pluck('week')->map(function($week) {
                    $year = (int)substr($week, 0, 4);
                    $weekNum = (int)substr($week, 4);
                    $date = \Carbon\Carbon::create($year, 1, 1)->addWeeks($weekNum - 1)->startOfWeek();
                    return 'Week of ' . $date->format('M d');
                }),
                'recording_count' => $counts,
                'total_duration' => $records->pluck('total_duration')->map(fn($d) => (int)$d),
                'male' => $counts, // NVR doesn't track gender, using total as male
                'female' => array_fill(0, count($counts), 0), // NVR doesn't track gender
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get weekly data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get recording statistics for a date range
     */
    public function stats(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date', $startDate);

            $stats = NvrRecording::getRecordingStats($startDate, $endDate);

            // Add male/female breakdown (NVR doesn't track gender, so all as male)
            $total = $stats['total_recordings'] ?? 0;
            return response()->json([
                'male' => $total,
                'female' => 0,
                'total' => $total,
                'total_duration' => (int)($stats['total_duration'] ?? 0),
                'avg_duration' => $stats['avg_duration'] ?? 0,
                'median_duration' => $stats['median_duration'] ?? 0,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get duration categories for a date range
     */
    public function durationCategories(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
        ]);

        try {
            $startDate = $request->input('start_date');
            $endDate = $request->input('end_date', $startDate);

            $recordings = NvrRecording::dateRange($startDate, $endDate)
                ->select('duration')
                ->get();

            $short = 0; // < 60 seconds
            $medium = 0; // 60-300 seconds (1-5 min)
            $long = 0; // > 300 seconds (5+ min)

            foreach ($recordings as $recording) {
                if ($recording->duration < 60) {
                    $short++;
                } elseif ($recording->duration <= 300) {
                    $medium++;
                } else {
                    $long++;
                }
            }

            return response()->json([
                'labels' => ['Short (<1 min)', 'Medium (1-5 min)', 'Long (5+ min)'],
                'data' => [$short, $medium, $long],
                'total' => $recordings->count()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get duration categories: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get peak hour for a specific date
     */
    public function peakHour(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
        ]);

        try {
            $date = $request->input('date');
            $hourlyData = NvrRecording::getHourlyRecordings($date);

            if ($hourlyData->isEmpty()) {
                return response()->json(['message' => 'No data found for this date'], 404);
            }

            $peak = $hourlyData->sortByDesc('recording_count')->first();

            return response()->json([
                'hour' => sprintf('%02d:00', $peak->recording_hour),
                'total_count' => (int)$peak->recording_count,
                'total_duration' => (int)$peak->total_duration,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to get peak hour: ' . $e->getMessage(),
            ], 500);
        }
    }
}
