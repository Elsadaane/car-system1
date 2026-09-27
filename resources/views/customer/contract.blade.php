@extends('customer.layouts.app')

@section('title', 'عقد '.$contract->contract_number)

@php
    use App\Support\Money;
    $today = today()->toDateString();
    $statusLabel = ['paid' => 'مدفوع', 'partially_paid' => 'مدفوع جزئياً', 'overdue' => 'متأخر', 'pending' => 'لم يستحق', 'cancelled' => 'ملغي'];
    $statusStyle = ['paid' => 'bg-emerald-50 text-emerald-700', 'partially_paid' => 'bg-amber-50 text-amber-700', 'overdue' => 'bg-rose-50 text-rose-700', 'pending' => 'bg-slate-100 text-slate-600', 'cancelled' => 'bg-slate-100 text-slate-400'];
@endphp

@section('content')
    <a href="{{ route('customer.dashboard') }}" class="text-sm text-blue-600 hover:underline">← رجوع للرئيسية</a>

    <div class="mt-3 bg-white rounded-2xl shadow-sm p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800">{{ trim($contract->brand_name.' '.$contract->model_name.' '.$contract->manufacturing_year) }}</h1>
                <p class="text-sm text-slate-500 mt-1">عقد {{ $contract->contract_number }} بتاريخ {{ $contract->contract_date }}</p>
            </div>
            <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $contract->status_name }}</span>
        </div>

        <dl class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
            <div><dt class="text-slate-500 text-xs">اللون</dt><dd class="font-bold text-slate-700">{{ $contract->color ?: '—' }}</dd></div>
            <div><dt class="text-slate-500 text-xs">رقم اللوحة</dt><dd class="font-bold text-slate-700">{{ $contract->plate_number ?: '—' }}</dd></div>
            <div><dt class="text-slate-500 text-xs">التسليم</dt><dd class="font-bold text-slate-700">{{ $contract->delivery_date ?: 'لسه' }}</dd></div>
            <div><dt class="text-slate-500 text-xs">نقل الملكية</dt><dd class="font-bold text-slate-700">{{ $contract->ownership_transferred_at ?: 'لسه' }}</dd></div>
        </dl>

        <div class="mt-5 grid grid-cols-3 gap-3 text-center">
            <div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">الإجمالي</p><p class="font-bold text-slate-800">{{ Money::format($contract->total_amount) }}</p></div>
            <div class="rounded-xl bg-emerald-50 p-3"><p class="text-xs text-emerald-700">المدفوع</p><p class="font-bold text-emerald-700">{{ Money::format($contract->paid_amount) }}</p></div>
            <div class="rounded-xl bg-amber-50 p-3"><p class="text-xs text-amber-700">المتبقي</p><p class="font-bold text-amber-700">{{ Money::format($contract->remaining_amount) }}</p></div>
        </div>
    </div>

    @if ($installments->isNotEmpty())
        <section class="mt-6">
            <h2 class="font-bold text-slate-800 mb-3">جدول الأقساط</h2>
            <div class="bg-white rounded-2xl shadow-sm overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs">
                        <tr>
                            <th class="p-3 text-right">#</th>
                            <th class="p-3 text-right">تاريخ الاستحقاق</th>
                            <th class="p-3 text-right">القسط</th>
                            <th class="p-3 text-right">المدفوع</th>
                            <th class="p-3 text-right">المتبقي</th>
                            <th class="p-3 text-right">الحالة</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($installments as $i)
                            @php $st = (! $i->is_paid && $i->due_date < $today && $i->status !== 'cancelled') ? 'overdue' : $i->status; @endphp
                            <tr>
                                <td class="p-3">{{ $i->installment_sequence }}</td>
                                <td class="p-3">{{ $i->due_date }}</td>
                                <td class="p-3">{{ Money::format($i->amount, false) }}</td>
                                <td class="p-3 text-emerald-600">{{ Money::format($i->paid_amount, false) }}</td>
                                <td class="p-3">{{ Money::format(max(0, (float) $i->amount - (float) $i->paid_amount), false) }}</td>
                                <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs font-bold {{ $statusStyle[$st] ?? 'bg-slate-100 text-slate-600' }}">{{ $statusLabel[$st] ?? $st }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="mt-6">
        <h2 class="font-bold text-slate-800 mb-3">المدفوعات</h2>
        <div class="bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
            @forelse ($payments as $p)
                <div class="flex items-center justify-between p-4 text-sm">
                    <div>
                        <p class="font-bold text-slate-700">{{ \Illuminate\Support\Carbon::parse($p->payment_date)->toDateString() }}</p>
                        <p class="text-xs text-slate-500">{{ $p->method_name ?: '—' }} · {{ $p->payment_reference }}</p>
                    </div>
                    <p class="font-bold text-emerald-600">{{ Money::format($p->amount) }}</p>
                </div>
            @empty
                <p class="p-6 text-center text-sm text-slate-500">مفيش مدفوعات مسجلة.</p>
            @endforelse
        </div>
    </section>
@endsection
