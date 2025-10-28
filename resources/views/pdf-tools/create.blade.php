<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Generate PDF') }}
            </h2>
            <a href="{{ route('pdf-tools.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                {{ __('Back to PDF Tools') }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">PDF Generation</h3>
                        <p class="text-gray-600">Select the type of PDF you want to generate and provide the necessary information.</p>
                    </div>

                    <!-- PDF Type Selection -->
                    <form method="POST" action="{{ route('pdf-tools.generate') }}" class="space-y-6">
                        @csrf
                        
                        <div>
                            <x-input-label for="pdf_type" :value="__('PDF Type')" />
                            <select id="pdf_type" name="pdf_type" class="block mt-1 w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm" required>
                                <option value="">Select PDF Type</option>
                                <option value="certificate">Certificate</option>
                                <option value="report">Report</option>
                                <option value="invoice">Invoice</option>
                            </select>
                            <x-input-error :messages="$errors->get('pdf_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="title" :value="__('Title')" />
                            <x-text-input id="title" class="block mt-1 w-full" type="text" name="title" :value="old('title')" required />
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="content" :value="__('Content')" />
                            <textarea id="content" name="content" rows="6" class="block mt-1 w-full border-gray-300 focus:border-purple-500 focus:ring-purple-500 rounded-md shadow-sm" required>{{ old('content') }}</textarea>
                            <x-input-error :messages="$errors->get('content')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <x-input-label for="recipient_name" :value="__('Recipient Name')" />
                                <x-text-input id="recipient_name" class="block mt-1 w-full" type="text" name="recipient_name" :value="old('recipient_name')" />
                                <x-input-error :messages="$errors->get('recipient_name')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="date" :value="__('Date')" />
                                <x-text-input id="date" class="block mt-1 w-full" type="date" name="date" :value="old('date', date('Y-m-d'))" />
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex items-center justify-end">
                            <a href="{{ route('pdf-tools.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 focus:bg-gray-400 active:bg-gray-500 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150 mr-3">
                                {{ __('Cancel') }}
                            </a>
                            <x-primary-button class="bg-purple-600 hover:bg-purple-700 focus:bg-purple-700 active:bg-purple-900 focus:ring-purple-500">
                                {{ __('Generate PDF') }}
                            </x-primary-button>
                        </div>
                    </form>

                    <!-- Note about future functionality -->
                    <div class="mt-8 p-4 bg-purple-50 border border-purple-200 rounded-lg">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-purple-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-purple-800">Coming Soon</h3>
                                <div class="mt-2 text-sm text-purple-700">
                                    <p>This is a placeholder form. The actual PDF generation functionality will be implemented using DomPDF and other tools from your previous version.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
