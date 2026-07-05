<?php

namespace App\Services\Messenger;

use App\Models\MessengerQuickReply;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MessengerQuickReplyImportService
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function importFromSpreadsheet(string $filePath, int $companyId): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

        $imported = 0;
        $skipped = 0;
        $sortOrder = (int) MessengerQuickReply::query()
            ->where('company_id', $companyId)
            ->max('sort_order');

        foreach ($rows as $index => $row) {
            $title = trim((string) ($row[0] ?? ''));
            $body = trim((string) ($row[1] ?? ''));

            if ($title === '' || $body === '') {
                $skipped++;

                continue;
            }

            if ($index === 0 && $this->looksLikeHeader($title, $body)) {
                continue;
            }

            $sortOrder++;

            MessengerQuickReply::query()->create([
                'company_id' => $companyId,
                'type' => 'text',
                'title' => mb_substr($title, 0, 120),
                'body' => mb_substr($body, 0, 2000),
                'sort_order' => $sortOrder,
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    public function createSampleSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Шаблоны');
        $sheet->fromArray([
            ['Название', 'Текст'],
            ['привет', 'Здравствуйте! Чем могу помочь?'],
            ['компофф', 'Пример длинного шаблона с условиями курса...'],
        ]);

        $sheet->getColumnDimension('A')->setWidth(24);
        $sheet->getColumnDimension('B')->setWidth(60);

        return $spreadsheet;
    }

    public function writeSampleToPath(string $path): void
    {
        $writer = new Xlsx($this->createSampleSpreadsheet());
        $writer->save($path);
    }

    protected function looksLikeHeader(string $title, string $body): bool
    {
        $titleLower = mb_strtolower($title);
        $bodyLower = mb_strtolower($body);

        return in_array($titleLower, ['название', 'title', 'имя', 'name'], true)
            || in_array($bodyLower, ['текст', 'text', 'сообщение', 'body'], true);
    }
}
