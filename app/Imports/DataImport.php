<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;

class DataImport implements ToCollection, WithHeadingRow
{
    protected string $modelClass;

    public function __construct(string $modelClass)
    {
        $this->modelClass = $modelClass;
    }

    public function collection(Collection $collection): void
    {
        $model = new $this->modelClass();
        $fillable = $model->getFillable();

        foreach ($collection as $row) {
            try {
                // Filter the row data based on the model's fillable fields
                $data = $row->only($fillable);

                // Validate the data (optional but recommended)
                $validatedData = validator($data, $model->rules ?? [])->validate();

                // Create or update the model
                $model::updateOrCreate(['id' => $data['id'] ?? null], $validatedData);

                Log::info("Imported record for model: {$this->modelClass}");
            } catch (\Exception $e) {
                Log::error("Failed to import row: " . json_encode($row) . " Error: " . $e->getMessage());
            }
        }
    }
}
