<x-filament-widgets::widget>
    <x-filament::section heading="Ruleهای پرتکرار (۷ روز)">
        @php($rows = $this->getRows())
        @if (empty($rows))
            <p class="text-sm text-gray-500">در این بازه سیگنالی ثبت نشده است.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-gray-500">
                        <th class="py-1 text-start font-medium">Rule</th>
                        <th class="py-1 text-start font-medium">رخداد</th>
                        <th class="py-1 text-start font-medium">کاربر</th>
                        <th class="py-1 text-start font-medium">میانگین ریسک</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-gray-100 dark:border-white/5">
                            <td class="py-1.5">{{ $row['name'] }}</td>
                            <td class="py-1.5">{{ number_format($row['events']) }}</td>
                            <td class="py-1.5">{{ number_format($row['users']) }}</td>
                            <td class="py-1.5">{{ $row['avg'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
