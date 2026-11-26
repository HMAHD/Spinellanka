<div class="space-y-4 text-sm">
    <div class="p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700">
        <p class="font-medium text-gray-900 dark:text-gray-100">Proposal: <span class="font-bold">{{ $proposal->reference }}</span> (v{{ $proposal->version }})</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Status: {{ $proposal->status->label() }} &bull; Response Deadline: {{ $proposal->expires_at->format('d M Y H:i') }}</p>
    </div>

    @if(!empty($comparison))
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse border border-gray-200 dark:border-gray-700">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        <th class="p-2 border border-gray-200 dark:border-gray-700 font-semibold">Attribute</th>
                        <th class="p-2 border border-gray-200 dark:border-gray-700 font-semibold">Initial Request</th>
                        <th class="p-2 border border-gray-200 dark:border-gray-700 font-semibold">Proposed Offer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($comparison as $item)
                        <tr class="{{ $item['changed'] ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                            <td class="p-2 border border-gray-200 dark:border-gray-700 font-medium text-gray-900 dark:text-gray-100">
                                {{ $item['label'] }}
                                @if($item['changed'])
                                    <span class="ml-1 text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200 font-semibold">Adjusted</span>
                                @endif
                            </td>
                            <td class="p-2 border border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-400">{{ $item['original'] }}</td>
                            <td class="p-2 border border-gray-200 dark:border-gray-700 {{ $item['changed'] ? 'font-semibold text-emerald-700 dark:text-emerald-400' : 'text-gray-900 dark:text-gray-100' }}">
                                {{ $item['proposed'] }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-gray-500 italic">No comparison attributes available.</p>
    @endif
</div>

