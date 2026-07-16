<?php

namespace App\Services\Employee;

use App\Models\Position;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class EmployeeImportService
{
    /**
     * @return array{imported: int, skipped: int, errors: list<string>}
     */
    public function importFromSpreadsheet(string $filePath, int $companyId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        $positionsByName = Position::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(fn (Position $position) => mb_strtolower(trim($position->name)));

        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[0] ?? ''));
            $email = trim((string) ($row[1] ?? ''));
            $password = trim((string) ($row[2] ?? ''));
            $positionName = trim((string) ($row[3] ?? ''));

            if ($index === 0 && $this->looksLikeHeader($name, $email, $password, $positionName)) {
                continue;
            }

            if ($name === '' && $email === '' && $password === '' && $positionName === '') {
                $skipped++;

                continue;
            }

            $rowNumber = $index + 1;

            $validator = Validator::make([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'position' => $positionName,
            ], [
                'name' => 'required|string|max:255',
                'email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email'),
                ],
                'password' => 'required|string|min:8|max:255',
                'position' => 'required|string|max:120',
            ]);

            if ($validator->fails()) {
                $skipped++;
                $errors[] = __('Строка :row: :message', [
                    'row' => $rowNumber,
                    'message' => $validator->errors()->first(),
                ]);

                continue;
            }

            $position = $positionsByName->get(mb_strtolower($positionName));

            if ($position === null) {
                $skipped++;
                $errors[] = __('Строка :row: должность «:name» не найдена.', [
                    'row' => $rowNumber,
                    'name' => $positionName,
                ]);

                continue;
            }

            User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'company_id' => $companyId,
                'company_role' => 'employee',
                'position_id' => $position->id,
                'email_verified_at' => now(),
            ]);

            $imported++;
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    public function createSampleSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Сотрудники');
        $sheet->fromArray([
            ['ФИО', 'Почта', 'Пароль', 'Должность'],
            ['Иванов Иван Иванович', 'ivanov@example.com', 'password123', 'Менеджер'],
            ['Петрова Анна', 'petrova@example.com', 'password123', 'Оператор'],
        ]);

        $sheet->getColumnDimension('A')->setWidth(32);
        $sheet->getColumnDimension('B')->setWidth(28);
        $sheet->getColumnDimension('C')->setWidth(18);
        $sheet->getColumnDimension('D')->setWidth(20);

        return $spreadsheet;
    }

    public function writeSampleToPath(string $path): void
    {
        $writer = new Xlsx($this->createSampleSpreadsheet());
        $writer->save($path);
    }

    protected function looksLikeHeader(string $name, string $email, string $password, string $position): bool
    {
        $nameLower = mb_strtolower($name);
        $emailLower = mb_strtolower($email);
        $passwordLower = mb_strtolower($password);
        $positionLower = mb_strtolower($position);

        return in_array($nameLower, ['фио', 'имя', 'name', 'ф.и.о.'], true)
            || in_array($emailLower, ['почта', 'email', 'e-mail', 'эл. почта'], true)
            || in_array($passwordLower, ['пароль', 'password'], true)
            || in_array($positionLower, ['должность', 'position', 'роль'], true);
    }
}
