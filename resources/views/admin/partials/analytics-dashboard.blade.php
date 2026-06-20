<section class="mt-8 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
    <div class="flex flex-col gap-5">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-zinc-900">Internal Analytics</h2>
                <p class="text-sm text-zinc-500">Admin and security view with raw IP visibility, repeat traffic, and bot monitoring.</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            @foreach([7, 30, 90] as $period)
                <div class="rounded-lg border border-zinc-200 p-4">
                    <p class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-500">{{ $period }} day window</p>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <p class="text-zinc-500">Visitors</p>
                            <p class="mt-1 text-2xl font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['visitors']) }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500">Page views</p>
                            <p class="mt-1 text-2xl font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['page_views']) }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500">Events</p>
                            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['events']) }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500">Bots</p>
                            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['bot_visits']) }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500">Humans</p>
                            <p class="mt-1 text-lg font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['human_visitors']) }}</p>
                        </div>
                        <div>
                            <p class="text-zinc-500">Human vs bot</p>
                            <p class="mt-1 text-sm font-semibold text-zinc-900">{{ number_format($admin_analytics['periods'][$period]['human_percentage'], 1) }}% / {{ number_format($admin_analytics['periods'][$period]['bot_percentage'], 1) }}%</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Page Views Per Day</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['page_views_per_day'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ \Illuminate\Support\Carbon::parse($row->day)->format('d M Y') }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No page views recorded yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Events Per Day</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['events_per_day'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ \Illuminate\Support\Carbon::parse($row->day)->format('d M Y') }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No events recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top Page Types</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_page_types'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ $row->page_type }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No page type data yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top Landing Pages</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_landing_pages'] as $row)
                        <div class="flex items-center justify-between gap-4 rounded-md bg-zinc-50 px-3 py-2">
                            <span class="truncate">{{ $row->landing_page }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No landing pages recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top Events</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_events'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ $row->event_type }} / {{ $row->event_key }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No event data yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top IP Addresses</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_ip_addresses'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ $row->ip_address }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total_visits) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No IP data yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Repeat Visitors By IP</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['repeat_visitors_by_ip'] as $row)
                        <div class="rounded-md bg-zinc-50 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span>{{ $row->ip_address }}</span>
                                <span class="font-medium">{{ number_format((int) $row->unique_visitors) }} visitors</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">{{ number_format((int) $row->total_visits) }} total visits</p>
                        </div>
                    @empty
                        <p class="text-zinc-500">No repeat IPs yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-zinc-900">Bot Classification</h3>
                    <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">{{ number_format((float) $admin_analytics['traffic_split']['bot_percentage'], 1) }}% bots</span>
                </div>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="rounded-md bg-zinc-50 px-3 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-zinc-500">Human Visits</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format((int) $admin_analytics['human_traffic_count']) }}</p>
                    </div>
                    <div class="rounded-md bg-zinc-50 px-3 py-3">
                        <p class="text-xs uppercase tracking-[0.18em] text-zinc-500">Bot Visits</p>
                        <p class="mt-2 text-2xl font-semibold text-zinc-900">{{ number_format((int) $admin_analytics['bot_traffic_count']) }}</p>
                    </div>
                </div>
                <p class="mt-3 text-xs text-zinc-500">
                    {{ number_format((int) $admin_analytics['traffic_split']['human']) }} humans / {{ number_format((int) $admin_analytics['traffic_split']['bots']) }} bots in the selected window.
                </p>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-zinc-900">Bot Traffic</h3>
                    <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700">{{ number_format((int) $admin_analytics['bot_traffic_count']) }} bot visits</span>
                </div>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['suspicious_high_frequency_ips'] as $row)
                        <div class="rounded-md bg-zinc-50 px-3 py-2">
                            <div class="flex items-center justify-between">
                                <span>{{ $row->ip_address }}</span>
                                <span class="font-medium">{{ number_format((int) $row->page_view_count) }} views</span>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500">
                                {{ number_format((int) $row->unique_visitors) }} anon IDs · last seen {{ \Illuminate\Support\Carbon::parse($row->last_seen_at)->format('d M Y H:i') }}
                            </p>
                        </div>
                    @empty
                        <p class="text-zinc-500">No suspicious high-frequency IPs in the last 24 hours.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top Bot Names</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_bot_names'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ $row->bot_name }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No bot names recorded yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-lg border border-zinc-200 p-4">
                <h3 class="text-sm font-semibold text-zinc-900">Top Bot IPs</h3>
                <div class="mt-4 space-y-2 text-sm text-zinc-700">
                    @forelse($admin_analytics['top_bot_ips'] as $row)
                        <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2">
                            <span>{{ $row->ip_address }}</span>
                            <span class="font-medium">{{ number_format((int) $row->total_visits) }}</span>
                        </div>
                    @empty
                        <p class="text-zinc-500">No bot IP data yet.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-zinc-200 p-4">
            <h3 class="text-sm font-semibold text-zinc-900">Recent Visits</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-zinc-200 text-sm">
                    <thead>
                        <tr class="text-left text-zinc-500">
                            <th class="py-2 pr-4 font-medium">IP</th>
                            <th class="py-2 pr-4 font-medium">Country</th>
                            <th class="py-2 pr-4 font-medium">Device</th>
                            <th class="py-2 pr-4 font-medium">Browser</th>
                            <th class="py-2 pr-4 font-medium">Landing page</th>
                            <th class="py-2 pr-4 font-medium">Bot</th>
                            <th class="py-2 pr-4 font-medium">Bot name</th>
                            <th class="py-2 pr-4 font-medium">Last seen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-100">
                        @forelse($admin_analytics['recent_visits'] as $visit)
                            <tr class="text-zinc-700">
                                <td class="py-3 pr-4 font-medium text-zinc-900">{{ $visit->ip_address ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ $visit->country_code ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ $visit->device_type ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ $visit->browser ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ $visit->landing_page ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ $visit->is_bot ? 'Yes' : 'No' }}</td>
                                <td class="py-3 pr-4">{{ $visit->bot_name ?? 'n/a' }}</td>
                                <td class="py-3 pr-4">{{ optional($visit->last_seen_at)->format('d M Y H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-4 text-zinc-500">No recent visits recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>
