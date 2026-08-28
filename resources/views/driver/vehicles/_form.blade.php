<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="plate_number" class="block text-sm font-semibold text-gray-800">Plate number</label>
        <div class="mt-2 flex gap-2"><input
            id="plate_number"
            type="text"
            value="{{ old('plate_number', $vehicle?->plate_number) }}"
            x-model="fields.plate_number"
            maxlength="20"
            required
            autocomplete="off"
            placeholder="e.g. VAB 1234"
            readonly
            class="block min-w-0 flex-1 cursor-not-allowed rounded-xl border-gray-300 bg-gray-100 uppercase text-gray-700 shadow-none"
            aria-describedby="plate_number_help @error('plate_number') plate_number_error @enderror"
        ><button type="button" @click="requestRevalidation('plate')" :disabled="revalidationMode && revalidationMode !== 'plate'" :class="revalidationMode === 'plate' ? 'bg-green-50' : ''" class="shrink-0 rounded-xl border border-green-700 px-4 text-sm font-semibold text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-100 disabled:text-gray-400">Redetect plate</button></div>
        <input type="hidden" name="plate_number" :value="plateRedetectionRequested && detectedPlatePreview ? detectedPlatePreview : fields.plate_number">
        <p id="plate_number_help" class="mt-1.5 text-sm text-gray-500">Letters, numbers, spaces, and hyphens only.</p>
        @error('plate_number')
            <p id="plate_number_error" class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="brand" class="block text-sm font-semibold text-gray-800">Brand</label>
        <input
            id="brand"
            name="brand"
            type="text"
            value="{{ old('brand', $vehicle?->brand) }}"
            x-model="fields.brand"
            maxlength="50"
            required
            placeholder="e.g. Perodua"
            readonly
            class="mt-2 block w-full cursor-not-allowed rounded-xl border-gray-300 bg-gray-100 text-gray-700 shadow-none"
        >
        @error('brand')
            <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="model" class="block text-sm font-semibold text-gray-800">Model</label>
        <input
            id="model"
            name="model"
            type="text"
            value="{{ old('model', $vehicle?->model) }}"
            x-model="fields.model"
            maxlength="50"
            required
            placeholder="e.g. Myvi"
            readonly
            class="mt-2 block w-full cursor-not-allowed rounded-xl border-gray-300 bg-gray-100 text-gray-700 shadow-none"
        >
        <button type="button" @click="requestRevalidation('model')" :disabled="revalidationMode && revalidationMode !== 'model'" :class="revalidationMode === 'model' ? 'bg-green-50' : ''" class="mt-2 w-full rounded-xl border border-green-700 px-4 py-2.5 text-sm font-semibold text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-100 disabled:text-gray-400">Rescan model</button>
        @error('model')
            <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="colour" class="block text-sm font-semibold text-gray-800">Colour</label>
        <div class="mt-2 flex gap-2"><input id="colour" name="colour" type="text" value="{{ old('colour', $vehicle?->colour) }}" x-model="fields.colour" maxlength="20" required readonly class="block min-w-0 flex-1 cursor-not-allowed rounded-xl border-gray-300 bg-gray-100 text-gray-700 shadow-none"><button type="button" @click="requestRevalidation('colour')" :disabled="revalidationMode && revalidationMode !== 'colour'" :class="revalidationMode === 'colour' ? 'bg-green-50' : ''" class="shrink-0 rounded-xl border border-green-700 px-4 text-sm font-semibold text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-100 disabled:text-gray-400">Redetect colour</button></div>
        <p class="mt-1.5 text-sm text-gray-500">Colour can only be updated using validated front, rear, and side photos.</p>
        @error('colour')
            <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="md:col-span-2">
        <label for="seat_capacity" class="block text-sm font-semibold text-gray-800">Passenger seats</label>
        <select
            id="seat_capacity"
            name="seat_capacity"
            required
            class="mt-2 block w-full rounded-xl border-gray-300 shadow-none focus:border-[#2E7D32] focus:ring-[#2E7D32]"
        >
            @foreach (range(1, 4) as $seats)
                <option value="{{ $seats }}" @selected((int) old('seat_capacity', $vehicle?->seat_capacity ?? 4) === $seats)>
                    {{ $seats }} {{ Str::plural('seat', $seats) }}
                </option>
            @endforeach
        </select>
        <p class="mt-1.5 text-sm text-gray-500">Available passenger seats, excluding the driver.</p>
        @error('seat_capacity')
            <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <section x-ref="revalidationSection" x-cloak x-show="identityChanged" x-transition class="scroll-mt-6 space-y-6 rounded-2xl border border-amber-200 bg-amber-50/50 p-5 md:col-span-2">
        <div class="flex items-start justify-between gap-4"><div><h3 class="font-bold text-gray-900">Revalidation required</h3><p class="mt-1 text-sm leading-6 text-gray-600" x-text="plateChanged ? 'A plate number change requires new front, rear, and side photos plus the latest Vehicle Geran/VOC.' : (modelChanged ? 'A model change requires the latest Vehicle Geran/VOC.' : 'A colour change requires new front, rear, and side photos.')"></p></div><button type="button" @click="cancelRevalidation" class="shrink-0 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancel revalidation</button></div>

        <div x-show="revalidationMode === 'plate'" class="grid gap-3 rounded-xl border border-green-200 bg-white p-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current plate</p><p class="mt-1 text-lg font-bold text-gray-900" x-text="original.plate_number"></p></div>
            <span class="hidden text-gray-400 sm:block">→</span>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Detected plate preview</p><p class="mt-1 text-lg font-bold" :class="detectedPlatePreview ? 'text-green-700' : 'text-gray-400'" x-text="detectedPlatePreview || 'Waiting for photo/VOC scan'"></p></div>
        </div>

        <div x-show="revalidationMode === 'model'" class="grid gap-3 rounded-xl border border-green-200 bg-white p-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current model</p><p class="mt-1 text-lg font-bold text-gray-900" x-text="original.model"></p></div>
            <span class="hidden text-gray-400 sm:block">→</span>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Detected model preview</p><p class="mt-1 text-lg font-bold" :class="detectedModelPreview ? 'text-green-700' : 'text-gray-400'" x-text="detectedModelPreview || 'Waiting for Geran/VOC scan'"></p></div>
        </div>

        <div x-show="revalidationMode === 'colour'" class="grid gap-3 rounded-xl border border-green-200 bg-white p-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center">
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current colour</p><p class="mt-1 text-lg font-bold text-gray-900" x-text="original.colour"></p></div>
            <span class="hidden text-gray-400 sm:block">→</span>
            <div><p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Detected colour preview</p><p class="mt-1 text-lg font-bold" :class="detectedColourPreview ? 'text-green-700' : 'text-gray-400'" x-text="detectedColourPreview || 'Waiting for all photo scans'"></p></div>
        </div>

        <div x-show="photosRequired" class="space-y-3">
            <div class="flex items-center justify-between gap-4"><p class="text-sm text-gray-600">Upload clear front, rear, and side photos of the same vehicle.</p><button type="button" @click="showPhotoExamples=true" class="shrink-0 text-sm font-semibold text-blue-700 underline decoration-blue-300 underline-offset-4 hover:text-blue-900">View photo examples</button></div>
            <div class="grid gap-4 md:grid-cols-3">
            @foreach(['front_image' => ['Front photo','FRONT'], 'rear_image' => ['Rear photo','REAR'], 'side_image' => ['Side photo','SIDE']] as $field => [$label, $view])
                <div>
                    <label class="block text-sm font-semibold text-gray-800">{{ $label }} *<span class="relative mt-2 flex aspect-[4/3] cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-white"><input x-ref="{{ $field }}" name="{{ $field }}" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="selectEvidence('{{ $field }}', $event.target.files[0])"><img x-cloak x-show="previews.{{ $field }}" :src="previews.{{ $field }}" class="h-full w-full object-cover" alt="{{ $label }} preview"><span x-show="!previews.{{ $field }}" class="p-4 text-center text-sm font-normal text-gray-500">Choose {{ strtolower($label) }}</span></span></label>
                    <input type="hidden" name="{{ $field }}_validation_token">
                    <div x-cloak x-show="busy.{{ $field }}" class="mt-2 rounded-lg border border-blue-100 bg-blue-50 px-3 py-3"><div class="mb-2 flex justify-between text-xs font-semibold text-blue-800"><span>Validating photo</span><span>Please wait…</span></div><div class="h-2 overflow-hidden rounded-full bg-blue-100"><div class="scan-progress-bar h-full w-full rounded-full"></div></div></div>
                    <button x-show="!busy.{{ $field }}" type="button" @click="validatePhoto('{{ $field }}','{{ $view }}')" :disabled="evidenceBusy || !previews.{{ $field }}" class="mt-2 w-full rounded-lg border border-green-700 px-3 py-2 text-sm font-semibold text-green-700 disabled:opacity-40" x-text="validated.{{ $field }} ? 'Validated ✓' : 'Validate photo'"></button>
                    @error($field)<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            @endforeach
            </div>
        </div>

        <div x-show="geranRequired"><div class="mb-2 flex items-center justify-between gap-4"><p class="text-sm font-semibold text-gray-800">Vehicle Geran / VOC *</p><button type="button" @click="showGeranExample=true" class="shrink-0 text-sm font-semibold text-blue-700 underline decoration-blue-300 underline-offset-4 hover:text-blue-900">View Geran example</button></div><div class="grid items-stretch gap-5 lg:grid-cols-2">
            <label class="block h-full min-h-72"><span class="relative flex h-full min-h-72 cursor-pointer items-center justify-center overflow-hidden rounded-xl border-2 border-dashed border-gray-300 bg-white"><input x-ref="vehicle_geran" name="vehicle_geran" type="file" accept="image/jpeg,image/png" class="sr-only" @change="selectEvidence('vehicle_geran', $event.target.files[0])"><img x-cloak x-show="previews.vehicle_geran" :src="previews.vehicle_geran" class="h-full max-h-80 w-full object-contain" alt="Vehicle Geran preview"><span x-show="!previews.vehicle_geran" class="p-6 text-center text-sm font-normal text-gray-500">Choose the latest Geran/VOC image</span></span></label>
            <div class="flex h-full min-h-72 flex-col rounded-xl border border-gray-200 bg-white p-5"><h4 class="font-bold text-gray-900">Document scan</h4><p class="mt-1 text-sm text-gray-500">The extracted owner and manufacturer must match the existing vehicle. The model will be populated from this document.</p><p x-show="scanMessage && !busy.vehicle_geran" x-text="scanMessage" class="mt-4 rounded-lg bg-gray-50 p-3 text-sm text-gray-700"></p><div x-cloak x-show="busy.vehicle_geran" class="mt-5 rounded-lg border border-blue-100 bg-blue-50 px-3 py-3"><div class="mb-2 flex justify-between text-xs font-semibold text-blue-800"><span>Scanning document</span><span>Please wait…</span></div><div class="h-2 overflow-hidden rounded-full bg-blue-100"><div class="scan-progress-bar h-full w-full rounded-full"></div></div></div><button x-show="!busy.vehicle_geran" type="button" @click="scanGeran" :disabled="evidenceBusy || !previews.vehicle_geran" class="mt-auto rounded-xl bg-green-700 px-4 py-3 text-sm font-bold text-white disabled:bg-gray-300" x-text="geranScanned ? 'Scanned ✓' : 'Scan & prefill'"></button></div>
        </div></div>
        @error('vehicle_geran')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

        <input type="hidden" name="registered_owner_name" x-ref="registered_owner_name">
        <input type="hidden" name="owner_identity_no" x-ref="owner_identity_no">
        <input type="hidden" name="manufacturer" x-ref="manufacturer">
        <input type="hidden" name="model_name" x-ref="model_name">
        <input type="hidden" name="geran_plate_number" x-ref="geran_plate_number">
    </section>
</div>
