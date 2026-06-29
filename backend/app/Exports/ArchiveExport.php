<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ArchiveExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Builder $query
    ) {}

    public function query(): Builder
    {
        return $this->query->with('category:id,name', 'partner:id,name');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Registration No',
            'Category',
            'Partner',
            'Validity From',
            'Validity To',
            'Updated At',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->registration_number ?? '',
            $row->category?->name ?? '',
            $row->partner?->name ?? '',
            $row->validity_from?->format('Y-m-d') ?? '',
            $row->validity_to?->format('Y-m-d') ?? '',
            $row->updated_at?->format('Y-m-d H:i') ?? '',
        ];
    }
}
