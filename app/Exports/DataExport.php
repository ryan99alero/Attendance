<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;

class DataExport implements FromQuery, WithHeadings
{
    use Exportable;

    protected string $model;

    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * @return Builder
     */
    public function query(): Builder
    {
        // Use Eloquent Builder for the query
        $modelInstance = new $this->model;
        return $modelInstance->newQuery();
    }

    /**
     * Define the headers for the exported file.
     *
     * @return array
     */
    public function headings(): array
    {
        // Get the table columns dynamically
        $modelInstance = new $this->model;
        return $modelInstance->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($modelInstance->getTable());
    }
}
