<x-filament-panels::page>
    @php
        $summary = $this->summary();
        $peak = $this->peakToday();
        $peaks = $this->peaks();
        $max = max(1, $peaks->max('peak'));
        $recent = $this->recent();
    @endphp

    @if ($summary || $peak !== null)
        <div style="display:flex;flex-wrap:wrap;gap:6px 18px;align-items:baseline;font-size:.875rem;opacity:.85">
            @if ($summary)<span>{{ $summary }}</span>@endif
            @if ($peak !== null)<span>{{ trans('player-manager::strings.summary.peak_today', ['count' => $peak]) }}</span>@endif
        </div>
    @endif

    {{ $this->table }}

    @if ($this->lastOutput !== null)
        <x-filament::section icon="tabler-terminal-2" :heading="trans('player-manager::strings.output')" collapsible>
            <div style="font-size:.75rem;opacity:.7;margin-bottom:6px;font-family:ui-monospace,monospace">&gt; {{ $this->lastCommand }}</div>
            <pre style="white-space:pre-wrap;word-break:break-word;font-size:.8rem;line-height:1.5;margin:0;font-family:ui-monospace,monospace;max-height:320px;overflow:auto">{{ $this->lastOutput }}</pre>
        </x-filament::section>
    @endif

    @if (config('player-manager.history', true) && !$this->disabled())
        <div style="display:grid;gap:1.5rem;align-items:start;grid-template-columns:repeat(auto-fit,minmax(min(100%,22rem),1fr))">
            <x-filament::section icon="tabler-chart-bar" :heading="trans('player-manager::strings.history.peaks')" :description="trans('player-manager::strings.history.peaks_help')">
                <div style="display:flex;align-items:flex-end;gap:4px;height:120px" role="img" aria-label="{{ trans('player-manager::strings.history.peaks') }}">
                    @foreach ($peaks as $p)
                        <div title="{{ \Illuminate\Support\Carbon::parse($p['day'])->isoFormat('ddd D MMM') }} : {{ $p['peak'] }}" style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;gap:4px">
                            <span style="font-size:.65rem;opacity:.7">{{ $p['peak'] ?: '' }}</span>
                            <div style="width:100%;max-width:26px;border-radius:3px;background:var(--primary-500);opacity:{{ $loop->last ? '1' : '.55' }};height:{{ max(2, round($p['peak'] / $max * 90)) }}px"></div>
                        </div>
                    @endforeach
                </div>
                <div style="display:flex;justify-content:space-between;font-size:.7rem;opacity:.6;margin-top:6px">
                    <span>{{ \Illuminate\Support\Carbon::parse($peaks->first()['day'])->isoFormat('D MMM') }}</span>
                    <span>{{ trans('player-manager::strings.history.today') }}</span>
                </div>
            </x-filament::section>

            <x-filament::section icon="tabler-history" :heading="trans('player-manager::strings.history.recent')" :description="trans('player-manager::strings.history.recent_help')">
                @forelse ($recent as $s)
                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:.85rem;padding:5px 0;border-bottom:1px solid rgba(127,127,127,.15)">
                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $s->name }}</span>
                        <span style="opacity:.65;white-space:nowrap" title="{{ $s->last_seen_at->toDateTimeString() }}">
                            {{ $s->last_seen_at->gt(now()->subMinutes(2)) ? trans('player-manager::strings.history.online') : $s->last_seen_at->diffForHumans() }}
                        </span>
                    </div>
                @empty
                    <p style="font-size:.85rem;opacity:.7">{{ trans('player-manager::strings.history.empty') }}</p>
                @endforelse
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
