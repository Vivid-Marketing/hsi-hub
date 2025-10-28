<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('MP3 Tools') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Zencastr MP3 URL Extractor</h3>
                        <p class="text-gray-600">Extract direct MP3 URLs from Zencastr podcast links for easy downloading or sharing.</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-6">
                        <form id="mp3-extract-form" class="space-y-4">
                            @csrf
                            <div>
                                <label for="zencastr-url" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zencastr URL
                                </label>
                                <input 
                                    type="url" 
                                    id="zencastr-url" 
                                    name="url" 
                                    placeholder="https://zencastr.com/z/R2s9pexZ"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                <p class="mt-1 text-sm text-gray-500">Enter a valid Zencastr podcast URL</p>
                            </div>
                            
                            <div>
                                <button 
                                    type="submit" 
                                    id="extract-btn"
                                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                >
                                    <span id="btn-text">Extract MP3 URL</span>
                                    <svg id="btn-spinner" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </button>
                            </div>
                        </form>

                        <!-- Results Section -->
                        <div id="results-section" class="mt-6 hidden">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h4 class="text-md font-medium text-gray-900 mb-3">Extracted MP3 URL</h4>
                                <div class="flex items-center space-x-2">
                                    <input 
                                        type="text" 
                                        id="mp3-url-result" 
                                        readonly 
                                        class="flex-1 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm font-mono"
                                    >
                                    <button 
                                        id="copy-btn"
                                        class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    >
                                        Copy
                                    </button>
                                </div>
                                <div id="copy-status" class="mt-2 text-sm text-green-600 hidden">
                                    ✓ URL copied to clipboard!
                                </div>
                            </div>
                        </div>

                        <!-- Error Section -->
                        <div id="error-section" class="mt-6 hidden">
                            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-red-800">Error</h3>
                                        <div class="mt-2 text-sm text-red-700">
                                            <p id="error-message"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Instructions -->
                    <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h4 class="text-md font-medium text-blue-900 mb-3">How to use</h4>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">
                            <li>Copy a Zencastr podcast URL (e.g., https://zencastr.com/z/R2s9pexZ)</li>
                            <li>Paste it into the input field above</li>
                            <li>Click "Extract MP3 URL" to get the direct download link</li>
                            <li>Use the "Copy" button to copy the MP3 URL to your clipboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('mp3-extract-form');
            const extractBtn = document.getElementById('extract-btn');
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');
            const resultsSection = document.getElementById('results-section');
            const errorSection = document.getElementById('error-section');
            const mp3UrlResult = document.getElementById('mp3-url-result');
            const copyBtn = document.getElementById('copy-btn');
            const copyStatus = document.getElementById('copy-status');
            const errorMessage = document.getElementById('error-message');

            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Hide previous results
                resultsSection.classList.add('hidden');
                errorSection.classList.add('hidden');
                
                // Show loading state
                extractBtn.disabled = true;
                btnText.textContent = 'Extracting...';
                btnSpinner.classList.remove('hidden');

                // Get form data
                const formData = new FormData(form);
                const url = formData.get('url');

                // Make AJAX request
                fetch('{{ route("mp3-tools.extract") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mp3UrlResult.value = data.audioUrl;
                        resultsSection.classList.remove('hidden');
                    } else {
                        errorMessage.textContent = data.message;
                        errorSection.classList.remove('hidden');
                    }
                })
                .catch(error => {
                    errorMessage.textContent = 'An unexpected error occurred. Please try again.';
                    errorSection.classList.remove('hidden');
                })
                .finally(() => {
                    // Reset button state
                    extractBtn.disabled = false;
                    btnText.textContent = 'Extract MP3 URL';
                    btnSpinner.classList.add('hidden');
                });
            });

            // Copy to clipboard functionality
            copyBtn.addEventListener('click', function() {
                mp3UrlResult.select();
                mp3UrlResult.setSelectionRange(0, 99999); // For mobile devices
                
                try {
                    document.execCommand('copy');
                    copyStatus.classList.remove('hidden');
                    setTimeout(() => {
                        copyStatus.classList.add('hidden');
                    }, 3000);
                } catch (err) {
                    // Fallback for modern browsers
                    navigator.clipboard.writeText(mp3UrlResult.value).then(() => {
                        copyStatus.classList.remove('hidden');
                        setTimeout(() => {
                            copyStatus.classList.add('hidden');
                        }, 3000);
                    });
                }
            });
        });
    </script>
</x-app-layout>
