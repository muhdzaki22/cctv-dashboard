<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NvrRecording extends Model
{
    use HasFactory;

    protected $fillable = [
        'recording_date',
        'recording_hour',
        'start_time',
        'end_time',
        'duration',
        'process_id',
    ];

    protected $casts = [
        'recording_date' => 'date',
        'recording_hour' => 'integer',
        'start_time' => 'integer',
        'end_time' => 'integer',
        'duration' => 'integer',
    ];

    /**
     * Scope for recordings within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('recording_date', [$startDate, $endDate]);
        }
        return $query->where('recording_date', '>=', $startDate);
    }

    /**
     * Scope for specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('recording_date', $date);
    }

    /**
     * Scope for specific hour
     */
    public function scopeForHour($query, $hour)
    {
        return $query->where('recording_hour', $hour);
    }

    /**
     * Get hourly recording counts for a date
     */
    public static function getHourlyRecordings($date)
    {
        return self::forDate($date)
            ->selectRaw('
                recording_hour,
                COUNT(*) as recording_count,
                SUM(duration) as total_duration
            ')
            ->groupBy('recording_hour')
            ->orderBy('recording_hour')
            ->get();
    }

    /**
     * Get hourly recording counts aggregated across a date range
     */
    public static function getHourlyRecordingsForRange($startDate, $endDate)
    {
        return self::dateRange($startDate, $endDate)
            ->selectRaw('
                recording_hour,
                COUNT(*) as recording_count,
                SUM(duration) as total_duration
            ')
            ->groupBy('recording_hour')
            ->orderBy('recording_hour')
            ->get();
    }

    /**
     * Get daily recording counts for a date range
     */
    public static function getDailyRecordings($startDate, $endDate = null)
    {
        $query = self::dateRange($startDate, $endDate)
            ->selectRaw('
                recording_date,
                COUNT(*) as recording_count,
                SUM(duration) as total_duration
            ')
            ->groupBy('recording_date')
            ->orderBy('recording_date');

        return $query->get();
    }

    /**
     * Get weekly recording counts for a date range
     */
    public static function getWeeklyRecordings($startDate, $endDate = null)
    {
        return self::dateRange($startDate, $endDate)
            ->selectRaw('
                YEARWEEK(recording_date, 1) as week,
                COUNT(*) as recording_count,
                SUM(duration) as total_duration
            ')
            ->groupBy('week')
            ->orderBy('week')
            ->get();
    }

    /**
     * Get recording statistics for a date range
     */
    public static function getRecordingStats($startDate, $endDate = null)
    {
        $stats = self::dateRange($startDate, $endDate)
            ->selectRaw('
                COUNT(*) as total_recordings,
                SUM(duration) as total_duration,
                AVG(duration) as avg_duration
            ')
            ->first();

        // Get median duration
        $durations = self::dateRange($startDate, $endDate)
            ->orderBy('duration')
            ->pluck('duration')
            ->toArray();

        $medianDuration = 0;
        $count = count($durations);
        if ($count > 0) {
            $middle = floor($count / 2);
            if ($count % 2 == 0) {
                $medianDuration = ($durations[$middle - 1] + $durations[$middle]) / 2;
            } else {
                $medianDuration = $durations[$middle];
            }
        }

        return [
            'total_recordings' => $stats->total_recordings ?? 0,
            'total_duration' => $stats->total_duration ?? 0,
            'avg_duration' => round($stats->avg_duration ?? 0, 2),
            'median_duration' => round($medianDuration, 2),
        ];
    }

    /**
     * Store recording data from NVR API
     * Merges consecutive recordings into single visits
     */
    public static function storeFromNvrData(array $recordings, string $processId): void
    {
        // Sort recordings by start time
        usort($recordings, function($a, $b) {
            return $a['start_time'] - $b['start_time'];
        });

        // Merge consecutive recordings into visits
        $visits = [];
        foreach ($recordings as $recording) {
            if (empty($visits)) {
                // First recording starts a new visit
                $visits[] = [
                    'start_time' => $recording['start_time'],
                    'end_time' => $recording['end_time'],
                    'recordings' => 1
                ];
            } else {
                $lastVisit = &$visits[count($visits) - 1];

                // Check if this recording continues the last visit
                if ($recording['start_time'] == $lastVisit['end_time']) {
                    // Connected recording - extend the visit
                    $lastVisit['end_time'] = $recording['end_time'];
                    $lastVisit['recordings']++;
                } else {
                    // Disconnected recording - start a new visit
                    $visits[] = [
                        'start_time' => $recording['start_time'],
                        'end_time' => $recording['end_time'],
                        'recordings' => 1
                    ];
                }
            }
        }

        // Store each visit
        foreach ($visits as $visit) {
            $startTime = $visit['start_time'];
            $endTime = $visit['end_time'];
            $duration = $endTime - $startTime;

            // Convert timestamp to date and hour
            $date = date('Y-m-d', $startTime);
            $hour = (int)date('H', $startTime);

            self::updateOrCreate(
                [
                    'recording_date' => $date,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                ],
                [
                    'recording_hour' => $hour,
                    'duration' => $duration,
                    'process_id' => $processId,
                ]
            );
        }

        \Log::info('Stored NVR visits:', [
            'total_recordings' => count($recordings),
            'total_visits' => count($visits),
            'merged_count' => count($recordings) - count($visits)
        ]);
    }
}
