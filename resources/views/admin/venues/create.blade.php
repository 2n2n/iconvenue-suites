@extends('layouts.admin')

@section('page-title', 'Create Venue')

@section('main-content')
<div class="bg-white rounded-lg shadow p-6">
    <form action="{{ route('admin.venues.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Venue Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Type</label>
                <input type="text" value="Venue" readonly 
                    class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                <input type="hidden" name="type" value="venue">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Capacity *</label>
                <input type="number" name="capacity" id="capacity" value="{{ old('capacity') }}" required min="1"
                    oninput="updatePerHeadPrice()"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Full Day Price *</label>
                <input type="number" name="price_per_day" id="price_per_day" value="{{ old('price_per_day') }}" required min="0" step="0.01"
                    oninput="updatePerHeadPrice()"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                    placeholder="0.00">
                <p class="text-xs text-gray-500 mt-1">Price for a full-day rental. This is separate from time-slot pricing below.</p>
                <p class="text-xs text-purple-600 font-medium mt-1" id="perHeadPriceDisplay">Per head: ₱0.00</p>
            </div>
        </div>

        <div class="mt-6">
            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">
                <h3 class="text-lg font-bold text-purple-800 mb-2">
                    <i class="fas fa-clock mr-2"></i>Time-Based Pricing
                </h3>
                <p class="text-purple-700">
                    Set prices for each time slot. These are independent from the full day price above.
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Morning (8AM - 12PM) *</label>
                    <input type="number" name="price_morning" id="price_morning" value="{{ old('price_morning') }}" min="0" step="0.01" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Afternoon (1PM - 5PM) *</label>
                    <input type="number" name="price_afternoon" id="price_afternoon" value="{{ old('price_afternoon') }}" min="0" step="0.01" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Evening (6PM - 10PM) *</label>
                    <input type="number" name="price_evening" id="price_evening" value="{{ old('price_evening') }}" min="0" step="0.01" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00">
                </div>
            </div>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Description *</label>
            <textarea name="description" rows="4" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">{{ old('description') }}</textarea>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Amenities (one per line)</label>
            <textarea name="amenities[]" rows="4" 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600" 
                placeholder="WiFi&#10;Air Conditioning&#10;Parking&#10;Sound System"></textarea>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Images</label>
            <input type="file" name="images[]" multiple accept="image/*" id="venueImagesInput"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                onchange="previewVenueImages(event)">
            @error('images.*')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
            <p class="text-sm text-gray-600 mt-1">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Upload high-quality images (up to 10MB each). Original quality will be preserved.
            </p>
            
            <!-- Image Previews -->
            <div id="venueImagePreviews" class="mt-4 hidden">
                <p class="text-sm text-gray-600 mb-2">Preview:</p>
                <div id="previewContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            </div>
        </div>

        <div class="mt-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" checked class="mr-2">
                <span class="text-gray-700 font-semibold">Active</span>
            </label>
        </div>

        <div class="mt-8 flex space-x-4">
            <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                <i class="fas fa-save mr-2"></i>Create Venue
            </button>
            <a href="{{ route('admin.venues.index') }}" class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
function updatePerHeadPrice() {
    const capacity = parseFloat(document.getElementById('capacity').value) || 0;
    const price = parseFloat(document.getElementById('price_per_day').value) || 0;
    const display = document.getElementById('perHeadPriceDisplay');
    const perHead = capacity > 0 ? price / capacity : 0;
    display.textContent = capacity > 0
        ? `Per head (based on ${capacity} pax capacity): ₱${perHead.toFixed(2)}`
        : 'Per head: enter capacity to calculate';
}

function previewVenueImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('previewContainer');
    const previewSection = document.getElementById('venueImagePreviews');
    
    // Clear previous previews
    previewContainer.innerHTML = '';
    
    if (files.length > 0) {
        previewSection.classList.remove('hidden');
        
        Array.from(files).forEach((file, index) => {
            // Validate file size (10MB = 10 * 1024 * 1024 bytes)
            if (file.size > 10 * 1024 * 1024) {
                alert(`File "${file.name}" is too large. Maximum size is 10MB.`);
                return;
            }
            
            // Validate file type
            if (!file.type.startsWith('image/')) {
                alert(`File "${file.name}" is not a valid image.`);
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const imageDiv = document.createElement('div');
                imageDiv.className = 'relative group';
                imageDiv.innerHTML = `
                    <img src="${e.target.result}" alt="Preview ${index + 1}" 
                         class="w-full h-32 object-cover rounded-lg shadow border">
                    <div class="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white text-xs px-2 py-1 rounded">
                        ${file.name}
                    </div>
                `;
                previewContainer.appendChild(imageDiv);
            }
            reader.readAsDataURL(file);
        });
    } else {
        previewSection.classList.add('hidden');
    }
}
</script>
@endsection
