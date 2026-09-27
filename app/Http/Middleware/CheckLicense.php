<?php

namespace App\Http\Middleware;

use App\Support\License;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * بعد انتهاء الاشتراك وفترة السماح: أي إضافة/تعديل/مسح في اللوحة بيترفض
 * (العرض والطباعة والتصدير شغالين عادي عشان العميل مايخسرش بياناته).
 */
class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && License::readOnly()
            && ! $request->routeIs('admin.locale.switch', 'admin.notifications.seen', 'admin.dashboard.layout')) {
            $msg = License::notice();
            if ($request->expectsJson()) {
                abort(403, $msg);
            }

            return back(fallback: route('admin.dashboard'))->with('error', $msg);
        }

        return $next($request);
    }
}
