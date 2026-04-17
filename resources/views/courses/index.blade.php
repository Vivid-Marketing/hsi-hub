<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Courses Manager') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            @if (session('error'))
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800 border border-red-200">
                    {{ session('error') }}
                </div>
            @endif

            @canany(['manage-courses', 'edit-courses'])
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 sm:p-8 text-gray-900 border-b border-gray-100">
                    <h3 class="text-lg font-medium text-gray-900">{{ __('Sync courses from CLD') }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ __('Fetch selected courses from the CLD API into the singles table, then optionally trigger Craft FeedMe.') }}
                    </p>
                </div>
                <div class="p-6 sm:p-8">
                    <form method="POST" action="{{ route('courses.process-singles') }}" class="space-y-6">
                        @csrf

                        <div>
                            <x-input-label for="cld_ids" :value="__('CLD lesson IDs')" />
                            <p class="mt-1 text-sm text-gray-500">{{ __('Enter the CLD IDs that you would like to process, each on their own line.') }}</p>
                            <textarea
                                id="cld_ids"
                                name="cld_ids"
                                rows="8"
                                class="mt-2 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm font-mono"
                                placeholder="15394&#10;15396&#10;15395"
                                required
                            >{{ old('cld_ids') }}</textarea>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ __('Maximum :max IDs per run.', ['max' => $maxSinglesIds ?? 50]) }}
                            </p>
                            <x-input-error :messages="$errors->get('cld_ids')" class="mt-2" />
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="send_to_craft"
                                        value="1"
                                        class="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        @checked(old('send_to_craft'))
                                    >
                                    <span>
                                        <span class="text-sm font-medium text-gray-900">{{ __('Send to Craft') }}</span>
                                        <span class="block mt-1 text-sm text-gray-600">
                                            {{ __('When enabled, after courses are fetched, FeedMe is triggered so Craft / the website can sync from the singles feed.') }}
                                        </span>
                                        @if (empty(config('cld_api.feedme.passkey')))
                                            <span class="block mt-2 text-xs text-amber-700">{{ __('FeedMe is disabled until CLD_FEEDME_PASSKEY is set in .env.') }}</span>
                                        @endif
                                    </span>
                                </label>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button type="submit">
                                {{ __('Process Courses') }}
                            </x-primary-button>
                            <span class="text-sm text-gray-500">{{ __('This may take several minutes for many IDs.') }}</span>
                        </div>

                        @if (session('cld_sync_ui'))
                            @php
                                $sync = session('cld_sync_ui');
                                $failed = (int) ($sync['failed'] ?? 0);
                                $userCraft = (bool) ($sync['user_wanted_craft'] ?? false);
                                $fmCfg = (bool) ($sync['feedme_configured'] ?? false);
                                $craftRan = (bool) ($sync['craft_ran'] ?? false);
                                $feedmeOk = $sync['feedme_ok'] ?? null;
                                $showAmber = $failed > 0
                                    || ($userCraft && ! $fmCfg)
                                    || ($userCraft && $fmCfg && $craftRan && $feedmeOk === false);
                                $tone = $showAmber ? 'amber' : 'emerald';
                            @endphp
                            <div
                                x-data="{ open: true }"
                                x-show="open"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="relative rounded-lg border p-4 pr-10 text-sm mt-2
                                    @if ($tone === 'emerald') border-emerald-200 bg-emerald-50 text-emerald-900
                                    @else border-amber-200 bg-amber-50 text-amber-950 @endif"
                                role="status"
                            >
                                <button
                                    type="button"
                                    @click="open = false"
                                    class="absolute top-2 right-2 rounded p-1 text-current hover:bg-black/5 focus:outline-none focus:ring-2 focus:ring-offset-1 focus:ring-emerald-600"
                                    aria-label="{{ __('Dismiss') }}"
                                >
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                                <p class="font-medium">
                                    {{ __(':ok of :total courses processed successfully.', ['ok' => $sync['succeeded'] ?? 0, 'total' => $sync['total'] ?? 0]) }}
                                </p>
                                @if (($sync['failed'] ?? 0) > 0)
                                    <p class="mt-2 text-amber-900">
                                        {{ __(':n lesson(s) could not be fetched. Check logs or your notification email for details.', ['n' => $sync['failed']]) }}
                                    </p>
                                @endif

                                @if (! ($sync['user_wanted_craft'] ?? false))
                                    <p class="mt-3">
                                        <span class="font-medium">{{ __('Preview singles JSON feed') }}</span>
                                        —
                                        <a href="{{ $sync['singles_feed_url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="underline font-mono text-xs break-all">
                                            {{ $sync['singles_feed_url'] ?? '' }}
                                        </a>
                                    </p>
                                @else
                                    @if (! ($sync['feedme_configured'] ?? false))
                                        <p class="mt-3 text-amber-900">
                                            {{ __('Send to Craft was selected, but CLD_FEEDME_PASSKEY is not set — FeedMe was not triggered.') }}
                                        </p>
                                        <p class="mt-2">
                                            <a href="{{ $sync['singles_feed_url'] ?? '#' }}" target="_blank" rel="noopener noreferrer" class="underline">
                                                {{ __('Preview singles JSON feed') }}
                                            </a>
                                        </p>
                                    @elseif (($sync['craft_ran'] ?? false) && ($sync['feedme_ok'] ?? false))
                                        <p class="mt-3 text-emerald-800">
                                            {{ __('Send to Craft: FeedMe was triggered successfully.') }}
                                        </p>
                                    @elseif ($sync['craft_ran'] ?? false)
                                        <p class="mt-3 text-amber-900">
                                            {{ __('Send to Craft: FeedMe was requested, but the response did not look successful. Check logs or your notification email.') }}
                                        </p>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </form>
                </div>
            </div>
            @endcanany

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('Courses Management') }}</h3>
                        <p class="text-gray-600">{{ __('Manage your courses and course-related content.') }}</p>
                    </div>

                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('No course list yet') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __('Additional course listing tools can be added here later.') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
