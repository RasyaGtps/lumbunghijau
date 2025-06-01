<x-filament::page>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <x-filament::card>
            <h2 class="text-xl font-semibold">Total Users</h2>
            <p class="text-3xl">{{ $stats['users'] }}</p>
        </x-filament::card>

        <x-filament::card>
            <h2 class="text-xl font-semibold">Total Transactions</h2>
            <p class="text-3xl">{{ $stats['transactions'] }}</p>
        </x-filament::card>

        <x-filament::card>
            <h2 class="text-xl font-semibold">Waste Categories</h2>
            <p class="text-3xl">{{ $stats['categories'] }}</p>
        </x-filament::card>
    </div>
</x-filament::page>
