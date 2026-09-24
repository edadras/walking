<x-filament-panels::page>
    @php($locations = $this->getLocations())
    @if ($locations->isEmpty())
        <x-filament::section>
            هنوز شعبه تأییدشده‌ای ندارید. ابتدا یک شعبه ثبت کنید و منتظر تأیید بمانید.
        </x-filament::section>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <label for="location" class="text-sm font-medium">شعبه:</label>
            <x-filament::input.wrapper class="min-w-64">
                <x-filament::input.select id="location" wire:model.live="locationId">
                    @foreach ($locations as $location)
                        <option value="{{ $location->public_id }}">{{ $location->name }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
            <x-filament::button color="gray" icon="heroicon-o-arrows-pointing-out"
                x-on:click="document.getElementById('gy-qr').requestFullscreen?.()">
                تمام صفحه
            </x-filament::button>
        </div>

        @php($code = $this->getCode())
        {{-- Re-render a little after each window rolls over. --}}
        <div wire:poll.{{ max(2, min(10, $code['seconds_left'] + 1)) }}s id="gy-qr"
             class="mx-auto flex w-full max-w-xl flex-col items-center gap-4 rounded-2xl bg-white p-8 text-center text-gray-900">
            <p class="text-lg font-bold">{{ $this->getLocation()?->name }}</p>
            <div class="w-full max-w-sm [&_svg]:h-auto [&_svg]:w-full">{!! $code['svg'] !!}</div>
            <p class="text-base">در اپ گام‌یار «اسکن QR شعبه» را بزنید.</p>
            <p class="text-sm text-gray-500">این کد هر {{ $code['window'] }} ثانیه عوض می‌شود و عکس آن کار نمی‌کند.</p>
        </div>
    @endif
</x-filament-panels::page>
