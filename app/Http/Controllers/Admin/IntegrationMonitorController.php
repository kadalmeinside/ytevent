<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationMonitorController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), ['failed', 'processed'], true)
            ? $request->query('status')
            : null;
        $provider = in_array($request->query('provider'), ['midtrans', 'xendit'], true)
            ? $request->query('provider')
            : null;
        $search = trim((string) $request->query('search', '')) ?: null;

        $webhooks = WebhookLog::query()
            ->when($status, fn ($query, $value) => $query->where('status', $value))
            ->when($provider, fn ($query, $value) => $query->where('provider', $value))
            ->when($search, function ($query, $value) {
                $query->where(function ($subQuery) use ($value) {
                    $subQuery->where('invoice_number', 'like', "%{$value}%")
                        ->orWhere('external_reference', 'like', "%{$value}%")
                        ->orWhere('error_message', 'like', "%{$value}%");
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (WebhookLog $log) => [
                'id' => $log->id,
                'provider' => $log->provider,
                'invoice_number' => $log->invoice_number,
                'external_reference' => $log->external_reference,
                'event_name' => $log->event_name,
                'status' => $log->status,
                'http_status' => $log->http_status,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at?->format('d/m/Y H:i:s'),
            ]);

        $failedJobs = DB::table('failed_jobs')
            ->when($search, function ($query, $value) {
                $query->where(function ($subQuery) use ($value) {
                    $subQuery->where('uuid', 'like', "%{$value}%")
                        ->orWhere('queue', 'like', "%{$value}%")
                        ->orWhere('exception', 'like', "%{$value}%");
                });
            })
            ->latest('failed_at')
            ->paginate(25, ['*'], 'jobs_page')
            ->withQueryString()
            ->through(fn (object $job) => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'connection' => $job->connection,
                'failed_at' => $job->failed_at,
                'exception' => $job->exception,
            ]);

        return Inertia::render('Admin/IntegrationMonitor/Index', [
            'webhooks' => $webhooks,
            'failedJobs' => $failedJobs,
            'filters' => [
                'status' => $status,
                'provider' => $provider,
                'search' => $search,
            ],
            'metrics' => [
                'failed_webhooks' => WebhookLog::query()->where('status', 'failed')->count(),
                'processed_webhooks' => WebhookLog::query()->where('status', 'processed')->count(),
                'failed_jobs' => DB::table('failed_jobs')->count(),
            ],
        ]);
    }
}
