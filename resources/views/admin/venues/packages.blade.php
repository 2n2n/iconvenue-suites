@extends('layouts.admin')

@section('page-title', 'Manage Event Packages - ' . $venue->name)

@section('main-content')
<div class="mb-6">
    <a href="{{ route('admin.venues.index') }}" class="text-purple-600 hover:text-purple-800 font-semibold">
        <i class="fas fa-arrow-left mr-2"></i>Back to Venues
    </a>
</div>

<div class="bg-white rounded-lg shadow p-6 mb-6">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">{{ $venue->name }}</h2>
            <p class="text-gray-600">Manage event packages for this venue</p>
        </div>
        <button onclick="document.getElementById('addPackageModal').classList.remove('hidden')" 
            class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Package
        </button>
    </div>

    @if($packages->count() > 0)
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
        @foreach($packages as $package)
        <div class="border rounded-lg p-6 {{ $package->is_active ? 'bg-white' : 'bg-gray-50' }}">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-800">{{ $package->name }}</h3>
                    @if($package->hasTimeBasedPricing())
                    <span class="text-xs bg-purple-100 text-purple-700 px-2 py-1 rounded-full ml-1">
                        <i class="fas fa-clock mr-1"></i>Time-based
                    </span>
                    @endif
                </div>
                <div>
                    @if($package->hasTimeBasedPricing())
                    <div class="text-right">
                        <div class="text-sm text-gray-600 mb-1">Starting at</div>
                        <div class="text-2xl font-bold text-purple-600">
                            ₱{{ number_format(min($package->price_morning ?? $package->price, $package->price_afternoon ?? $package->price, $package->price_evening ?? $package->price), 2) }}
                        </div>
                    </div>
                    @else
                    <div class="text-2xl font-bold text-purple-600">
                        ₱{{ number_format($package->price, 2) }}
                    </div>
                    @endif
                </div>
            </div>

            @if($package->description)
            <p class="text-gray-600 text-sm mb-4">{{ $package->description }}</p>
            @endif

            @if($package->hasTimeBasedPricing())
            <div class="mb-4 p-3 bg-purple-50 rounded-lg">
                <p class="text-xs font-semibold text-purple-700 mb-2">Time Slot Pricing:</p>
                <div class="grid grid-cols-3 gap-2 text-xs">
                    @if($package->price_morning)
                    <div class="text-center">
                        <i class="fas fa-sun text-blue-500"></i>
                        <div class="font-semibold">Morning</div>
                        <div class="text-purple-600">₱{{ number_format($package->price_morning, 0) }}</div>
                    </div>
                    @endif
                    @if($package->price_afternoon)
                    <div class="text-center">
                        <i class="fas fa-cloud-sun text-orange-500"></i>
                        <div class="font-semibold">Afternoon</div>
                        <div class="text-purple-600">₱{{ number_format($package->price_afternoon, 0) }}</div>
                    </div>
                    @endif
                    @if($package->price_evening)
                    <div class="text-center">
                        <i class="fas fa-moon text-indigo-500"></i>
                        <div class="font-semibold">Evening</div>
                        <div class="text-purple-600">₱{{ number_format($package->price_evening, 0) }}</div>
                    </div>
                    @endif
                </div>
                <div class="text-center mt-2 pt-2 border-t border-purple-200">
                    <div class="font-semibold">Full Day</div>
                    <div class="text-purple-600">₱{{ number_format($package->price, 0) }}</div>
                </div>
            </div>
            @endif

            @if(count($package->inclusion_labels) > 0)
            <div class="mb-4">
                <p class="font-semibold text-gray-700 text-sm mb-2">Inclusions:</p>
                <ul class="text-sm text-gray-600 space-y-1">
                    @foreach($package->inclusion_labels as $inclusion)
                    <li><i class="fas fa-check text-green-500 mr-2"></i>{{ $inclusion }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            <div class="flex space-x-2 mt-4">
                <button onclick="editPackage({{ $package->id }})" 
                    class="flex-1 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition text-sm">
                    <i class="fas fa-edit mr-1"></i>Edit
                </button>
                
                <form action="{{ route('admin.venues.packages.toggle', [$venue, $package]) }}" method="POST" class="flex-1">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full {{ $package->is_active ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700' }} text-white px-4 py-2 rounded transition text-sm">
                        <i class="fas fa-toggle-{{ $package->is_active ? 'on' : 'off' }} mr-1"></i>{{ $package->is_active ? 'Active' : 'Inactive' }}
                    </button>
                </form>
                
                <form action="{{ route('admin.venues.packages.destroy', [$venue, $package]) }}" method="POST" onsubmit="return confirm('Delete this package?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700 transition text-sm">
                        <i class="fas fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="text-center py-12">
        <i class="fas fa-box-open text-gray-300 text-6xl mb-4"></i>
        <p class="text-gray-600 text-xl mb-4">No packages yet</p>
        <button onclick="document.getElementById('addPackageModal').classList.remove('hidden')" 
            class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
            <i class="fas fa-plus mr-2"></i>Add Your First Package
        </button>
    </div>
    @endif
</div>

<!-- Add Package Modal -->
<div id="addPackageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Add Event Package</h3>
            <button onclick="document.getElementById('addPackageModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form action="{{ route('admin.venues.packages.store', $venue) }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Package Name *</label>
                    <input type="text" name="name" required placeholder="e.g., Birthday Package, Wedding Package"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Description</label>
                    <textarea name="description" rows="3" placeholder="Brief description of the package"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Price *</label>
                    <input type="number" name="price" required min="0" step="0.01" placeholder="0.00"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                    <p class="text-xs text-gray-500 mt-1">Base price (Full Day)</p>
                </div>

                <!-- Time-Based Pricing Section -->
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-gray-700 font-semibold">Time-Based Pricing (Optional)</label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="hasTimeBasedPricing" name="has_time_based_pricing" value="1"
                                   onchange="toggleTimeBasedPricing()"
                                   class="mr-2 w-4 h-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <span class="text-sm text-gray-600">Enable time-based pricing</span>
                        </label>
                    </div>
                    
                    <div id="timeBasedPricingFields" class="hidden space-y-3 bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 mb-3">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Set different prices for specific time slots. Leave blank to use base price.
                        </p>
                        
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-sun text-blue-500 mr-1"></i>Morning (8AM-12PM)
                                </label>
                                <input type="number" name="price_morning" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-cloud-sun text-orange-500 mr-1"></i>Afternoon (1PM-5PM)
                                </label>
                                <input type="number" name="price_afternoon" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-moon text-indigo-500 mr-1"></i>Evening (6PM-10PM)
                                </label>
                                <input type="number" name="price_evening" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Add-ons included with this package</label>
                    <p class="text-xs text-gray-500 mb-2">Check any items from your Add-ons catalog that come free with this package. The same add-on can still be sold separately elsewhere.</p>
                    @if($addons->count() > 0)
                    <div class="grid sm:grid-cols-2 gap-1 max-h-48 overflow-y-auto border rounded-lg p-3">
                        @foreach($addons as $addon)
                        <label class="flex items-center text-sm">
                            <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}"
                                class="mr-2 w-4 h-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            {{ $addon->name }}
                        </label>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-gray-500 italic">No add-ons in your catalog yet — add some under Admin &gt; Add-ons to include them here.</p>
                    @endif
                </div>

            </div>

            <div class="mt-6 flex space-x-4">
                <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-save mr-2"></i>Add Package
                </button>
                <button type="button" onclick="document.getElementById('addPackageModal').classList.add('hidden')" 
                    class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Package Modal -->
<div id="editPackageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-8 max-w-2xl w-full mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-6">
            <h3 class="text-2xl font-bold text-gray-800">Edit Package</h3>
            <button onclick="document.getElementById('editPackageModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times text-2xl"></i>
            </button>
        </div>

        <form id="editPackageForm" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Package Name *</label>
                    <input type="text" name="name" id="edit_name" required
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Description</label>
                    <textarea name="description" id="edit_description" rows="3"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"></textarea>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Price *</label>
                    <input type="number" name="price" id="edit_price" required min="0" step="0.01"
                        class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
                    <p class="text-xs text-gray-500 mt-1">Base price (Full Day)</p>
                </div>

                <!-- Time-Based Pricing Section -->
                <div class="border-t pt-4">
                    <div class="flex items-center justify-between mb-4">
                        <label class="block text-gray-700 font-semibold">Time-Based Pricing (Optional)</label>
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" id="edit_hasTimeBasedPricing" name="has_time_based_pricing" value="1" 
                                   onchange="toggleEditTimeBasedPricing()"
                                   class="mr-2 w-4 h-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            <span class="text-sm text-gray-600">Enable time-based pricing</span>
                        </label>
                    </div>
                    
                    <div id="edit_timeBasedPricingFields" class="hidden space-y-3 bg-gray-50 p-4 rounded-lg">
                        <p class="text-xs text-gray-600 mb-3">
                            <i class="fas fa-info-circle text-blue-500 mr-1"></i>
                            Set different prices for specific time slots. Leave blank to use base price.
                        </p>
                        
                        <div class="grid md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-sun text-blue-500 mr-1"></i>Morning (8AM-12PM)
                                </label>
                                <input type="number" name="price_morning" id="edit_price_morning" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-cloud-sun text-orange-500 mr-1"></i>Afternoon (1PM-5PM)
                                </label>
                                <input type="number" name="price_afternoon" id="edit_price_afternoon" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-orange-500 text-sm">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    <i class="fas fa-moon text-indigo-500 mr-1"></i>Evening (6PM-10PM)
                                </label>
                                <input type="number" name="price_evening" id="edit_price_evening" min="0" step="0.01" placeholder="Optional"
                                    class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-sm">
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 font-semibold mb-2">Add-ons included with this package</label>
                    <p class="text-xs text-gray-500 mb-2">Check any items from your Add-ons catalog that come free with this package. The same add-on can still be sold separately elsewhere.</p>
                    @if($addons->count() > 0)
                    <div id="editAddonsContainer" class="grid sm:grid-cols-2 gap-1 max-h-48 overflow-y-auto border rounded-lg p-3">
                        @foreach($addons as $addon)
                        <label class="flex items-center text-sm">
                            <input type="checkbox" name="addon_ids[]" value="{{ $addon->id }}" class="edit-addon-checkbox mr-2 w-4 h-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                            {{ $addon->name }}
                        </label>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-gray-500 italic">No add-ons in your catalog yet — add some under Admin &gt; Add-ons to include them here.</p>
                    @endif
                </div>

            </div>

            <div class="mt-6 flex space-x-4">
                <button type="submit" class="bg-purple-600 text-white px-6 py-3 rounded-lg hover:bg-purple-700 transition">
                    <i class="fas fa-save mr-2"></i>Update Package
                </button>
                <button type="button" onclick="document.getElementById('editPackageModal').classList.add('hidden')" 
                    class="bg-gray-300 text-gray-700 px-6 py-3 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleTimeBasedPricing() {
    const checkbox = document.getElementById('hasTimeBasedPricing');
    const fields = document.getElementById('timeBasedPricingFields');
    
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
        // Clear the fields when disabled
        fields.querySelectorAll('input').forEach(input => input.value = '');
    }
}

function toggleEditTimeBasedPricing() {
    const checkbox = document.getElementById('edit_hasTimeBasedPricing');
    const fields = document.getElementById('edit_timeBasedPricingFields');
    
    if (checkbox.checked) {
        fields.classList.remove('hidden');
    } else {
        fields.classList.add('hidden');
    }
}

function editPackage(id, name, description, price, inclusions) {
    // Fetch package data including time-based pricing
    fetch(`/admin/venues/{{ $venue->id }}/packages/${id}/data`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('editPackageForm').action = '{{ route('admin.venues.packages.update', [$venue, ':id']) }}'.replace(':id', id);
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_description').value = data.description || '';
            document.getElementById('edit_price').value = data.price;

            // Set time-based pricing fields
            const hasTimePricing = data.has_time_based_pricing || (data.price_morning || data.price_afternoon || data.price_evening);
            document.getElementById('edit_hasTimeBasedPricing').checked = hasTimePricing;
            
            if (hasTimePricing) {
                document.getElementById('edit_timeBasedPricingFields').classList.remove('hidden');
            } else {
                document.getElementById('edit_timeBasedPricingFields').classList.add('hidden');
            }
            
            document.getElementById('edit_price_morning').value = data.price_morning || '';
            document.getElementById('edit_price_afternoon').value = data.price_afternoon || '';
            document.getElementById('edit_price_evening').value = data.price_evening || '';

            // Set included add-ons (uncheck all first, since this modal is reused for every package)
            const addonIds = (data.addon_ids || []).map(String);
            document.querySelectorAll('.edit-addon-checkbox').forEach(checkbox => {
                checkbox.checked = addonIds.includes(checkbox.value);
            });

            document.getElementById('editPackageModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error fetching package data:', error);
            alert('Error loading package data. Please try again.');
        });
}
</script>
@endsection
