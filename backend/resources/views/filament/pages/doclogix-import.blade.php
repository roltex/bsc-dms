<x-filament-panels::page>
    <form wire:submit="runImport" class="space-y-6">
        {{ $this->form }}

        <div class="flex justify-end">
            <x-filament::button type="submit" color="primary">
                Run Import
            </x-filament::button>
        </div>
    </form>

    @if($importResult)
        <div class="mt-6 p-4 rounded-lg border {{ ($importResult['status'] ?? '') === 'success' ? 'bg-green-50 border-green-200' : (($importResult['status'] ?? '') === 'partial' ? 'bg-yellow-50 border-yellow-200' : 'bg-red-50 border-red-200') }}">
            <h3 class="font-semibold text-lg mb-2">Import Results</h3>

            @if(isset($importResult['stats']))
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4">
                    @foreach($importResult['stats'] as $key => $value)
                        <div class="bg-white rounded p-2 shadow-sm">
                            <span class="text-xs text-gray-500">{{ str_replace('_', ' ', ucfirst($key)) }}</span>
                            <span class="block text-lg font-bold">{{ $value }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if(!empty($importResult['errors']))
                <details class="mt-3">
                    <summary class="text-sm text-red-600 cursor-pointer font-medium">
                        {{ count($importResult['errors']) }} errors (click to expand)
                    </summary>
                    <ul class="mt-2 space-y-1 text-sm text-red-700">
                        @foreach($importResult['errors'] as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </details>
            @endif
        </div>
    @endif
</x-filament-panels::page>
