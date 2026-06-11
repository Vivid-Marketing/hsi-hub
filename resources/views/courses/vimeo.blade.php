<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Courses Manager') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <nav class="flex gap-1 border-b border-gray-200">
                <a
                    href="{{ route('courses.index') }}"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ request()->routeIs('courses.index') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                >
                    {{ __('Manual Singles') }}
                </a>
                <a
                    href="{{ route('courses.vimeo') }}"
                    class="border-b-2 px-4 py-2 text-sm font-medium {{ request()->routeIs('courses.vimeo') ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' }}"
                >
                    {{ __('Vimeo') }}
                </a>
            </nav>

            @if ($error)
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 border border-red-200">
                    {{ $error }}
                </div>
            @endif

            @unless ($configured)
                <div class="rounded-md bg-amber-50 p-4 text-sm text-amber-900 border border-amber-200">
                    {{ __('Vimeo is not connected yet. Set VIMEO_ACCESS_TOKEN in .env (a personal access token with Public and Private scopes).') }}
                </div>
            @endunless

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8 text-gray-900 border-b border-gray-100">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Latest Vimeo uploads') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Pull the most recently uploaded videos from Vimeo within the selected time window.') }}
                    </p>
                </div>

                <div class="p-6 sm:p-8">
                    <form method="GET" action="{{ route('courses.vimeo') }}" class="flex flex-wrap items-end gap-4">
                        <div>
                            <x-input-label for="period" :value="__('Time Ago')" />
                            <select
                                id="period"
                                name="period"
                                class="mt-1 block w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                @foreach ($periods as $value => $label)
                                    <option value="{{ $value }}" @selected($period === $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <x-primary-button type="submit" :disabled="! $configured">
                            {{ __('Fetch videos') }}
                        </x-primary-button>
                    </form>

                    @if ($fetched && $videos !== null)
                        @php
                            $copyText = collect($videos)->pluck('id')->implode("\n");
                        @endphp

                        <div
                            class="mt-8"
                            x-data="{
                                ids: @js($copyText),
                                copied: false,
                                async copyIds() {
                                    try {
                                        await navigator.clipboard.writeText(this.ids);
                                    } catch (e) {
                                        const ta = document.createElement('textarea');
                                        ta.value = this.ids;
                                        document.body.appendChild(ta);
                                        ta.select();
                                        document.execCommand('copy');
                                        document.body.removeChild(ta);
                                    }
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                },
                            }"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <p class="text-sm text-gray-600">
                                    {{ trans_choice(':count video found|:count videos found', count($videos), ['count' => count($videos)]) }}
                                </p>
                                <button
                                    type="button"
                                    @click="copyIds()"
                                    x-bind:disabled="! ids"
                                    class="inline-flex items-center gap-2 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                    <span x-show="! copied">{{ __('Copy all IDs') }}</span>
                                    <span x-show="copied" x-cloak class="text-emerald-700">{{ __('Copied!') }}</span>
                                </button>
                            </div>

                            <div class="mt-3 overflow-x-auto rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 text-sm">
                                    <thead class="bg-gray-50 text-gray-700">
                                        <tr>
                                            <th class="px-4 py-3 text-left font-medium">{{ __('Video Title') }}</th>
                                            <th class="px-4 py-3 text-left font-medium">{{ __('Video ID') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 bg-white">
                                        @forelse ($videos as $video)
                                            <tr class="hover:bg-gray-50/80">
                                                <td class="px-4 py-3 text-gray-900">{{ $video['title'] }}</td>
                                                <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $video['id'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td class="px-4 py-6 text-center text-gray-500" colspan="2">
                                                    {{ __('No videos uploaded in this time window.') }}
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
