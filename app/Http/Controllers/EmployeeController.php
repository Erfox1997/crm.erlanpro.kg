<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Position;
use App\Models\User;
use App\Services\Employee\EmployeeImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeController extends Controller
{
    public function __construct(
        private EmployeeImportService $importService,
    ) {}

    public function index(Request $request): Response
    {
        $companyId = (int) $request->user()->company_id;

        $employees = User::query()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNull('company_role')
                    ->orWhere('company_role', '!=', 'owner');
            })
            ->with('position:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position_id' => $user->position_id,
                'position_name' => $user->position?->name,
                'created_at' => $user->created_at?->format('d.m.Y'),
            ]);

        $positions = Position::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $company = Company::query()->with('tariff')->findOrFail($companyId);
        $maxEmployees = $company->maxEmployees();
        $employeesUsed = $employees->count();

        return Inertia::render('Employees/Index', [
            'employees' => $employees,
            'positions' => $positions,
            'limits' => [
                'max_employees' => $maxEmployees,
                'employees_used' => $employeesUsed,
                'can_add' => $maxEmployees === null || $employeesUsed < $maxEmployees,
            ],
            'pageTitle' => 'Сотрудники',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::query()->with('tariff')->findOrFail($companyId);

        if (! $company->canAddEmployees()) {
            return back()->withErrors([
                'employee' => __('Лимит сотрудников по тарифу исчерпан (:max).', [
                    'max' => $company->maxEmployees(),
                ]),
            ]);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::defaults()],
            'position_id' => [
                'required',
                Rule::exists('positions', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        User::query()->create([
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'company_id' => $companyId,
            'company_role' => 'employee',
            'position_id' => (int) $validated['position_id'],
            'email_verified_at' => now(),
        ]);

        return back()->with('success', __('Сотрудник создан.'));
    }

    public function update(Request $request, User $employee): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->assertManageableEmployee($employee, $companyId);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->id),
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'position_id' => [
                'required',
                Rule::exists('positions', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $payload = [
            'name' => trim($validated['name']),
            'email' => $validated['email'],
            'position_id' => (int) $validated['position_id'],
        ];

        if (! empty($validated['password'])) {
            $payload['password'] = $validated['password'];
        }

        $employee->update($payload);

        return back()->with('success', __('Сотрудник обновлён.'));
    }

    public function destroy(Request $request, User $employee): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;
        $this->assertManageableEmployee($employee, $companyId);

        if ($employee->id === $request->user()->id) {
            return back()->withErrors([
                'employee' => __('Нельзя удалить собственный аккаунт.'),
            ]);
        }

        $employee->delete();

        return back()->with('success', __('Сотрудник удалён.'));
    }

    public function import(Request $request): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:5120',
        ]);

        $path = $validated['file']->getRealPath();
        if (! is_string($path) || $path === '') {
            return back()->withErrors(['file' => __('Не удалось прочитать файл.')]);
        }

        try {
            $result = $this->importService->importFromSpreadsheet($path, $companyId);
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        if ($result['imported'] === 0) {
            $message = __('Не найдено строк для импорта. Проверьте формат: ФИО, Почта, Пароль, Должность.');

            if ($result['errors'] !== []) {
                $message .= ' '.$result['errors'][0];
            }

            return back()->withErrors(['file' => $message]);
        }

        $success = __('Импортировано сотрудников: :count', ['count' => $result['imported']]);

        if ($result['errors'] !== []) {
            $success .= ' '.__('Пропущено с ошибками: :count', ['count' => $result['skipped']]);
        }

        return back()->with('success', $success);
    }

    public function sample(): StreamedResponse
    {
        return response()->streamDownload(function () {
            $path = tempnam(sys_get_temp_dir(), 'emp_sample_');
            if ($path === false) {
                throw new \RuntimeException(__('Не удалось создать файл.'));
            }

            try {
                $this->importService->writeSampleToPath($path);
                echo (string) file_get_contents($path);
            } finally {
                @unlink($path);
            }
        }, 'employees-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function assertManageableEmployee(User $employee, int $companyId): void
    {
        abort_unless($employee->company_id === $companyId, 403);
        abort_if($employee->company_role === 'owner', 403);
        abort_if($employee->is_platform_admin, 403);
    }
}
