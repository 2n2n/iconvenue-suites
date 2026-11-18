@extends('layouts.admin')

@section('page-title', 'Suites Management')

@section('main-content')
<div class="mb-6 flex justify-between items-center">
    <a href="{{ route('admin.suites.create') }}" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
        <i class="fas fa-plus mr-2"></i>Add New Suite
    </a>
</div>

<!-- Info Box -->
<div class="bg-gradient-to-r from-blue-50 to-cyan-50 border-l-4 border-blue-600 rounded-lg p-4 mb-6">
    <div class="flex items-start">
        <i class="fas fa-info-circle text-blue-600 text-xl mr-3 mt-1"></i>
        <div>
            <h3 class="font-semibold text-blue-900 mb-1">Suite Booking Information</h3>
            <p class="text-sm text-blue-800">
                Suites use standard 22-hour booking periods with <strong>Check-in at 2:00 PM</strong> and <strong>Check-out at 12:00 PM</strong> the next day.
            </p>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Room No.</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Capacity</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regular Rate / Night</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($suites as $suite)
            <tr>
                <td class="px-6 py-4">
                    <div class="flex items-center">
                        <i class="fas fa-bed text-blue-600 text-xl mr-3"></i>
                        <div class="font-semibold text-gray-800">{{ $suite->name }}</div>
                    </div>
                </td>
                <td class="px-6 py-4 text-gray-600">
                    @if($suite->room_number)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                            <i class="fas fa-door-open mr-1"></i>{{ $suite->room_number }}
                        </span>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-gray-600">{{ $suite->capacity }} guests</td>
                <td class="px-6 py-4 text-gray-600">₱{{ number_format($suite->price_per_day, 2) }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold {{ $suite->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                        {{ $suite->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-2">
                        <!-- Book Button -->
                        <a href="{{ route('admin.bookings.create', ['venue_id' => $suite->id]) }}" 
                           class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200" 
                           title="Create Booking">
                            <i class="fas fa-calendar-plus mr-1"></i>Book
                        </a>
                        
                        <!-- Availability Toggle -->
                        <form action="{{ route('admin.venues.toggle-availability', $suite) }}" method="POST" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit" 
                                    class="inline-flex items-center px-3 py-2 text-xs font-semibold rounded-lg transition-all duration-200 {{ $suite->is_active ? 'bg-gradient-to-r from-red-500 to-red-600 text-white hover:shadow-lg' : 'bg-gradient-to-r from-green-500 to-green-600 text-white hover:shadow-lg' }}"
                                    title="{{ $suite->is_active ? 'Set Unavailable' : 'Set Available' }}"
                                    onclick="return confirm('{{ $suite->is_active ? 'Make this suite unavailable for bookings?' : 'Make this suite available for bookings?' }}')">
                                <i class="fas fa-{{ $suite->is_active ? 'times' : 'check' }} mr-1"></i>
                                {{ $suite->is_active ? 'Set Unavailable' : 'Set Available' }}
                            </button>
                        </form>
                        
                        <!-- Edit Button -->
                        <a href="{{ route('admin.suites.edit', $suite) }}" 
                           class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200" 
                           title="Edit Suite">
                            <i class="fas fa-edit mr-1"></i>Edit
                        </a>

                        <!-- Platform Rates Button -->
                        <a href="{{ route('admin.rates.index') }}#suite-{{ $suite->id }}"
                           dusk="suite-rates-{{ $suite->id }}"
                           class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200"
                           title="Rates per booking platform">
                            <i class="fas fa-tags mr-1"></i>Rates
                        </a>
                        
                        <!-- Delete Button -->
                        <form action="{{ route('admin.suites.destroy', $suite) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" 
                                    class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200" 
                                    title="Delete Suite"
                                    onclick="return confirm('Permanently delete this suite? This will also delete all associated bookings. This action cannot be undone.')">
                                <i class="fas fa-trash-alt mr-1"></i>Delete
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-12 text-center">
                    <i class="fas fa-bed text-gray-300 text-6xl mb-4"></i>
                    <p class="text-gray-600 text-xl">No suites found</p>
                    <a href="{{ route('admin.suites.create') }}" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition">
                        <i class="fas fa-plus mr-2"></i>Add Your First Suite
                    </a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $suites->links() }}
</div>
@endsection
