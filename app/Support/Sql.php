<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * دوال SQL بتختلف من قاعدة بيانات للتانية (MySQL / SQLite).
 * السيستم على الإنتاج شغال MySQL، بس التجارب والـ CI بيشتغلوا SQLite -
 * فأي حاجة من دي بتتكتب من هنا عشان الصفحة متقعش على أي واحدة فيهم.
 */
class Sql
{
    public static function sqlite(): bool
    {
        return DB::connection()->getDriverName() === 'sqlite';
    }

    /** دمج نصوص/أعمدة: concat('PAY-', r.year) */
    public static function concat(string ...$parts): string
    {
        return self::sqlite() ? '('.implode(' || ', $parts).')' : 'concat('.implode(', ', $parts).')';
    }

    /** رقم بصفرين من الشمال (شهر 3 ← 03) */
    public static function pad2(string $column): string
    {
        return self::sqlite() ? "printf('%02d', {$column})" : "lpad({$column}, 2, '0')";
    }

    /** عدد الأيام من $from لحد $to (بالسالب لو $to قبل $from) */
    public static function daysBetween(string $from, string $to): string
    {
        return self::sqlite()
            ? "cast(julianday({$to}) - julianday({$from}) as integer)"
            : "datediff({$to}, {$from})";
    }

    /** الأكبر بين قيمتين */
    public static function greatest(string $a, string $b): string
    {
        return self::sqlite() ? "max({$a}, {$b})" : "greatest({$a}, {$b})";
    }

    /** تاريخ + عدد أيام من عمود */
    public static function addDays(string $date, string $daysColumn): string
    {
        return self::sqlite()
            ? "date({$date}, '+' || {$daysColumn} || ' days')"
            : "DATE_ADD({$date}, INTERVAL {$daysColumn} DAY)";
    }
}
