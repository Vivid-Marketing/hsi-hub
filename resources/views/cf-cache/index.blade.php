<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('CF Cache') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(! $configured)
                <div class="bg-yellow-50 border border-yellow-300 text-yellow-800 px-4 py-3 rounded">
                    Cloudflare is not configured. Set <code>CLOUDFLARE_API_TOKEN</code> and <code>CLOUDFLARE_ZONE_ID</code> in .env.
                </div>
            @endif

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Purge specific URLs -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-4">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Clear Specific URLs</h3>
                        <p class="text-gray-600">Purge the Cloudflare cache for individual pages or files on hsi.com.</p>
                    </div>

                    <form method="POST" action="{{ route('cf-cache.purge-urls') }}" onsubmit="this.querySelector('button').disabled = true;">
                        @csrf

                        <label for="urls" class="block text-sm font-medium text-gray-700 mb-1">URLs</label>
                        <p class="text-sm text-gray-500 mb-2">Enter <strong>1 URL per line</strong>, including <code>https://</code> (e.g. <code>https://hsi.com/courses/cpr</code>).</p>
                        <textarea
                            id="urls"
                            name="urls"
                            rows="10"
                            required
                            placeholder="https://hsi.com/page-one&#10;https://hsi.com/page-two"
                            class="w-full border-gray-300 rounded-md shadow-sm font-mono text-sm focus:border-yellow-500 focus:ring-yellow-500"
                        >{{ old('urls') }}</textarea>
                        @error('urls')
                            <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                        @enderror

                        <div class="mt-4">
                            <button
                                type="submit"
                                class="inline-flex items-center px-4 py-2 bg-yellow-400 border border-transparent rounded-md font-semibold text-xs text-gray-900 uppercase tracking-widest hover:bg-yellow-500 focus:bg-yellow-500 active:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                            >
                                Clear URL(s)
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Purge everything -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 text-center">
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Clear All Cache</h3>
                    <p class="text-gray-600 max-w-2xl mx-auto">
                        Purges <strong>everything</strong> Cloudflare has cached for hsi.com. Every visitor will hit the origin server until the cache rebuilds.
                    </p>
                    <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-4 py-3 mt-4 max-w-2xl mx-auto">
                        <strong>Please do not use this multiple times in a short period.</strong>
                        Only use this if you have updated a large number of pages and listing them all in the section above isn't possible or would be too time consuming.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('cf-cache.purge-all') }}"
                        class="mt-6"
                        onsubmit="if (! confirm('Clear ALL Cloudflare cache for hsi.com?')) return false; this.querySelector('button').disabled = true;"
                    >
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:bg-red-700 active:bg-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150"
                        >
                            Clear All
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
