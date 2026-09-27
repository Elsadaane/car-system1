<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * اشتراك النسخة (LICENSE_EXPIRES في .env):
 *   - قبل الانتهاء بـ 14 يوم: تنبيه في لوحة الإدارة
 *   - بعد الانتهاء: فترة سماح (LICENSE_GRACE_DAYS - الافتراضي 7 أيام) والتنبيه شغال
 *   - بعد فترة السماح: اللوحة "قراءة بس" - العميل يشوف بياناته بس مايضيفش ولا يعدّل
 * من غير LICENSE_EXPIRES النسخة مفتوحة على طول.
 */
class License
{
    public const WARN_DAYS = 14;

    public static function expiresAt(): ?Carbon
    {
        $value = config('laroville.license.expires_at');
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /** أيام متبقية (بالسالب لو انتهت) - null لو مفيش تاريخ */
    public static function daysLeft(): ?int
    {
        $at = self::expiresAt();

        return $at ? (int) today()->diffInDays($at->copy()->startOfDay(), false) : null;
    }

    public static function readOnly(): bool
    {
        $days = self::daysLeft();

        return $days !== null && $days < -max(0, (int) config('laroville.license.grace_days', 7));
    }

    /** رسالة التنبيه اللي بتظهر فوق الصفحات (null = مفيش تنبيه) */
    public static function notice(): ?string
    {
        $days = self::daysLeft();
        if ($days === null || $days > self::WARN_DAYS) {
            return null;
        }

        return match (true) {
            self::readOnly() => 'انتهى اشتراك النسخة - اللوحة دلوقتي للعرض بس. جدّد الاشتراك عشان تقدر تضيف وتعدّل.',
            $days < 0        => 'انتهى اشتراك النسخة من '.abs($days).' يوم. جدّد قبل ما اللوحة تتحوّل للعرض بس.',
            $days === 0      => 'اشتراك النسخة بينتهي النهارده.',
            default          => 'اشتراك النسخة بينتهي بعد '.$days.' يوم ('.self::expiresAt()->toDateString().').',
        };
    }
}
