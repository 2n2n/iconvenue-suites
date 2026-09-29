@extends('layouts.admin')

@section('page-title', 'Edit Venue or Suite')

@section('main-content')
<div class="bg-white rounded-lg shadow p-6">
    <form action="{{ route('admin.venues.update', $venue) }}" method="POST" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2" id="nameLabel">{{ $venue->type == 'suite' ? 'Suite Name' : 'Venue Name' }} *</label>
                <input type="text" name="name" value="{{ old('name', $venue->name) }}" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Type *</label>
                <select name="type" id="typeSelect" required class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                    <option value="venue" {{ $venue->type == 'venue' ? 'selected' : '' }}>Venue</option>
                    <option value="suite" {{ $venue->type == 'suite' ? 'selected' : '' }}>Suite</option>
                </select>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Capacity *</label>
                <input type="number" name="capacity" id="capacity" value="{{ old('capacity', $venue->capacity) }}" required min="1"
                    oninput="updatePerHeadPrice()"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Full Day Price *</label>
                <input type="number" name="price_per_day" id="price_per_day" value="{{ old('price_per_day', $venue->price_per_day) }}" required min="0" step="0.01"
                    oninput="updatePerHeadPrice()"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                    placeholder="0.00">
                <p class="text-xs text-gray-500 mt-1" id="pricePerDayNote">Price for a full-day rental. This is separate from time-slot pricing below.</p>
                <p class="text-xs text-purple-600 font-medium mt-1" id="perHeadPriceDisplay">Per head: ₱0.00</p>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4">Time-Based Pricing</h3>
            <p class="text-sm text-gray-600 mb-4" id="timePricingNote">For venues only. Suites use standard 22-hour booking (Check-in: 2PM, Check-out: 12PM)</p>
            <div class="grid md:grid-cols-3 gap-6" id="timePricingFields" style="{{ $venue->type == 'suite' ? 'display: none;' : '' }}">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Morning (8AM - 12PM) *</label>
                    <input type="number" name="price_morning" id="price_morning" value="{{ old('price_morning', $venue->price_morning) }}" min="0" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00" {{ $venue->type == 'venue' ? 'required' : '' }}>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Afternoon (1PM - 5PM) *</label>
                    <input type="number" name="price_afternoon" id="price_afternoon" value="{{ old('price_afternoon', $venue->price_afternoon) }}" min="0" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00" {{ $venue->type == 'venue' ? 'required' : '' }}>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Evening (6PM - 10PM) *</label>
                    <input type="number" name="price_evening" id="price_evening" value="{{ old('price_evening', $venue->price_evening) }}" min="0" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                        placeholder="0.00" {{ $venue->type == 'venue' ? 'required' : '' }}>
                </div>
            </div>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Description *</label>
            <textarea name="description" rows="4" required 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">{{ old('description', $venue->description) }}</textarea>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Amenities (one per line)</label>
            <textarea name="amenities[]" rows="4" 
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600" 
                placeholder="WiFi&#10;Air Conditioning&#10;Parking">{{ is_array(old('amenities')) ? implode("\n", old('amenities')) : old('amenities', $venue->amenities ? implode("\n", $venue->amenities) : '') }}</textarea>
        </div>

        @if($venue->images && count($venue->images) > 0)
        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Current Images</label>
            <div class="grid grid-cols-4 gap-4">
                @foreach($venue->images as $index => $image)
                <div class="relative group">
                    <img src="{{ \App\Helpers\ImageHelper::getImageUrl($image) }}" alt="Venue image" class="w-full h-32 object-cover rounded-lg">
                    <button type="button" 
                            onclick="removeImage('{{ $venue->id }}', '{{ $image }}', this)"
                            class="absolute top-2 right-2 bg-red-600 hover:bg-red-700 text-white rounded-full w-8 h-8 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-200 shadow-lg">
                        <i class="fas fa-trash text-sm"></i>
                    </button>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Add New Images</label>
            <input type="file" name="images[]" multiple accept="image/*"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            <p class="text-sm text-gray-600 mt-1">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Upload high-quality images (up to 10MB each). Original quality will be preserved.
            </p>
        </div>

        <div class="mt-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" {{ $venue->is_active ? 'checked' : '' }} class="mr-2">
                <span class="text-gray-700 font-semibold">Active</span>
            </label>
        </div>

        <div class="mt-8 flex space-x-4">
            <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                Update Venue or Suite
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

document.addEventListener('DOMContentLoaded', function() {
    updatePerHeadPrice();

    const typeSelect = document.getElementById('typeSelect');
    const nameLabel = document.getElementById('nameLabel');
    const timePricingFields = document.getElementById('timePricingFields');
    const timePricingNote = document.getElementById('timePricingNote');
    const timeSlotInputs = document.querySelectorAll('#timePricingFields input');

    function updateFormBasedOnType() {
        if (typeSelect.value === 'suite') {
            nameLabel.textContent = 'Suite Name *';
            timePricingFields.style.display = 'none';
            timePricingNote.textContent = 'Suites use standard 22-hour booking (Check-in: 2PM, Check-out: 12PM next day)';
            timeSlotInputs.forEach(input => {
                input.value = '';
                input.removeAttribute('required');
            });
        } else {
            nameLabel.textContent = 'Venue Name *';
            timePricingFields.style.display = 'grid';
            timePricingNote.textContent = 'Set prices for each time slot. These are independent from the full day price above.';
            timeSlotInputs.forEach(input => input.setAttribute('required', 'required'));
        }
    }
    
    typeSelect.addEventListener('change', updateFormBasedOnType);
    updateFormBasedOnType();
});

function removeImage(venueId, imagePath, button) {
    if (!confirm('Are you sure you want to delete this image?')) {
        return;
    }
    
    fetch(`/admin/venues/${venueId}/remove-image`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ image: imagePath })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            button.closest('.relative').remove();
            alert('Image deleted successfully!');
        } else {
            alert('Failed to delete image: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete image. Please try again.');
    });
}
</script>
@endsection
