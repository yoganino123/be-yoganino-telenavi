<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\TodoStorage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\TodosExport;
use Carbon\Carbon;
 

class TodoController extends Controller
{
    protected TodoStorage $storage;

    public function __construct(TodoStorage $storage)
    {
        $this->storage = $storage;
    }

    public function store(Request $request)
    {
        \Log::info('=== POST /api/todos START ===', [
            'headers' => $request->headers->all(),
            'data'    => $request->all(),
            'ip'      => $request->ip(),
            'method'  => $request->method(),
        ]);

        try {
            $validated = $request->validate([
                'title'        => 'required|string|max:255',
                'assignee'     => 'nullable|string|max:255',
                'due_date'     => 'required|date|after_or_equal:today',
                'time_tracked' => 'nullable|numeric|min:0',
                'priority'     => 'required|in:low,medium,high',
                'status'       => 'nullable|in:pending,open,in_progress,completed',
            ]);

            \Log::info('Validation passed', $validated);

            $todo = [
                'id'           => (string) Str::uuid(),
                'title'        => $validated['title'],
                'assignee'     => $validated['assignee'] ?? null,
                'due_date'     => $validated['due_date'],
                'time_tracked' => $validated['time_tracked'] ?? 0,
                'status'       => $validated['status'] ?? 'pending',
                'priority'     => $validated['priority'],
                'created_at'   => now()->toDateTimeString(),
                'updated_at'   => now()->toDateTimeString(),
            ];

            // simpan 
            $this->storage->append($todo);

            \Log::info('Todo created', $todo);
            \Log::info('=== POST /api/todos END ===');

            return response()->json($todo, 201);

        } catch (\Exception $e) {
            \Log::error('Error in store(): ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    

     

 

    /**
     * Index — list + filtering + optional export
     *
     * Query filters:
     * - title (partial)
     * - assignee (comma separated)
     * - due_date (start,end) e.g. ?due_date=2025-09-01,2025-09-30
     * - time_tracked (min,max) e.g. ?time_tracked=0,10
     * - status (comma separated)
     * - priority (comma separated)
     * - export=excel  (if present, returns Excel download)
     */
    public function index(Request $request)
    {
        $items = $this->storage->load();

        // filters
        if ($q = $request->query('title')) {
            $items = array_filter($items, function($t) use ($q) {
                return stripos($t['title'] ?? '', $q) !== false;
            });
        }

        if ($assigneestr = $request->query('assignee')) {
            $assignees = array_map('trim', explode(',', $assigneestr));
            $items = array_filter($items, function($t) use ($assignees) {
                return in_array($t['assignee'] ?? '', $assignees, true);
            });
        }

        if ($due = $request->query('due_date')) {
            [$start, $end] = array_pad(array_map('trim', explode(',', $due)), 2, null);
            $items = array_filter($items, function($t) use ($start, $end) {
                $d = $t['due_date'] ?? null;
                if (!$d) return false;
                if ($start && $d < $start) return false;
                if ($end && $d > $end) return false;
                return true;
            });
        }

        if ($tt = $request->query('time_tracked')) {
            [$min, $max] = array_pad(array_map('trim', explode(',', $tt)), 2, null);
            $items = array_filter($items, function($t) use ($min, $max) {
                $v = floatval($t['time_tracked'] ?? 0);
                if ($min !== null && $v < floatval($min)) return false;
                if ($max !== null && $v > floatval($max)) return false;
                return true;
            });
        }

        if ($status = $request->query('status')) {
            $statuses = array_map('trim', explode(',', $status));
            $items = array_filter($items, function($t) use ($statuses) {
                return in_array($t['status'] ?? '', $statuses, true);
            });
        }

        if ($priority = $request->query('priority')) {
            $priorities = array_map('trim', explode(',', $priority));
            $items = array_filter($items, function($t) use ($priorities) {
                return in_array($t['priority'] ?? '', $priorities, true);
            });
        }

        // reindex
        $items = array_values($items);

        // if export requested
        if ($request->query('export') === 'excel') {
            //  Excel export
            $export = new TodosExport($items);
            $filename = 'todos_' . now()->format('Ymd_His') . '.xlsx';
            return Excel::download($export, $filename);
        }

        return response()->json(array_values($items));
    }

   
    public function chart(Request $request)
    {
        $type = $request->query('type', 'status');
        $items = $this->storage->load();

        if ($type === 'status') {
            $keys = ['pending','open','in_progress','completed'];
            $summary = array_fill_keys($keys, 0);
            foreach ($items as $t) {
                $s = $t['status'] ?? 'pending';
                if (!isset($summary[$s])) $summary[$s] = 0;
                $summary[$s]++;
            }
            return response()->json(['status_summary' => $summary]);
        }

        if ($type === 'priority') {
            $keys = ['low','medium','high'];
            $summary = array_fill_keys($keys, 0);
            foreach ($items as $t) {
                $p = $t['priority'] ?? 'low';
                if (!isset($summary[$p])) $summary[$p] = 0;
                $summary[$p]++;
            }
            return response()->json(['priority_summary' => $summary]);
        }

        if ($type === 'assignee') {
            $out = [];
            foreach ($items as $t) {
                $a = $t['assignee'] ?? 'Unassigned';
                if (!isset($out[$a])) {
                    $out[$a] = [
                        'total_todos' => 0,
                        'total_pending_todos' => 0,
                        'total_timetracked_completed_todos' => 0,
                    ];
                }
                $out[$a]['total_todos']++;
                if (($t['status'] ?? '') === 'pending') {
                    $out[$a]['total_pending_todos']++;
                }
                if (($t['status'] ?? '') === 'completed') {
                    $out[$a]['total_timetracked_completed_todos'] += floatval($t['time_tracked'] ?? 0);
                }
            }
            return response()->json(['assignee_summary' => $out]);
        }

        return response()->json(['error' => 'invalid chart type'], 400);
    }
}
