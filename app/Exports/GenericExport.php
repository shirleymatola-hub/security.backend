<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Exportação Excel genérica (cabeçalhos + linhas) usada pelo painel Super Admin. */
class GenericExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    private array $headers;

    private array $rows;

    public function __construct(array $data)
    {
        $this->headers = $data['headers'];
        $this->rows = $data['rows'];
    }

    public function collection()
    {
        return collect($this->rows);
    }

    public function headings(): array
    {
        return $this->headers;
    }

    public function map($row): array
    {
        return array_values($row);
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 11]],
        ];
    }
}
