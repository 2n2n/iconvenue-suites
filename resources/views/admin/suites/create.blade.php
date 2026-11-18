@extends('layouts.admin')

@section('page-title', 'Create Suite')

@section('main-content')
<div class="bg-white rounded-lg shadow p-6">
    <form action="{{ route('admin.suites.store') }}" method="POST" enctype="multipart/form-data">
        @csrf
        
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">Suite Name *</label>
                <input type="text" name="name" value="{{ old('name') }}" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Room Number</label>
                <input type="text" name="room_number" value="{{ old('room_number') }}" maxlength="50"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                    placeholder="e.g. 101, A-02, Suite 5">
                <p class="text-xs text-gray-500 mt-1">Optional identifier for this suite room.</p>
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Type</label>
                <input type="text" value="Suite" readonly 
                    class="w-full px-4 py-2 border rounded-lg bg-gray-100 text-gray-600">
                <input type="hidden" name="type" value="suite">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Capacity *</label>
                <input type="number" name="capacity" value="{{ old('capacity') }}" required min="1"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">Regular Rate (per night) *</label>
                <input type="number" name="price_per_day" value="{{ old('price_per_day') }}" required min="0" step="0.01"
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
            </div>
        </div>

        <div class="mt-6">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h3 class="text-lg font-bold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Suite Booking Information
                </h3>
                <p class="text-blue-700">
                    Suites use a standard 22-hour booking period with <strong>Check-in at 2:00 PM</strong> and <strong>Check-out at 12:00 PM</strong> the next day.
                    Time-based pricing is not applicable for suites.
                </p>
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
                placeholder="WiFi&#10;Air Conditioning&#10;Private Bathroom&#10;Mini Fridge"></textarea>
        </div>

        <div class="mt-6">
            <label class="block text-gray-700 font-semibold mb-2">Images</label>
            <input type="file" name="images[]" multiple accept="image/*" id="suiteImagesInput"
                class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                onchange="previewSuiteImages(event)">
            @error('images.*')
            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
            <p class="text-sm text-gray-600 mt-1">
                <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                Upload high-quality images (up to 10MB each). Original quality will be preserved.
            </p>
            
            <!-- Image Previews -->
            <div id="suiteImagePreviews" class="mt-4 hidden">
                <p class="text-sm text-gray-600 mb-2">Preview:</p>
                <div id="suitePreviewContainer" class="grid grid-cols-2 md:grid-cols-4 gap-4"></div>
            </div>
        </div>

        <div class="mt-6">
            <label class="flex items-center">
                <input type="checkbox" name="is_active" checked class="mr-2">
                <span class="text-gray-700 font-semibold">Active</span>
            </label>
        </div>

        <div class="mt-8 flex space-x-4">
            <button type="submit" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                <i class="fas fa-save mr-2"></i>Create Suite
            </button>
            <a href="{{ route('admin.suites.index') }}" class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition">
                Cancel
            </a>
        </div>
    </form>
</div>

<script>
function previewSuiteImages(event) {
    const files = event.target.files;
    const previewContainer = document.getElementById('suitePreviewContainer');
    const previewSection = document.getElementById('suiteImagePreviews');
    
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
