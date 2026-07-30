<div class="grid gap-6 md:grid-cols-2">
    <div class="md:col-span-2">
        <label for="plate_number" class="block text-sm font-semibold text-gray-800">Plate number</label>
        <input
            id="plate_number"
            name="plate_number"
            type="text"
            value="{{ old('plate_number', $vehicle?->plate_number) }}"
            maxlength="20"
            required
            autocomplete="off"
            placeholder="e.g. VAB 1234"
            class="mt-2 block w-full rounded-xl border-gray-300 uppercase shadow-none focus:border-[#2E7D32] focus:ring-[#2E7D32]"
            aria-describedby="plate_number_help @error('plate_number') plate_number_error @enderror"
        >
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
            maxlength="50"
            required
            placeholder="e.g. Perodua"
            class="mt-2 block w-full rounded-xl border-gray-300 shadow-none focus:border-[#2E7D32] focus:ring-[#2E7D32]"
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
            maxlength="50"
            required
            placeholder="e.g. Myvi"
            class="mt-2 block w-full rounded-xl border-gray-300 shadow-none focus:border-[#2E7D32] focus:ring-[#2E7D32]"
        >
        @error('model')
            <p class="mt-1.5 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label for="colour" class="block text-sm font-semibold text-gray-800">Colour</label>
        <input
            id="colour"
            name="colour"
            type="text"
            value="{{ old('colour', $vehicle?->colour) }}"
            maxlength="20"
            required
            placeholder="e.g. Silver"
            class="mt-2 block w-full rounded-xl border-gray-300 shadow-none focus:border-[#2E7D32] focus:ring-[#2E7D32]"
        >
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
</div>
