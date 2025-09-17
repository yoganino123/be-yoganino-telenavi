<?php
namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class TodosExport implements FromArray, ShouldAutoSize
{
    protected array $items;

    public function __construct(array $items)
    {
        $this->items = $items;
    }

    public function array(): array
    {
        $rows = [];

        // header
        $rows[] = ['title','assignee','due_date','time_tracked','status','priority'];

        $totalTime = 0;
        foreach ($this->items as $t) {
            $rows[] = [
                $t['title'] ?? '',
                $t['assignee'] ?? '',
                $t['due_date'] ?? '',
                $t['time_tracked'] ?? 0,
                $t['status'] ?? '',
                $t['priority'] ?? '',
            ];
            $totalTime += floatval($t['time_tracked'] ?? 0);
        }

        // empty row then summary rows
        $rows[] = [];
        $rows[] = ['Total number of todos', count($this->items)];
        $rows[] = ['Total time_tracked', $totalTime];

        return $rows;
    }
}
