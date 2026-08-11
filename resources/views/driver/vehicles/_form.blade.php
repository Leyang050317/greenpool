<div class="grid gap-6 md:grid-cols-2">
    <div
        class="md:col-span-2"
        x-data="{
            previewUrl: null,
            fileName: '',
            dragging: false,
            previewFile(file) {
                if (!file) return;
                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = URL.createObjectURL(file);
                this.fileName = file.name;
            },
            chooseDroppedFile(event) {
                const file = event.dataTransfer.files[0];
                if (!file) return;
                const transfer = new DataTransfer();
                transfer.items.add(file);
                this.$refs.vehicleImage.files = transfer.files;
                this.previewFile(file);
            }
        }"
    >
        <label for="vehicle_image" class="block text-sm font-semibold text-gray-800">
            Vehicle Picture @unless($vehicle)<span class="text-red-600" aria-hidden="true">*</span>@endunless
        </label>
        <p id="vehicle_image_help" class="mt-1.5 text-sm text-gray-500">Upload a clear picture of the vehicle. JPG, PNG or WEBP, maximum 5 MB.</p>

        <input
            id="vehicle_image"
            x-ref="vehicleImage"
            name="vehicle_image"
            type="file"
            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            class="sr-only"
            @change="previewFile($event.target.files[0])"
            @unless($vehicle) required @endunless
            aria-describedby="vehicle_image_help @error('vehicle_image') vehicle_image_error @enderror"
        >

        <button
            type="button"
            @click="$refs.vehicleImage.click()"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="dragging = false; chooseDroppedFile($event)"
            :class="dragging ? 'border-[#2E7D32] bg-green-50' : 'border-gray-300 bg-gray-50 hover:bg-gray-100'"
            class="mt-3 flex w-full flex-col items-center justify-center overflow-hidden rounded-xl border-2 border-dashed p-3 text-center transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2E7D32] focus-visible:ring-offset-2"
        >
            <img
                x-cloak
                x-show="previewUrl"
                :src="previewUrl"
                alt="Selected vehicle picture preview"
                class="aspect-[16/7] w-full rounded-lg object-cover"
            >

            @if ($vehicle?->vehicle_image_path)
                <img
                    x-show="!previewUrl"
                    src="{{ asset('storage/'.$vehicle->vehicle_image_path) }}"
                    alt="{{ $vehicle->brand }} {{ $vehicle->model }} vehicle"
                    class="aspect-[16/7] w-full rounded-lg object-cover"
                >
            @else
                <span x-show="!previewUrl" class="flex min-h-40 flex-col items-center justify-center py-8">
                    <x-icons.lucide name="car-front" class="h-8 w-8 text-gray-400" />
                    <span class="mt-3 text-sm font-semibold text-gray-700">Click to select or drag and drop</span>
                </span>
            @endif

            <span x-show="fileName" x-text="fileName" class="mt-3 max-w-full truncate text-sm font-medium text-[#2E7D32]"></span>
            <span x-show="previewUrl" class="mt-1 text-xs text-gray-500">Click to choose another picture</span>
        </button>

        @error('vehicle_image')
            <p id="vehicle_image_error" class="mt-2 text-sm font-medium text-red-600">{{ $message }}</p>
        @enderror
    </div>

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
