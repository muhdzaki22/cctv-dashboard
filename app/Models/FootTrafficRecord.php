<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FootTrafficRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'hour',
        'male_count',
        'female_count',
        'total_count',
    ];

    protected $casts = [
        'date' => 'date',
        'hour' => 'integer',
        'male_count' => 'integer',
        'female_count' => 'integer',
        'total_count' => 'integer',
    ];

    /**
     * Scope for records within a date range
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        if ($endDate) {
            return $query->whereBetween('date', [$startDate, $endDate]);
        }
        return $query->where('date', '>=', $startDate);
    }

    /**
     * Scope for specific date
     */
    public function scopeForDate($query, $date)
    {
        return $query->where('date', $date);
    }

    /**
     * Get total counts grouped by hour for a date
     */
    public static function getHourlyTotals($date)
    {
        return self::forDate($date)
            ->orderBy('hour')
            ->get(['hour', 'male_count', 'female_count', 'total_count']);
    }

    /**
     * Get daily totals for a date range
     */
    public static function getDailyTotals($startDate, $endDate = null)
    {
        $query = self::dateRange($startDate, $endDate)
            ->selectRaw('
                date,
                SUM(male_count) as male_count,
                SUM(female_count) as female_count,
                SUM(total_count) as total_count
            ')
            ->groupBy('date')
            ->orderBy('date');

        return $query->get();
    }

    /**
     * Get weekly totals for a date range
     */
    public static function getWeeklyTotals($startDate, $endDate = null)
    {
        return self::dateRange($startDate, $endDate)
            ->selectRaw('
                YEARWEEK(date, 1) as week,
                SUM(male_count) as male_count,
                SUM(female_count) as female_count,
                SUM(total_count) as total_count
            ')
            ->groupBy('week')
            ->orderBy('week')
            ->get();
    }

    /**
     * Get gender statistics for a date range
     */
    public static function getGenderStats($startDate, $endDate = null)
    {
        $stats = self::dateRange($startDate, $endDate)
            ->selectRaw('
                SUM(male_count) as male_total,
                SUM(female_count) as female_total
            ')
            ->first();

        return [
            'male' => $stats->male_total ?? 0,
            'female' => $stats->female_total ?? 0,
            'total' => ($stats->male_total ?? 0) + ($stats->female_total ?? 0),
        ];
    }
}
