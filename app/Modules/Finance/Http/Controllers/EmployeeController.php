<?php

namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Finance\Models\Employee;
use App\Modules\Media\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public const POSITIONS = ['Manager', 'Assistant Manager', 'Executive', 'Accountant', 'Sales', 'Staff', 'Others'];

    public function __construct(private MediaService $mediaService) {}

    public function index(Request $request): View
    {
        $status = $request->string('status')->value() ?: 'active';
        $employees = Employee::query()->with('image')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->string('q')->trim()->value(), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%{$t}%")->orWhere('phone', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page') ?: 50)->withQueryString();

        return view('studio.accounts.salary.index', [
            'employees' => $employees,
            'status' => $status,
            'totalSalary' => (float) Employee::where('status', 'active')->sum('salary'),
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
        ]);
    }

    public function create(): View
    {
        return view('studio.accounts.salary.form', ['employee' => new Employee(['status' => 'active']), 'positions' => self::POSITIONS, 'mediaUrl' => fn ($m) => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['image_id'] = $this->uploadImage($request, $data['name']);
        $data['cv_path'] = $this->uploadCv($request);
        Employee::create($data);

        return redirect()->route('accounts.salary.index')->with('success', 'Member added.');
    }

    public function edit(Employee $employee): View
    {
        return view('studio.accounts.salary.form', [
            'employee' => $employee->load('image'),
            'positions' => self::POSITIONS,
            'mediaUrl' => fn ($m) => $m ? $this->mediaService->url($m) : null,
        ]);
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validated($request);
        if ($id = $this->uploadImage($request, $data['name'])) {
            $data['image_id'] = $id;
        }
        if ($cv = $this->uploadCv($request)) {
            $data['cv_path'] = $cv;
        }
        $employee->update($data);

        return redirect()->route('accounts.salary.index')->with('success', 'Member updated.');
    }

    public function toggle(Request $request, Employee $employee): JsonResponse|RedirectResponse
    {
        $employee->update(['status' => $employee->isActive() ? 'inactive' : 'active']);

        return $request->expectsJson()
            ? response()->json(['message' => 'Status updated.', 'status' => $employee->status])
            : back()->with('success', 'Status updated.');
    }

    public function destroy(Request $request, Employee $employee): JsonResponse|RedirectResponse
    {
        $employee->delete();

        return $request->expectsJson()
            ? response()->json(['message' => 'Member deleted.'])
            : back()->with('success', 'Member deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'joining_date' => ['nullable', 'date'],
            'name' => ['required', 'string', 'max:150'],
            'position' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'office_phone' => ['nullable', 'string', 'max:40'],
            'designation' => ['nullable', 'string', 'max:500'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', 'in:active,inactive'],
            'image' => ['nullable', 'image', 'max:6144'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx,zip', 'max:10240'],
        ]);
        $data['salary'] = $data['salary'] ?? 0;
        unset($data['image'], $data['cv']);

        return $data;
    }

    private function uploadImage(Request $request, string $alt): ?int
    {
        return $request->hasFile('image')
            ? $this->mediaService->upload($request->file('image'), $alt, null, 'employee')->id
            : null;
    }

    private function uploadCv(Request $request): ?string
    {
        return $request->hasFile('cv')
            ? $request->file('cv')->store('employee-cv', 'public')
            : null;
    }
}
