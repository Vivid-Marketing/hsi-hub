<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Training Assessment PDF Logs') }}
            </h2>
            <a href="{{ route('pdf-tools.index') }}" class="inline-flex items-center px-4 py-2 bg-white border rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Back to PDF Tools') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4 text-sm text-gray-600">
                        Public endpoints (POST): <code class="px-1 py-0.5 bg-gray-100 rounded">/reports/generate-report-html</code>,
                        <code class="px-1 py-0.5 bg-gray-100 rounded">/reports/generate-report-html-hrca</code>,
                        <code class="px-1 py-0.5 bg-gray-100 rounded">/reports/generate-report-html-qew</code>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">When</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title / Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origin / IP</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PDF</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Error</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($recent as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $row->created_at?->format('Y-m-d H:i:s') ?? '' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $row->type }}
                                        </td>
                                        <td class="px-4 py-2 text-sm whitespace-nowrap">
                                            @if($row->status === 'success')
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-green-100 text-green-800">success</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">failed</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700">
                                            <div class="font-medium">{{ $row->title }}</div>
                                            <div class="text-gray-500">{{ $row->name }}</div>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700">
                                            <div class="text-gray-700">{{ $row->origin }}</div>
                                            <div class="text-gray-500">{{ $row->ip }}</div>
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                            {{ $row->duration_ms ? ($row->duration_ms . 'ms') : '' }}
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700">
                                            @if($row->pdf_url)
                                                <a href="{{ $row->pdf_url }}" target="_blank" class="text-blue-600 hover:text-blue-800 underline">Open</a>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-sm text-gray-700 max-w-md">
                                            @if($row->error_message)
                                                <div class="truncate" title="{{ $row->error_message }}">{{ $row->error_message }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-4 py-6 text-center text-sm text-gray-500">
                                            No logs yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-10 border-t border-gray-200 pt-8">
                        <h3 class="text-base font-semibold text-gray-900 mb-1">{{ __('Survey telemetry (Craft batches)') }}</h3>
                        <p class="mb-4 text-sm text-gray-600">
                            {{ __('Ingested from') }}
                            <code class="px-1 py-0.5 bg-gray-100 rounded">POST /internal/surveys-pdf/logs</code>
                            {{ __('(signed). Most recent events below.') }}
                        </p>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('When (client)') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Level') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Event') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Survey') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Page') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Path') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Visitor') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Client / hub IP') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Source') }}</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">{{ __('Extras') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($surveyEvents as $ev)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">
                                                {{ $ev->client_occurred_at->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm whitespace-nowrap">
                                                @if(($ev->level ?? '') === 'error')
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-800">{{ $ev->level }}</span>
                                                @elseif($ev->level)
                                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ $ev->level }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">{{ $ev->event_type }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-700 whitespace-nowrap">{{ $ev->survey }}</td>
                                            <td class="px-4 py-2 text-sm text-gray-700 max-w-[8rem]">
                                                @if($ev->page)
                                                    <div class="truncate" title="{{ $ev->page }}">{{ $ev->page }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 max-w-xs">
                                                <div class="truncate" title="{{ $ev->path }}">{{ $ev->path }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 max-w-[10rem]">
                                                @if($ev->visitor_id)
                                                    <div class="truncate font-mono text-xs" title="{{ $ev->visitor_id }}">{{ $ev->visitor_id }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700">
                                                <div>{{ $ev->client_ip }}</div>
                                                <div class="text-gray-500 text-xs">{{ $ev->hub_ip }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 max-w-[8rem]">
                                                <div class="truncate" title="{{ $ev->source }}">{{ $ev->source }}</div>
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-700 max-w-md">
                                                @if($ev->extras && count($ev->extras))
                                                    <div class="truncate font-mono text-xs" title="{{ json_encode($ev->extras) }}">{{ json_encode($ev->extras) }}</div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="10" class="px-4 py-6 text-center text-sm text-gray-500">
                                                {{ __('No survey telemetry rows yet.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

