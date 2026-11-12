<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'action' => trim((string) $request->query('action', '')) ?: null,
            'entity_type' => trim((string) $request->query('entity_type', '')) ?: null,
            'actor_id' => $request->integer('actor_id') ?: null,
            'search' => trim((string) $request->query('search', '')) ?: null,
        ];

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['action'], fn ($query, $value) => $query->where('action', $value))
            ->when($filters['entity_type'], fn ($query, $value) => $query->where('auditable_type', $value))
            ->when($filters['actor_id'], fn ($query, $value) => $query->where('actor_id', $value))
            ->when($filters['search'], function ($query, $value) {
                $query->where(function ($subQuery) use ($value) {
                    $subQuery->where('action', 'like', "%{$value}%")
                        ->orWhere('reason', 'like', "%{$value}%")
                        ->orWhere('from_status', 'like', "%{$value}%")
                        ->orWhere('to_status', 'like', "%{$value}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => class_basename($log->auditable_type),
                'entity_id' => $log->auditable_id,
                'from_status' => $log->from_status,
                'to_status' => $log->to_status,
                'reason' => $log->reason,
                'metadata' => $log->metadata,
                'actor' => $log->actor ? [
                    'name' => $log->actor->name,
                    'email' => $log->actor->email,
                ] : null,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'entityTypes' => AuditLog::query()
                ->distinct()
                ->orderBy('auditable_type')
                ->pluck('auditable_type')
                ->map(fn (string $type) => [
                    'value' => $type,
                    'label' => class_basename($type),
                ])
                ->values(),
            'actors' => User::query()
                ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = [
            'action' => trim((string) $request->query('action', '')) ?: null,
            'entity_type' => trim((string) $request->query('entity_type', '')) ?: null,
            'actor_id' => $request->integer('actor_id') ?: null,
            'search' => trim((string) $request->query('search', '')) ?: null,
        ];

        $logs = AuditLog::query()
            ->with('actor:id,name,email')
            ->when($filters['action'], fn ($query, $value) => $query->where('action', $value))
            ->when($filters['entity_type'], fn ($query, $value) => $query->where('auditable_type', $value))
            ->when($filters['actor_id'], fn ($query, $value) => $query->where('actor_id', $value))
            ->when($filters['search'], function ($query, $value) {
                $query->where(function ($subQuery) use ($value) {
                    $subQuery->where('action', 'like', "%{$value}%")
                        ->orWhere('reason', 'like', "%{$value}%")
                        ->orWhere('from_status', 'like', "%{$value}%")
                        ->orWhere('to_status', 'like', "%{$value}%");
                });
            })
            ->latest('id')
            ->get();

        $filename = 'audit-trail-ytevent-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($logs): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'ID',
                'Waktu',
                'Action',
                'Entitas',
                'Entity ID',
                'Status Sebelum',
                'Status Sesudah',
                'Actor',
                'Email Actor',
                'Alasan',
                'Metadata',
            ]);

            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->id,
                    $log->created_at?->format('Y-m-d H:i:s'),
                    $log->action,
                    class_basename($log->auditable_type),
                    $log->auditable_id,
                    $log->from_status,
                    $log->to_status,
                    $log->actor?->name ?? 'Sistem',
                    $log->actor?->email,
                    $log->reason,
                    $log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
