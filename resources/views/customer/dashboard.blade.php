@extends('customer.layouts.app')

@section('title', 'الرئيسية')

@php
    use App\Support\Money;
    use App\Support\Media;
    $phone = preg_replace('/\D+/', '', (string) ($company->phone ?? ''));
@endphp

@section('content')
    <div class="bg-white rounded-2xl shadow-sm p-6 sm:p-8">
        <h1 class="text-xl font-bold text-slate-800">أهلاً {{ $customer->name_ar ?? auth()->user()->name }}</h1>
        <p class="text-slate-500 mt-1 text-sm">هنا تقدر تتابع عقودك وأقساطك ومدفوعاتك أول بأول.</p>
    </div>

    @if ($overdue->isNotEmpty())
        <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 p-5 text-rose-800">
            <p class="font-bold">عليك {{ $overdue->count() }} {{ $overdue->count() === 1 ? 'قسط متأخر' : 'أقساط متأخرة' }} بإجمالي {{ Money::format($summary['overdue']) }}</p>
            <p class="text-sm mt-1">برجاء السداد في أقرب وقت أو التواصل مع المعرض.</p>
            @if ($phone)
                <a href="https://wa.me/{{ $phone }}" target="_blank" rel="noopener" class="inline-block mt-3 rounded-xl bg-emerald-600 px-4 py-2 text-sm font-bold text-white hover:bg-emerald-700">تواصل واتساب</a>
            @endif
        </div>
    @endif

    <div class="mt-5 grid grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ([
            ['إجمالي العقود', $summary['total'], 'text-slate-800'],
            ['المدفوع', $summary['paid'], 'text-emerald-600'],
            ['المتبقي', $summary['remaining'], 'text-amber-600'],
            ['المتأخر', $summary['overdue'], $summary['overdue'] > 0 ? 'text-rose-600' : 'text-slate-400'],
        ] as [$label, $value, $color])
            <div class="bg-white rounded-2xl shadow-sm p-5">
                <p class="text-xs text-slate-500">{{ $label }}</p>
                <p class="mt-1 text-lg font-bold {{ $color }}">{{ Money::format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <section class="lg:col-span-2">
            <h2 class="font-bold text-slate-800 mb-3">عقودي</h2>
            <div class="space-y-3">
                @forelse ($contracts as $c)
                    @php $img = Media::list($c->images)[0] ?? null; @endphp
                    <a href="{{ route('customer.contracts.show', $c->id) }}" class="flex gap-4 bg-white rounded-2xl shadow-sm p-4 hover:ring-2 hover:ring-blue-100 transition">
                        <div class="h-20 w-28 shrink-0 overflow-hidden rounded-xl bg-slate-100">
                            @if ($img)
                                <img src="{{ Media::url($img) }}" alt="" class="h-full w-full object-cover" loading="lazy">
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="font-bold text-slate-800">{{ trim($c->brand_name.' '.$c->model_name.' '.$c->manufacturing_year) }}</p>
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-bold',
                                    'bg-emerald-50 text-emerald-700' => $c->status_code === 'completed',
                                    'bg-rose-50 text-rose-700' => $c->status_code === 'cancelled',
                                    'bg-blue-50 text-blue-700' => ! in_array($c->status_code, ['completed', 'cancelled'], true),
                                ])>{{ $c->status_name }}</span>
                            </div>
                            <p class="text-xs text-slate-500 mt-1">عقد {{ $c->contract_number }} · {{ $c->contract_date }} · {{ $c->payment_type === 'installments' ? 'تقسيط' : 'كاش' }}</p>
                            @if ($c->status_code !== 'cancelled')
                                @php $pct = (float) $c->total_amount > 0 ? min(100, round($c->paid_amount / $c->total_amount * 100)) : 0; @endphp
                                <div class="mt-3 h-2 rounded-full bg-slate-100 overflow-hidden"><div class="h-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>
                                <p class="text-xs text-slate-500 mt-1">اتدفع {{ Money::format($c->paid_amount) }} من {{ Money::format($c->total_amount) }} ({{ $pct }}%)</p>
                            @endif
                        </div>
                    </a>
                @empty
                    <div class="bg-white rounded-2xl shadow-sm p-6 text-center text-slate-500 text-sm">مفيش عقود مسجلة على حسابك لسه.</div>
                @endforelse
            </div>
        </section>

        <section>
            <h2 class="font-bold text-slate-800 mb-3">الأقساط الجاية</h2>
            <div class="bg-white rounded-2xl shadow-sm divide-y divide-slate-100">
                @forelse ($upcoming as $i)
                    <div class="flex items-center justify-between p-4 text-sm">
                        <div>
                            <p class="font-bold text-slate-700">{{ $i->due_date }}</p>
                            <p class="text-xs text-slate-500">قسط {{ $i->installment_sequence }}/{{ $i->total_installments }} · {{ $i->contract_number }}</p>
                        </div>
                        <p class="font-bold text-slate-800">{{ Money::format((float) $i->amount - (float) $i->paid_amount) }}</p>
                    </div>
                @empty
                    <p class="p-6 text-center text-sm text-slate-500">مفيش أقساط جاية.</p>
                @endforelse
            </div>

            @if ($phone || ($company->address_ar ?? null))
                <div class="mt-6 bg-white rounded-2xl shadow-sm p-5 text-sm text-slate-600 space-y-1">
                    <p class="font-bold text-slate-800">{{ $company->name_ar ?? config('app.name') }}</p>
                    @if ($company->phone ?? null) <p dir="ltr" class="text-right">{{ $company->phone }}</p> @endif
                    @if ($company->address_ar ?? null) <p>{{ $company->address_ar }}</p> @endif
                </div>
            @endif
        </section>
    </div>
@endsection
