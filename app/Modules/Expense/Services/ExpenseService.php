<?php

namespace App\Modules\Expense\Services;

use App\Modules\Expense\Models\Expense;
use App\Modules\Expense\Models\ExpenseCategory;
use App\Modules\Finance\Services\AccountService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ExpenseService
{
    public function __construct(private AccountService $accountService) {}

    public function createExpense(array $data): Expense
    {
        $expense = Expense::create($this->payload($data));

        $this->accountService->recordExpenseDebit($expense);

        return $expense;
    }

    public function updateExpense(Expense $expense, array $data): Expense
    {
        $expense->update($this->payload($data, false));

        return $expense->refresh();
    }

    public function deleteExpense(Expense $expense): void
    {
        $expense->delete();
    }

    public function dailyExpense(?string $date = null): float
    {
        $expenseDate = Carbon::parse($date ?? now())->toDateString();

        return (float) Expense::whereDate('expense_date', $expenseDate)->sum('amount');
    }

    public function monthlyExpense(?int $year = null, ?int $month = null): float
    {
        $date = now();
        $year ??= (int) $date->year;
        $month ??= (int) $date->month;

        return (float) Expense::query()
            ->whereYear('expense_date', $year)
            ->whereMonth('expense_date', $month)
            ->sum('amount');
    }

    public function totalExpense(): float
    {
        return (float) Expense::sum('amount');
    }

    public function summary(): array
    {
        return [
            'today_expense' => $this->dailyExpense(),
            'month_expense' => $this->monthlyExpense(),
            'overall_expense' => $this->totalExpense(),
            'category_totals' => ExpenseCategory::query()
                ->withSum('expenses', 'amount')
                ->orderBy('name')
                ->get()
                ->map(fn (ExpenseCategory $category) => [
                    'category' => $category->name,
                    'amount' => (float) ($category->expenses_sum_amount ?? 0),
                ])
                ->all(),
        ];
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Expense::query()
            ->with(['category', 'staffUser'])
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('expense_category_id', $categoryId))
            ->when($filters['date_from'] ?? null, fn ($query, $dateFrom) => $query->whereDate('expense_date', '>=', $dateFrom))
            ->when($filters['date_to'] ?? null, fn ($query, $dateTo) => $query->whereDate('expense_date', '<=', $dateTo))
            ->orderByDesc('expense_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function activeCategories()
    {
        return ExpenseCategory::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->get();
    }

    protected function payload(array $data, bool $includeStaff = true): array
    {
        $payload = [
            'expense_category_id' => $data['expense_category_id'],
            'expense_date' => Carbon::parse($data['expense_date'])->toDateString(),
            'amount' => $data['amount'],
            'description' => $data['description'],
            'receipt_number' => $data['receipt_number'] ?? null,
        ];

        if ($includeStaff) {
            $payload['staff_user_id'] = $data['staff_user_id'] ?? auth()->guard('staff')->id();
        }

        return $payload;
    }
}
