<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Rich Text Tools') }}
        </h2>
    </x-slot>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="origin"></script>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Rich Text Content Cleaner</h3>
                        <p class="text-gray-600">Paste your content here (from Word, Craft CMS, or anywhere). Use "Clean Links" to remove unwanted link settings, then copy the result back into Craft.</p>
                    </div>

                    <!-- Input: WYSIWYG editor -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Your content
                        </label>
                        <textarea
                            id="html-input"
                            name="html"
                            rows="14"
                            placeholder="Paste formatted text here..."
                        ></textarea>
                    </div>

                    <!-- Actions Section -->
                    <div class="mb-6">
                        <h4 class="text-md font-medium text-gray-900 mb-3">Actions</h4>
                        <div class="flex flex-wrap gap-3">
                            <button
                                id="clean-links-btn"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                <span id="clean-links-text">Clean Links Window Target</span>
                                <svg id="clean-links-spinner" class="animate-spin -ml-1 mr-3 h-4 w-4 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Stats Section -->
                    <div id="stats-section" class="mb-6 hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-900 mb-2">Cleaning Statistics</h4>
                            <div class="grid grid-cols-3 gap-4 text-sm">
                                <div>
                                    <span class="text-blue-700">Links cleaned:</span>
                                    <span id="stats-links" class="font-semibold text-blue-900 ml-2">0</span>
                                </div>
                                <div>
                                    <span class="text-blue-700">Targets removed:</span>
                                    <span id="stats-targets" class="font-semibold text-blue-900 ml-2">0</span>
                                </div>
                                <div>
                                    <span class="text-blue-700">Rel attributes cleaned:</span>
                                    <span id="stats-rels" class="font-semibold text-blue-900 ml-2">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Output: read-only WYSIWYG so editors see the result, then copy -->
                    <div id="output-section" class="mb-6 hidden">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Cleaned result — copy this back into Craft
                            </label>
                            <button
                                id="copy-btn"
                                class="inline-flex items-center px-3 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:bg-green-700 active:bg-green-900 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition ease-in-out duration-150"
                            >
                                Copy to clipboard
                            </button>
                        </div>
                        <textarea id="html-output" name="cleaned_html" rows="14"></textarea>
                        <div id="copy-status" class="mt-2 text-sm text-green-600 hidden">
                            ✓ Copied! Paste into Craft CMS.
                        </div>
                    </div>

                    <!-- Error Section -->
                    <div id="error-section" class="mb-6 hidden">
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

                    <!-- Instructions -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                        <h4 class="text-md font-medium text-blue-900 mb-3">How to use</h4>
                        <ol class="list-decimal list-inside space-y-2 text-sm text-blue-800">
                            <li>Paste your content in the editor above (from Word, Craft CMS, or any rich text)</li>
                            <li>Click "Clean Links Window Target" to remove unwanted link settings (e.g. open in new tab)</li>
                            <li>Check the cleaned result below, then click "Copy to clipboard"</li>
                            <li>Paste into your Craft CMS field</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cleanLinksBtn = document.getElementById('clean-links-btn');
            const cleanLinksText = document.getElementById('clean-links-text');
            const cleanLinksSpinner = document.getElementById('clean-links-spinner');
            const copyBtn = document.getElementById('copy-btn');
            const copyStatus = document.getElementById('copy-status');
            const errorSection = document.getElementById('error-section');
            const errorMessage = document.getElementById('error-message');
            const statsSection = document.getElementById('stats-section');
            const statsLinks = document.getElementById('stats-links');
            const statsTargets = document.getElementById('stats-targets');
            const statsRels = document.getElementById('stats-rels');
            const outputSection = document.getElementById('output-section');

            const cleanLinksUrl = '{{ route("rich-text-tools.clean-links") }}';
            const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            // Store cleaned HTML for copy (exact server output)
            let lastCleanedHtml = '';
            let outputEditorInitialized = false;

            tinymce.init({
                selector: '#html-input',
                height: 320,
                menubar: false,
                plugins: 'lists link paste',
                toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright | bullist numlist | link',
                paste_as_text: false,
                paste_data_images: false,
                content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; }',
                branding: false,
                promotion: false
            });

            function setOutputContent(html) {
                lastCleanedHtml = html || '';
                if (outputEditorInitialized) {
                    var ed = tinymce.get('html-output');
                    if (ed) ed.setContent(lastCleanedHtml);
                    outputSection.classList.remove('hidden');
                    return;
                }
                outputEditorInitialized = true;
                outputSection.classList.remove('hidden');
                tinymce.init({
                    selector: '#html-output',
                    height: 320,
                    menubar: false,
                    toolbar: false,
                    readonly: true,
                    plugins: 'lists link',
                    content_style: 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; font-size: 14px; }',
                    branding: false,
                    promotion: false,
                    init_instance_callback: function(ed) {
                        ed.setContent(lastCleanedHtml);
                    }
                });
            }

            cleanLinksBtn.addEventListener('click', function() {
                const editorInput = tinymce.get('html-input');
                const html = editorInput ? editorInput.getContent() : '';

                if (!html || !html.trim()) {
                    errorMessage.textContent = 'Please paste or type some content first.';
                    errorSection.classList.remove('hidden');
                    return;
                }

                errorSection.classList.add('hidden');
                statsSection.classList.add('hidden');
                outputSection.classList.add('hidden');

                cleanLinksBtn.disabled = true;
                cleanLinksText.textContent = 'Cleaning...';
                cleanLinksSpinner.classList.remove('hidden');

                fetch(cleanLinksUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ html: html })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        setOutputContent(data.cleaned_html || '');
                        if (data.stats) {
                            statsLinks.textContent = data.stats.links_cleaned || 0;
                            statsTargets.textContent = data.stats.targets_removed || 0;
                            statsRels.textContent = data.stats.rels_cleaned || 0;
                            statsSection.classList.remove('hidden');
                        }
                    } else {
                        errorMessage.textContent = data.message || 'An error occurred while cleaning the HTML.';
                        errorSection.classList.remove('hidden');
                    }
                })
                .catch(function() {
                    errorMessage.textContent = 'An unexpected error occurred. Please try again.';
                    errorSection.classList.remove('hidden');
                })
                .finally(function() {
                    cleanLinksBtn.disabled = false;
                    cleanLinksText.textContent = 'Clean Links Window Target';
                    cleanLinksSpinner.classList.add('hidden');
                });
            });

            copyBtn.addEventListener('click', function() {
                const textToCopy = lastCleanedHtml || (tinymce.get('html-output') ? tinymce.get('html-output').getContent() : '');

                if (!textToCopy) {
                    errorMessage.textContent = 'Nothing to copy. Run "Clean Links" first.';
                    errorSection.classList.remove('hidden');
                    return;
                }

                navigator.clipboard.writeText(textToCopy).then(function() {
                    copyStatus.classList.remove('hidden');
                    setTimeout(function() { copyStatus.classList.add('hidden'); }, 3000);
                }).catch(function() {
                    try {
                        var ta = document.createElement('textarea');
                        ta.value = textToCopy;
                        ta.setAttribute('readonly', '');
                        ta.style.position = 'absolute';
                        ta.style.left = '-9999px';
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        copyStatus.classList.remove('hidden');
                        setTimeout(function() { copyStatus.classList.add('hidden'); }, 3000);
                    } catch (e) {
                        errorMessage.textContent = 'Could not copy. Select the text above and copy manually.';
                        errorSection.classList.remove('hidden');
                    }
                });
            });
        });
    </script>
</x-app-layout>
