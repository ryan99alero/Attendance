<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ReportItem extends Model
{
    protected $fillable = [
        'id',
        'report_name',
        'description',
        'last_generated',
        'status'
    ];

    protected $casts = [
        'last_generated' => 'datetime'
    ];

    // This is a virtual model - we don't use a database table
    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'int';

    /**
     * Get all available reports as a collection
     */
    public static function getReports()
    {
        return collect([
            (object)[
                'id' => 1,
                'report_name' => 'ADP Export Report',
                'description' => 'Attendance data formatted for ADP payroll import. Includes employee hours, overtime calculations, and pay codes.',
                'last_generated' => now()->subHours(2),
                'status' => 'ready'
            ],
            (object)[
                'id' => 2,
                'report_name' => 'Payroll Summary Report',
                'description' => 'Summary of hours worked, regular time, overtime, and gross pay calculations by employee.',
                'last_generated' => now()->subDays(1),
                'status' => 'ready'
            ],
            (object)[
                'id' => 3,
                'report_name' => 'Department Hours Report',
                'description' => 'Hours breakdown by department for labor cost analysis.',
                'last_generated' => null,
                'status' => 'ready'
            ],
        ]);
    }

    /**
     * Override the newQuery method to return a custom builder
     */
    public function newQuery()
    {
        // Return a mock builder that works with Filament
        return app(MockQueryBuilder::class, ['model' => $this]);
    }

    /**
     * Override the query method
     */
    public static function query()
    {
        return (new static)->newQuery();
    }
}

/**
 * Mock Query Builder for virtual models
 */
class MockQueryBuilder extends Builder
{
    protected $mockData;

    public function __construct($query = null)
    {
        // Don't call parent constructor to avoid database connection issues
        $this->mockData = ReportItem::getReports();
    }

    public function get($columns = ['*'])
    {
        return $this->mockData;
    }

    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null, $total = null)
    {
        return $this->mockData;
    }

    public function orderBy($column, $direction = 'asc')
    {
        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        return $this;
    }

    public function limit($value)
    {
        return $this;
    }

    public function offset($value)
    {
        return $this;
    }

    public function count($columns = ['*'])
    {
        return $this->mockData->count();
    }

    public function pluck($column, $key = null)
    {
        return $this->mockData->pluck($column, $key);
    }

    public function toSql()
    {
        return 'SELECT * FROM virtual_reports';
    }

    public function getBindings()
    {
        return [];
    }
}