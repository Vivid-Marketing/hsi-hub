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
                    @php
                        $maxSinglesIdsLimit = (int) ($maxSinglesIds ?? config('cld_api.ui.max_singles_ids', 50));
                        $initialSyncRows = [['cld_id' => '', 'vimeo_id' => '']];

                        if (old('course_sync_rows')) {
                            $decoded = json_decode(old('course_sync_rows'), true);
                            if (is_array($decoded) && count($decoded) > 0) {
                                $initialSyncRows = array_values(array_map(static fn ($row) => [
                                    'cld_id' => (string) ($row['cld_id'] ?? ''),
                                    'vimeo_id' => (string) ($row['vimeo_id'] ?? ''),
                                ], $decoded));
                            }
                        } elseif (old('cld_ids')) {
                            $initialSyncRows = [];
                            foreach (preg_split('/\R/u', old('cld_ids')) ?: [] as $line) {
                                $line = trim($line);
                                if ($line === '') {
                                    continue;
                                }
                                if (str_contains($line, "\t")) {
                                    [$cldId, $vimeoId] = array_pad(explode("\t", $line, 2), 2, '');
                                    $initialSyncRows[] = ['cld_id' => trim($cldId), 'vimeo_id' => trim($vimeoId)];
                                } else {
                                    $initialSyncRows[] = ['cld_id' => $line, 'vimeo_id' => ''];
                                }
                            }
                        }

                        if ($initialSyncRows === []) {
                            $initialSyncRows = [['cld_id' => '', 'vimeo_id' => '']];
                        }
                    @endphp

                    <script>
                        document.addEventListener('alpine:init', () => {
                            Alpine.data('courseSyncGrid', (initialRows, maxRows) => ({
                                rows: initialRows,
                                maxRows: maxRows,

                                isValidCldId(value) {
                                    return /^\d+$/.test(String(value).trim());
                                },

                                isEmptyVimeo(value) {
                                    const normalized = String(value).trim().toLowerCase();
                                    return normalized === '' || normalized === 'n/a' || normalized === 'na' || normalized === '-';
                                },

                                vimeoLooksInvalid(value) {
                                    if (this.isEmptyVimeo(value)) {
                                        return false;
                                    }

                                    return ! /^\d+$/.test(String(value).trim());
                                },

                                get validRowCount() {
                                    return this.rows.filter((row) => this.isValidCldId(row.cld_id)).length;
                                },

                                get overMax() {
                                    return this.validRowCount > this.maxRows;
                                },

                                parsePastedText(text) {
                                    return text
                                        .split(/\r?\n/)
                                        .map((line) => line.trim())
                                        .filter((line) => line !== '')
                                        .map((line) => {
                                            let parts;

                                            if (line.includes('\t')) {
                                                parts = line.split('\t');
                                            } else if (line.includes(',')) {
                                                parts = line.split(',');
                                            } else {
                                                parts = line.split(/\s+/);
                                            }

                                            return {
                                                cld_id: String(parts[0] ?? '').trim(),
                                                vimeo_id: String(parts[1] ?? '').trim(),
                                            };
                                        })
                                        .filter((row) => row.cld_id !== '' || row.vimeo_id !== '');
                                },

                                handlePaste(event) {
                                    event.preventDefault();
                                    const parsed = this.parsePastedText(event.clipboardData.getData('text/plain'));

                                    if (parsed.length > 0) {
                                        this.rows = parsed;
                                    }
                                },

                                addRow() {
                                    this.rows.push({ cld_id: '', vimeo_id: '' });
                                },

                                removeRow(index) {
                                    if (this.rows.length === 1) {
                                        this.rows = [{ cld_id: '', vimeo_id: '' }];
                                        return;
                                    }

                                    this.rows.splice(index, 1);
                                },

                                clearAll() {
                                    this.rows = [{ cld_id: '', vimeo_id: '' }];
                                },

                                serializedRows() {
                                    return JSON.stringify(
                                        this.rows
                                            .filter((row) => String(row.cld_id).trim() !== '' || String(row.vimeo_id).trim() !== '')
                                            .map((row) => ({
                                                cld_id: String(row.cld_id).trim(),
                                                vimeo_id: String(row.vimeo_id).trim(),
                                            }))
                                    );
                                },

                                cldIdsOnly() {
                                    return this.rows
                                        .map((row) => String(row.cld_id).trim())
                                        .filter((id) => this.isValidCldId(id))
                                        .join('\n');
                                },
                            }));
                        });
                    </script>

                    <form
                        method="POST"
                        action="{{ route('courses.process-singles') }}"
                        class="space-y-6"
                        x-data="courseSyncGrid(@js($initialSyncRows), {{ $maxSinglesIdsLimit }})"
                    >
                        @csrf

                        <div>
                            <x-input-label for="course-sync-grid" :value="__('Courses to sync')" />
                            <p class="mt-1 text-sm text-gray-500">
                                {{ __('In Excel, put CLD ID in column A and Vimeo ID in column B. Select both columns, copy, then click the table below and paste. Leave the Vimeo cell blank or enter N/A when there is no video.') }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                <p class="text-xs text-gray-500">
                                    <span x-text="validRowCount"></span>
                                    {{ __('valid row(s)') }}
                                    ·
                                    {{ __('Maximum :max per run.', ['max' => $maxSinglesIdsLimit]) }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="addRow()"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        {{ __('Add row') }}
                                    </button>
                                    <button
                                        type="button"
                                        @click="clearAll()"
                                        class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        {{ __('Clear all') }}
                                    </button>
                                </div>
                            </div>

                            <div
                                id="course-sync-grid"
                                tabindex="0"
                                @paste.capture="handlePaste($event)"
                                class="mt-2 overflow-hidden rounded-lg border border-gray-300 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500"
                            >
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                                        <thead class="bg-gray-50 text-gray-700">
                                            <tr>
                                                <th class="w-10 px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">{{ __('#') }}</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">{{ __('CLD ID') }}</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide">{{ __('Vimeo ID') }}</th>
                                                <th class="w-12 px-3 py-2"><span class="sr-only">{{ __('Remove row') }}</span></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white">
                                            <template x-for="(row, index) in rows" :key="index">
                                                <tr
                                                    class="hover:bg-gray-50/80"
                                                    :class="{
                                                        'bg-amber-50/60': row.cld_id.trim() !== '' && ! isValidCldId(row.cld_id),
                                                        'bg-red-50/40': vimeoLooksInvalid(row.vimeo_id),
                                                    }"
                                                >
                                                    <td class="px-3 py-1.5 text-xs text-gray-400 font-mono" x-text="index + 1"></td>
                                                    <td class="px-2 py-1.5">
                                                        <input
                                                            type="text"
                                                            x-model="row.cld_id"
                                                            inputmode="numeric"
                                                            autocomplete="off"
                                                            class="block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            placeholder="{{ __('e.g. 19459') }}"
                                                        >
                                                    </td>
                                                    <td class="px-2 py-1.5">
                                                        <input
                                                            type="text"
                                                            x-model="row.vimeo_id"
                                                            autocomplete="off"
                                                            class="block w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            placeholder="1196086484 / N/A"
                                                        >
                                                    </td>
                                                    <td class="px-2 py-1.5 text-center">
                                                        <button
                                                            type="button"
                                                            @click="removeRow(index)"
                                                            class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                            :aria-label="'{{ __('Remove row') }} ' + (index + 1)"
                                                        >
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="border-t border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-500">
                                    {{ __('Click here and paste from Excel to replace all rows. You can also edit individual cells afterward.') }}
                                </div>
                            </div>

                            <p x-show="overMax" style="display: none;" class="mt-2 text-xs text-amber-700">
                                {{ __('You have more than :max valid CLD IDs. Remove some rows before submitting.', ['max' => $maxSinglesIdsLimit]) }}
                            </p>

                            <input type="hidden" name="course_sync_rows" :value="serializedRows()">
                            <input type="hidden" name="cld_ids" :value="cldIdsOnly()" required>

                            <x-input-error :messages="$errors->get('cld_ids')" class="mt-2" />
                            <x-input-error :messages="$errors->get('course_sync_rows')" class="mt-2" />
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
                                        <span class="block mt-2 text-xs text-gray-500">
                                            {{ __('FeedMe feed ID for this action: :id (set CLD_FEEDME_SINGLES_FEED_ID in .env if it should differ from the cron feed).', ['id' => $feedMeSinglesFeedId ?? (int) config('cld_api.feedme.singles_feed_id')]) }}
                                        </span>
                                        @if (empty(config('cld_api.feedme.singles_passkey')))
                                            <span class="block mt-2 text-xs text-amber-700">{{ __('FeedMe is disabled until CLD_FEEDME_SINGLES_PASSKEY or CLD_FEEDME_PASSKEY is set in .env.') }}</span>
                                        @endif
                                    </span>
                                </label>
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button type="submit" x-bind:disabled="validRowCount === 0 || overMax">
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
                                            {{ __('Send to Craft was selected, but no FeedMe passkey is configured (set CLD_FEEDME_SINGLES_PASSKEY or CLD_FEEDME_PASSKEY) — FeedMe was not triggered.') }}
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
                        <h3 class="text-lg font-medium text-gray-900 mb-2">{{ __('CLD sync runs') }}</h3>
                        <p class="text-gray-600">{{ __('Recent runs from cld:sync and cld:sync-singles (including the Courses page).') }}</p>
                    </div>

                    @php
                        try {
                            $runs = \Illuminate\Support\Facades\DB::table('cld_sync_runs')
                                ->orderByDesc('id')
                                ->limit(25)
                                ->get();
                        } catch (\Throwable $e) {
                            $runs = collect();
                        }
                    @endphp

                    @if ($runs->isEmpty())
                        <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-700 border border-gray-200">
                            {{ __('No sync runs recorded yet. Run') }} <span class="font-mono">php artisan cld:sync</span> {{ __('or') }}
                            <span class="font-mono">php artisan cld:sync-singles 7142</span> {{ __('to start populating this list.') }}
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50 text-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('When') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('Trigger') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('Mode') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('IDs') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('Result') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('FeedMe') }}</th>
                                        <th class="px-4 py-3 text-left font-medium">{{ __('Duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach ($runs as $r)
                                        @php
                                            $failed = (int) ($r->failed ?? 0);
                                            $aborted = ! empty($r->abort_reason);
                                            $ok = ! $aborted && $failed === 0;
                                        @endphp
                                        <tr class="@if ($ok) bg-white @else bg-amber-50/40 @endif">
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                {{ \Illuminate\Support\Carbon::parse($r->created_at)->diffForHumans() }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs text-gray-700">
                                                {{ $r->trigger }}
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                {{ $r->mode }}
                                            </td>
                                            <td class="px-4 py-3 text-gray-700">
                                                @if (! empty($r->requested_ids))
                                                    <div class="font-mono text-xs break-all">{{ $r->requested_ids }}</div>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                @if ($aborted)
                                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800">{{ __('Aborted') }}</span>
                                                @elseif ($failed > 0)
                                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900">
                                                        {{ __(':ok/:total ok', ['ok' => $r->succeeded ?? 0, 'total' => $r->total ?? 0]) }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900">
                                                        {{ __(':ok/:total ok', ['ok' => $r->succeeded ?? 0, 'total' => $r->total ?? 0]) }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                @php
                                                    $fmRan = (bool) ($r->feedme_ran ?? false);
                                                    $fmOk = $r->feedme_ok;
                                                    $fmCode = $r->feedme_http_code;
                                                @endphp
                                                @if (! $fmRan)
                                                    <span class="text-gray-400">—</span>
                                                @elseif ($fmOk)
                                                    <span class="text-emerald-800">{{ __('OK') }}</span>
                                                    <span class="text-gray-400 text-xs">({{ $fmCode ?? '?' }})</span>
                                                @else
                                                    <span class="text-amber-900">{{ __('Check') }}</span>
                                                    <span class="text-gray-400 text-xs">({{ $fmCode ?? '?' }})</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">
                                                @if (! empty($r->duration_ms))
                                                    {{ number_format(((int) $r->duration_ms) / 1000, 1) }}s
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                        </tr>
                                        @if (! empty($r->abort_reason))
                                            <tr>
                                                <td class="px-4 pb-4 pt-0 text-xs text-red-800" colspan="7">
                                                    <span class="font-medium">{{ __('Abort reason:') }}</span>
                                                    {{ $r->abort_reason }}
                                                </td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
