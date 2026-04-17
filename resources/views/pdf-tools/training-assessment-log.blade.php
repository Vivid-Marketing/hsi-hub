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
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

