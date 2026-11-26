<x-filament-panels::page>
  @php
    $data = $this->getViewData();
    $heading = $data['heading'];
    $items = $data['items'];
    $vehicles = $data['vehicles'];
    $alerts = $data['alerts'];
  @endphp

  <style>
    /* WCAG 2.2 AA Non-Color Discrimination Border Patterns */
    .border-pattern-solid { border: 2px solid currentColor; }
    .border-pattern-dashed { border: 2px dashed currentColor; }
    .border-pattern-dotted { border: 2px dotted currentColor; }
    .border-pattern-double { border: 3px double currentColor; }
    .border-pattern-diagonal_hatch {
      border: 2px solid currentColor;
      background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0, 0, 0, 0.08) 4px, rgba(0, 0, 0, 0.08) 8px);
    }
    .dark .border-pattern-diagonal_hatch {
      background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(255, 255, 255, 0.12) 4px, rgba(255, 255, 255, 0.12) 8px);
    }
    .border-pattern-cross_hatch {
      border: 2px solid currentColor;
      background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(0, 0, 0, 0.08) 4px, rgba(0, 0, 0, 0.08) 8px),
                        repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(0, 0, 0, 0.08) 4px, rgba(0, 0, 0, 0.08) 8px);
    }
    .dark .border-pattern-cross_hatch {
      background-image: repeating-linear-gradient(45deg, transparent, transparent 4px, rgba(255, 255, 255, 0.12) 4px, rgba(255, 255, 255, 0.12) 8px),
                        repeating-linear-gradient(-45deg, transparent, transparent 4px, rgba(255, 255, 255, 0.12) 4px, rgba(255, 255, 255, 0.12) 8px);
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, minmax(0, 1fr));
      gap: 1px;
      background-color: #cbd5e1;
      border: 1px solid #cbd5e1;
      border-radius: 0.5rem;
      overflow: hidden;
    }
    .dark .calendar-grid {
      background-color: #334155;
      border-color: #334155;
    }
    .calendar-cell {
      background-color: #ffffff;
      min-height: 120px;
      padding: 0.5rem;
      display: flex;
      flex-direction: column;
    }
    .dark .calendar-cell {
      background-color: #0f172a;
    }
    .calendar-cell-other-month {
      background-color: #f8fafc;
      opacity: 0.6;
    }
    .dark .calendar-cell-other-month {
      background-color: #020617;
    }
    .cal-block-item {
      border-radius: 0.375rem;
      padding: 0.25rem 0.375rem;
      margin-bottom: 0.375rem;
      font-size: 0.75rem;
      line-height: 1.25;
      position: relative;
    }
  </style>

  <!-- Section 1: Operational Alerts Banner -->
  @if (!empty($alerts))
    <section aria-labelledby="alerts-heading" class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-950">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
          <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 text-amber-700 dark:text-amber-400" />
          <h2 id="alerts-heading" class="font-bold text-amber-900 dark:text-amber-200 text-sm tracking-tight">
            Operational Alerts &amp; Scheduling Friction ({{ count($alerts) }})
          </h2>
        </div>
        <span class="text-xs font-medium text-amber-700 dark:text-amber-400">Action Required</span>
      </div>

      <div class="space-y-2">
        @foreach ($alerts as $alert)
          <div class="flex items-start justify-between rounded-lg border bg-white p-2.5 shadow-sm dark:bg-gray-900 {{ $alert->severity->value === 'critical' ? 'border-rose-400 text-rose-950 dark:border-rose-600 dark:text-rose-200' : 'border-amber-300 text-amber-950 dark:border-amber-600 dark:text-amber-200' }}">
            <div class="flex items-start gap-2">
              <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold tracking-wide uppercase {{ $alert->severity->value === 'critical' ? 'bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900 dark:text-amber-200' }}">
                {{ $alert->severity->label() }}
              </span>
              <div>
                <strong class="font-semibold text-xs">{{ $alert->title }}</strong>
                <p class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ $alert->description }}</p>
              </div>
            </div>
            @if ($alert->booking_reference)
              <span class="text-xs font-mono font-bold text-gray-600 dark:text-gray-300 ml-2">
                {{ $alert->booking_reference }}
              </span>
            @endif
          </div>
        @endforeach
      </div>
    </section>
  @endif

  <!-- Section 2: Command & Control Toolbar -->
  <header class="flex flex-wrap items-center justify-between gap-4 mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    <!-- View Mode Switcher -->
    <div class="inline-flex rounded-lg border border-gray-200 p-1 dark:border-gray-700 dark:bg-gray-800" role="tablist" aria-label="Calendar view format">
      @foreach (['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'] as $modeKey => $modeLabel)
        <button
          type="button"
          wire:click="setViewMode('{{ $modeKey }}')"
          class="rounded-md px-3 py-1.5 text-xs font-semibold transition-colors {{ $viewMode === $modeKey ? 'bg-primary-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white' }}"
          role="tab"
          aria-selected="{{ $viewMode === $modeKey ? 'true' : 'false' }}">
          {{ $modeLabel }}
        </button>
      @endforeach
    </div>

    <!-- Date Period Navigation -->
    <div class="flex items-center gap-2">
      <button
        type="button"
        wire:click="previousPeriod"
        class="inline-flex items-center justify-center p-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        aria-label="Previous time interval">
        <x-filament::icon icon="heroicon-o-chevron-left" class="h-4 w-4" />
      </button>

      <button
        type="button"
        wire:click="today"
        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">
        Today
      </button>

      <button
        type="button"
        wire:click="nextPeriod"
        class="inline-flex items-center justify-center p-2 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
        aria-label="Next time interval">
        <x-filament::icon icon="heroicon-o-chevron-right" class="h-4 w-4" />
      </button>

      <h1 class="text-base font-bold text-gray-900 dark:text-white ml-2 tracking-tight">
        {{ $heading }}
      </h1>
    </div>

    <!-- Vehicle Filter Dropdown -->
    <div class="flex items-center gap-2">
      <label for="vehicle-filter" class="text-xs font-semibold text-gray-600 dark:text-gray-400">Fleet Vehicle:</label>
      <select
        id="vehicle-filter"
        wire:change="setVehicleId($event.target.value)"
        class="rounded-lg border-gray-300 text-xs font-medium text-gray-900 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
        <option value="">All Vehicles (Entire 5-Car Fleet)</option>
        @foreach ($vehicles as $veh)
          <option value="{{ $veh->id }}" {{ $vehicleId === $veh->id ? 'selected' : '' }}>
            {{ $veh->make }} {{ $veh->model }} ({{ $veh->license_plate }})
          </option>
        @endforeach
      </select>
    </div>
  </header>

  <!-- Section 3: Calendar / Agenda Surface -->
  <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
    @if ($viewMode === 'month' || $viewMode === 'week')
      <!-- Day Headers (Monday to Sunday) -->
      <div class="grid grid-cols-7 gap-1 text-center font-bold text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 py-2 border-b border-gray-200 dark:border-gray-800 mb-1">
        <div>Mon</div>
        <div>Tue</div>
        <div>Wed</div>
        <div>Thu</div>
        <div>Fri</div>
        <div>Sat</div>
        <div>Sun</div>
      </div>

      <!-- Month / Week Grid -->
      <div class="calendar-grid">
        @foreach ($items as $dateKey => $cellBlocks)
          @php
            $cellCarbon = \Carbon\Carbon::parse($dateKey, 'Asia/Colombo');
            $isToday = $cellCarbon->isToday();
            $isCurrentMonth = $cellCarbon->format('Y-m') === \Carbon\Carbon::parse($activeDate, 'Asia/Colombo')->format('Y-m');
          @endphp
          <div class="calendar-cell {{ $viewMode === 'month' && !$isCurrentMonth ? 'calendar-cell-other-month' : '' }}">
            <div class="flex items-center justify-between mb-1.5 pb-1 border-b border-gray-100 dark:border-gray-800">
              <span class="text-xs font-bold {{ $isToday ? 'bg-primary-600 text-white rounded-full w-5 h-5 inline-flex items-center justify-center' : 'text-gray-700 dark:text-gray-300' }}">
                {{ $cellCarbon->format('j') }}
              </span>
              <span class="text-[10px] text-gray-400 font-medium">
                {{ count($cellBlocks) > 0 ? count($cellBlocks).' block(s)' : '' }}
              </span>
            </div>

            <div class="flex-1 space-y-1 overflow-y-auto">
              @foreach ($cellBlocks as $block)
                <div class="cal-block-item border-pattern-{{ $block->border_style }} {{ $block->badge_class }} {{ $block->has_conflict ? 'ring-2 ring-rose-500 ring-offset-1' : '' }}">
                  <div class="flex items-center justify-between gap-1 font-bold">
                    <span class="inline-flex items-center gap-0.5 truncate">
                      <x-filament::icon icon="{{ $block->kind_icon }}" class="h-3 w-3 inline shrink-0" />
                      <span>{{ $block->starts_at->format('H:i') }}</span>
                    </span>
                    <span class="text-[9px] uppercase tracking-wide px-1 py-0.2 rounded bg-black/10 dark:bg-white/10 shrink-0">
                      {{ $block->kind_label }}
                    </span>
                  </div>

                  <div class="font-medium text-[11px] truncate mt-0.5">
                    {{ $block->vehicle_plate }} &bull; {{ $block->service_label }}
                  </div>

                  @if ($block->driver_name)
                    <div class="text-[10px] opacity-90 truncate mt-0.5 flex items-center gap-1">
                      <span>👤 {{ $block->driver_name }}</span>
                      @if ($block->is_replacement)
                        <span class="text-[9px] font-bold text-amber-700 dark:text-amber-300">(Rep)</span>
                      @endif
                    </div>
                  @endif

                  @if ($block->has_conflict)
                    <div class="mt-1 flex items-center gap-0.5 text-[9px] font-bold text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-950/80 px-1 py-0.5 rounded">
                      <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-2.5 w-2.5 inline" />
                      <span>Schedule Conflict</span>
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          </div>
        @endforeach
      </div>

    @elseif ($viewMode === 'day')
      <!-- Day Timeline / List View -->
      <div class="space-y-3">
        @if (empty($items))
          <div class="text-center py-12 text-gray-500 text-sm">
            No schedule blocks allocated for this date.
          </div>
        @else
          @foreach ($items as $block)
            <div class="rounded-lg border-pattern-{{ $block->border_style }} {{ $block->badge_class }} p-3 {{ $block->has_conflict ? 'ring-2 ring-rose-500' : '' }}">
              <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                  <x-filament::icon icon="{{ $block->kind_icon }}" class="h-5 w-5" />
                  <span class="font-bold text-sm">{{ $block->starts_at->format('h:i A') }} – {{ $block->ends_at->format('h:i A') }}</span>
                  <span class="px-2 py-0.5 rounded text-xs font-bold uppercase tracking-wider bg-black/10">
                    {{ $block->kind_label }}
                  </span>
                </div>
                <div class="font-mono text-xs font-bold">
                  {{ $block->vehicle_name }} ({{ $block->vehicle_plate }})
                </div>
              </div>

              <div class="mt-2 text-xs text-gray-800 dark:text-gray-200">
                <strong>Service:</strong> {{ $block->service_label }}
                @if ($block->booking_reference)
                  &bull; <strong>Ref:</strong> {{ $block->booking_reference }}
                @endif
                @if ($block->driver_name)
                  &bull; <strong>Driver:</strong> {{ $block->driver_name }} {{ $block->is_replacement ? '(Replacement Chauffeur)' : '(Tied Driver)' }}
                @endif
              </div>

              @if ($block->notes)
                <p class="mt-1 text-xs italic opacity-85">Note: {{ $block->notes }}</p>
              @endif

              @if ($block->has_conflict)
                <div class="mt-2 inline-flex items-center gap-1 text-xs font-bold text-rose-700 dark:text-rose-300 bg-rose-100 dark:bg-rose-950 px-2 py-1 rounded">
                  <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-3.5 w-3.5" />
                  <span>Critical: Vehicle schedule overlaps with another active block!</span>
                </div>
              @endif
            </div>
          @endforeach
        @endif
      </div>

    @elseif ($viewMode === 'agenda')
      <!-- Agenda View -->
      <div class="space-y-6">
        @foreach ($items as $dateKey => $agendaItems)
          @php
            $dateCarbon = \Carbon\Carbon::parse($dateKey, 'Asia/Colombo');
          @endphp
          <div>
            <h3 class="font-bold text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 pb-1 border-b border-gray-200 dark:border-gray-800 mb-2">
              {{ $dateCarbon->format('l, d F Y') }} ({{ count($agendaItems) }} trip(s))
            </h3>

            @if (empty($agendaItems))
              <div class="text-xs text-gray-400 italic py-1">No scheduled journeys</div>
            @else
              <div class="space-y-2">
                @foreach ($agendaItems as $trip)
                  <div class="rounded-lg border border-gray-200 p-3 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 {{ $trip->has_conflict ? 'border-rose-400' : '' }}">
                    <div class="flex items-center justify-between gap-2">
                      <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-primary-600 dark:text-primary-400">{{ $trip->time_formatted }}</span>
                        <span class="text-xs font-semibold text-gray-900 dark:text-white">{{ $trip->service_label }}</span>
                        @if ($trip->booking_reference)
                          <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded font-bold">
                            {{ $trip->booking_reference }}
                          </span>
                        @endif
                      </div>

                      <div class="text-xs font-mono font-medium text-gray-600 dark:text-gray-300">
                        {{ $trip->vehicle_plate }}
                      </div>
                    </div>

                    <div class="mt-2 text-xs text-gray-600 dark:text-gray-400">
                      <strong>Route:</strong> {{ $trip->origin_text }}
                      @if ($trip->destination_text)
                        &rarr; {{ $trip->destination_text }}
                      @endif
                    </div>

                    <div class="mt-2 flex items-center justify-between text-xs pt-2 border-t border-gray-100 dark:border-gray-800">
                      <div>
                        @if ($trip->driver_name)
                          <span>Chauffeur: <strong>{{ $trip->driver_name }}</strong> {{ $trip->is_replacement_driver ? '(Replacement)' : '' }}</span>
                        @elseif ($trip->service_type === 'rent')
                          <span class="text-gray-500 font-medium">Self-Drive Rental</span>
                        @elseif ($trip->booking_reference)
                          <span class="text-rose-600 font-semibold">Chauffeur: Unassigned</span>
                        @else
                          <span class="text-gray-400 font-medium">Operational Block</span>
                        @endif
                      </div>
                      @if ($trip->trip_status_label)
                        <span class="font-semibold text-primary-700 dark:text-primary-300">
                          Status: {{ $trip->trip_status_label }}
                        </span>
                      @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @endif
  </div>
</x-filament-panels::page>

