<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\ContractStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * بوابة العميل: العميل بيشوف عقوده وجدول أقساطه ودفعاته (قراءة بس).
 * كل الاستعلامات مقفولة على customer_id بتاع اليوزر - مفيش طريقة يشوف بيانات عميل تاني.
 */
class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $customerId = $this->customerId($request);
        $today = today()->toDateString();

        $contracts = $this->contracts($customerId)->get();
        $active = $contracts->where('status_code', '!=', 'cancelled');

        $open = DB::table('installments')
            ->join('sales_contracts', 'sales_contracts.id', '=', 'installments.sales_contract_id')
            ->where('sales_contracts.customer_id', $customerId)
            ->whereNull('installments.deleted_at')->whereNull('sales_contracts.deleted_at')
            ->where('installments.is_paid', false)
            ->where('sales_contracts.status_id', '!=', (int) ContractStatus::id('cancelled'))
            ->select('installments.*', 'sales_contracts.contract_number')
            ->orderBy('installments.due_date')
            ->get();

        $overdue = $open->filter(fn ($i) => $i->due_date < $today);

        return view('customer.dashboard', [
            'customer'  => DB::table('customers')->where('id', $customerId)->first(),
            'contracts' => $contracts,
            'upcoming'  => $open->filter(fn ($i) => $i->due_date >= $today)->take(6),
            'overdue'   => $overdue,
            'summary'   => [
                'total'     => (float) $active->sum('total_amount'),
                'paid'      => (float) $active->sum('paid_amount'),
                'remaining' => (float) $active->sum('remaining_amount'),
                'overdue'   => (float) $overdue->sum(fn ($i) => (float) $i->amount - (float) $i->paid_amount),
            ],
            'company'   => \App\Support\Company::info(),
        ]);
    }

    public function contract(Request $request, int $contract): View
    {
        $customerId = $this->customerId($request);

        $row = $this->contracts($customerId)->where('sales_contracts.id', $contract)->first();
        abort_unless($row, 404);

        $payments = DB::table('payments')
            ->where('payable_type', (new \App\Models\SalesContract)->getMorphClass())
            ->where('payable_id', $row->id)
            ->whereNull('payments.deleted_at')->where('payments.status', 'completed')
            ->leftJoin('payment_methods', 'payment_methods.id', '=', 'payments.payment_method_id')
            ->select('payments.payment_reference', 'payments.amount', 'payments.payment_date', 'payment_methods.name_ar as method_name')
            ->orderBy('payments.payment_date')->orderBy('payments.id')
            ->get();

        return view('customer.contract', [
            'contract'     => $row,
            'installments' => DB::table('installments')->where('sales_contract_id', $row->id)
                ->whereNull('deleted_at')->orderBy('installment_sequence')->get(),
            'payments'     => $payments,
            'company'      => \App\Support\Company::info(),
        ]);
    }

    private function customerId(Request $request): int
    {
        $id = (int) $request->user()->customer_id;
        abort_if(! $id, 403, 'الحساب ده مش مربوط بملف عميل. كلّم المعرض.');

        return $id;
    }

    private function contracts(int $customerId)
    {
        return DB::table('sales_contracts')
            ->where('sales_contracts.customer_id', $customerId)
            ->whereNull('sales_contracts.deleted_at')
            ->leftJoin('vehicles', 'vehicles.id', '=', 'sales_contracts.vehicle_id')
            ->leftJoin('car_brands', 'car_brands.id', '=', 'vehicles.brand_id')
            ->leftJoin('car_models', 'car_models.id', '=', 'vehicles.model_id')
            ->leftJoin('contract_statuses', 'contract_statuses.id', '=', 'sales_contracts.status_id')
            ->select('sales_contracts.id', 'sales_contracts.contract_number', 'sales_contracts.contract_date', 'sales_contracts.payment_type',
                'sales_contracts.total_amount', 'sales_contracts.paid_amount', 'sales_contracts.remaining_amount', 'sales_contracts.delivery_date',
                'sales_contracts.ownership_transferred_at',
                'vehicles.manufacturing_year', 'vehicles.color', 'vehicles.plate_number', 'vehicles.chassis_number', 'vehicles.images',
                'car_brands.name_ar as brand_name', 'car_models.name_ar as model_name',
                'contract_statuses.code as status_code', 'contract_statuses.name_ar as status_name')
            ->orderByDesc('sales_contracts.contract_date')->orderByDesc('sales_contracts.id');
    }
}
