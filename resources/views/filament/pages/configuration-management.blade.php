<x-filament-panels::page>
  @if($hasSyntheticState)
    <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950/40 dark:text-amber-200">
      <strong>CHG-003 Staging Synthetic State Active:</strong> Database contains synthetic staging fixtures. Production configuration export remains strictly blocked until replaced with client-approved fleet, imagery, rates, and legal inputs.
    </div>
  @endif

  <div class="grid gap-6">
    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Active Configuration Snapshot</h2>
      @if($activeSnapshot)
        <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-4 text-sm">
          <div>
            <dt class="text-gray-500">Version</dt>
            <dd class="font-mono font-semibold">v{{ $activeSnapshot->version_number }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Classification</dt>
            <dd>{{ $activeSnapshot->package_classification->label() }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Checksum (SHA-256)</dt>
            <dd class="font-mono text-xs break-all">{{ $activeSnapshot->checksum_sha256 }}</dd>
          </div>
          <div>
            <dt class="text-gray-500">Applied (UTC)</dt>
            <dd class="font-mono">{{ $activeSnapshot->applied_at?->toIso8601String() ?? '—' }}</dd>
          </div>
        </dl>
      @else
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No configuration snapshot has been imported yet. Use <code>php artisan config:import-package</code>.</p>
      @endif
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Provider &amp; Integration Readiness (Non-Secret)</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-800">
            <tr>
              <th class="py-2 pr-4">Capability</th>
              <th class="py-2 pr-4">Adapter</th>
              <th class="py-2 pr-4">State</th>
              <th class="py-2 pr-4">Missing Keys</th>
              <th class="py-2">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($providers as $provider)
              <tr>
                <td class="py-2 pr-4 font-medium">{{ $provider['capability'] }}</td>
                <td class="py-2 pr-4 font-mono text-xs">{{ $provider['provider'] }}</td>
                <td class="py-2 pr-4">{{ $provider['state'] }}</td>
                <td class="py-2 pr-4 font-mono text-xs">{{ $provider['missing_keys'] === [] ? '—' : implode(', ', $provider['missing_keys']) }}</td>
                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $provider['notes'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Snapshot History</h2>
      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b border-gray-200 text-gray-500 dark:border-gray-800">
            <tr>
              <th class="py-2 pr-4">Version</th>
              <th class="py-2 pr-4">Classification</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2 pr-4">Checksum</th>
              <th class="py-2">Notes</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($snapshots as $snap)
              <tr>
                <td class="py-2 pr-4 font-mono">v{{ $snap->version_number }}</td>
                <td class="py-2 pr-4">{{ $snap->package_classification->label() }}</td>
                <td class="py-2 pr-4">{{ $snap->is_active ? 'Active' : 'Historical' }}</td>
                <td class="py-2 pr-4 font-mono text-xs">{{ substr($snap->checksum_sha256, 0, 16) }}…</td>
                <td class="py-2 text-gray-600 dark:text-gray-400">{{ $snap->notes ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="py-4 text-center text-gray-500">No snapshots recorded yet.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>
  </div>
</x-filament-panels::page>

