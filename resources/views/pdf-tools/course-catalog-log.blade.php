<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ __('Course Catalog PDF Logs') }}
                </h2>
                <p class="text-sm text-gray-600">
                    Tracks incoming requests, PDF generation, and email delivery.
                </p>
            </div>
            <a href="{{ route('pdf-tools.index') }}" class="inline-flex items-center px-3 py-2 rounded-md text-sm bg-white border hover:bg-gray-50">
                Back to PDF Tools
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900">Scheduler status</h3>
                            <p class="text-sm text-gray-600">
                                Last update:
                                <span class="font-mono">{{ $schedulerLogMtime ?? '—' }}</span>
                            </p>
                        </div>
                    </div>
                    <div class="mt-4">
                        @if (!empty($schedulerLogTail))
                            <pre class="text-xs bg-gray-50 border rounded p-3 overflow-x-auto whitespace-pre-wrap">{{ $schedulerLogTail }}</pre>
                        @else
                            <div class="text-sm text-gray-500">No scheduler log output yet.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent Batch Jobs</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b bg-gray-50 text-left">
                                    <th class="py-2 px-3">Job ID</th>
                                    <th class="py-2 px-3">Email</th>
                                    <th class="py-2 px-3">Last seen</th>
                                    <th class="py-2 px-3">Total</th>
                                    <th class="py-2 px-3">Pending</th>
                                    <th class="py-2 px-3">Processed</th>
                                    <th class="py-2 px-3">Failed</th>
                                    <th class="py-2 px-3">Stitched CPDID</th>
                                    <th class="py-2 px-3">Processed at</th>
                                    <th class="py-2 px-3">Error</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentBatchJobs as $row)
                                    <tr class="border-b">
                                        <td class="py-2 px-3 font-mono">{{ $row->job_id }}</td>
                                        <td class="py-2 px-3">{{ $row->email }}</td>
                                        <td class="py-2 px-3">{{ $row->last_seen }}</td>
                                        <td class="py-2 px-3">{{ $row->total_batches }}</td>
                                        <td class="py-2 px-3">{{ $row->pending_batches }}</td>
                                        <td class="py-2 px-3">{{ $row->processed_batches }}</td>
                                        <td class="py-2 px-3">{{ $row->failed_batches }}</td>
                                        <td class="py-2 px-3 font-mono">{{ $row->stitched_cpdid ?? '—' }}</td>
                                        <td class="py-2 px-3">{{ $row->processed_at ?? '—' }}</td>
                                        <td class="py-2 px-3">
                                            @if (!empty($row->error_message))
                                                <span class="text-red-700">{{ $row->error_message }}</span>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="py-6 text-center text-gray-500">No batch jobs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recent PDF Requests</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="border-b bg-gray-50 text-left">
                                    <th class="py-2 px-3">CPDID</th>
                                    <th class="py-2 px-3">Email</th>
                                    <th class="py-2 px-3">Entered</th>
                                    <th class="py-2 px-3">Status</th>
                                    <th class="py-2 px-3">PDF</th>
                                    <th class="py-2 px-3">PDF generated</th>
                                    <th class="py-2 px-3">Email sent</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentData as $row)
                                    <tr class="border-b">
                                        <td class="py-2 px-3 font-mono">{{ $row->cpdid }}</td>
                                        <td class="py-2 px-3">{{ $row->email }}</td>
                                        <td class="py-2 px-3">{{ $row->date_entered }}</td>
                                        <td class="py-2 px-3">{{ $row->status }}</td>
                                        <td class="py-2 px-3">
                                            @if (!empty($row->pdf_url))
                                                <a class="text-blue-600 hover:underline" href="{{ $row->pdf_url }}" target="_blank" rel="noreferrer">Open</a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="py-2 px-3">{{ $row->pdf_generated_at ?? '—' }}</td>
                                        <td class="py-2 px-3">{{ $row->email_sent_at ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-6 text-center text-gray-500">No PDF requests yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

