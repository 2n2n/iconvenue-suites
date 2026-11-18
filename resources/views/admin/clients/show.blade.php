@extends('layouts.admin')

@section('page-title', 'Client – ' . $client['name'])

@section('main-content')
<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('admin.clients.index') }}" class="text-gray-500 hover:text-gray-700 flex items-center gap-2">
        <i class="fas fa-arrow-left"></i> Back to Clients
    </a>
</div>

@if(session('success'))
<div class="mb-4 bg-green-100 border border-green-300 text-green-800 px-4 py-3 rounded-lg">
    {{ session('success') }}
</div>
@endif

{{-- Client Info Card --}}
<div class="bg-white rounded-lg shadow p-6 mb-6 flex items-center gap-6">
    <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center text-purple-600 text-2xl font-bold">
        {{ strtoupper(substr($client['name'], 0, 1)) }}
    </div>
    <div>
        <h2 class="text-xl font-bold text-gray-800" dusk="client-name">{{ $client['name'] }}</h2>
        <div class="text-sm text-gray-500 mt-1" dusk="client-email-line">
            <i class="fas fa-envelope mr-1"></i>{{ $client['email'] ?? 'No email' }}
        </div>
        <div class="text-sm text-gray-500 mt-1" dusk="client-phone-line">
            <i class="fas fa-phone mr-1"></i>{{ $client['phone'] }}
        </div>
    </div>
    <div class="ml-auto flex flex-col items-end gap-3">
        <div class="text-right">
            <div class="text-2xl font-bold text-purple-600">{{ $bookings->count() }}</div>
            <div class="text-sm text-gray-500">Total Bookings</div>
            <div class="text-lg font-semibold text-gray-700 mt-1">
                ₱{{ number_format($bookings->sum('total_amount'), 2) }}
            </div>
            <div class="text-sm text-gray-500">Total Spent</div>
        </div>
        @if(auth()->user()->isAdmin())
        <button onclick="document.getElementById('editModal').classList.remove('hidden')" dusk="client-edit"
                class="inline-flex items-center px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white text-sm font-semibold rounded-lg hover:shadow-lg transition-all duration-200">
            <i class="fas fa-edit mr-2"></i>Edit Client
        </button>
        @endif
    </div>
</div>

{{-- Bookings Table --}}
<div class="bg-white rounded-lg shadow overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-700">Booking History</h3>
    </div>
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venue</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @foreach($bookings as $booking)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <span class="font-mono text-sm font-semibold text-purple-600 bg-purple-50 px-2 py-1 rounded">
                        {{ $booking->booking_reference }}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-700">
                    <div>{{ $booking->venue->name }}</div>
                    @if($booking->package)
                    <div class="text-xs text-purple-600 mt-1">
                        <i class="fas fa-box mr-1"></i>{{ $booking->package->name }}
                    </div>
                    @endif
                </td>
                <td class="px-6 py-4 text-gray-600">{{ $booking->booking_date->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-gray-700 font-medium">₱{{ number_format($booking->total_amount, 2) }}</td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($booking->status == 'confirmed') bg-green-100 text-green-800
                        @elseif($booking->status == 'pending') bg-yellow-100 text-yellow-800
                        @elseif($booking->status == 'cancelled') bg-red-100 text-red-800
                        @elseif($booking->status == 'completed') bg-purple-100 text-purple-800
                        @else bg-blue-100 text-blue-800
                        @endif">
                        {{ ucfirst($booking->status) }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($booking->payment_status == 'paid') bg-green-100 text-green-800
                        @elseif($booking->payment_status == 'partial') bg-yellow-100 text-yellow-800
                        @else bg-red-100 text-red-800
                        @endif">
                        {{ ucfirst($booking->payment_status) }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <a href="{{ route('admin.bookings.show', $booking) }}"
                       class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-eye mr-1"></i>View
                    </a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

{{-- Edit Client Modal (Admin only) --}}
@if(auth()->user()->isAdmin())
<div id="editModal" dusk="client-edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-semibold text-gray-800">Edit Client Info</h3>
            <button onclick="document.getElementById('editModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <form action="{{ route('admin.clients.update', $client['key']) }}" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            @method('PUT')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Full Name</label>
                <input type="text" name="client_name" value="{{ old('client_name', $client['name']) }}"
                       dusk="client-edit-name" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                @error('client_name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email Address (optional)</label>
                <input type="email" name="client_email" value="{{ old('client_email', $client['email']) }}"
                       dusk="client-edit-email"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                <p class="text-xs text-gray-500 mt-1">Needed for booking confirmation, payment and reminder emails.</p>
                @error('client_email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                <input type="text" name="client_phone" value="{{ old('client_phone', $client['phone']) }}"
                       dusk="client-edit-phone" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500">
                @error('client_phone')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <p class="text-xs text-gray-400">This will update the client info across all their bookings.</p>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button"
                        onclick="document.getElementById('editModal').classList.add('hidden')"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm">
                    Cancel
                </button>
                <button type="submit" dusk="client-edit-save"
                        class="px-4 py-2 bg-gradient-to-r from-purple-600 to-pink-600 text-white rounded-lg hover:shadow-lg transition text-sm font-semibold">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Re-open modal on validation error --}}
@if($errors->any())
<script>document.getElementById('editModal').classList.remove('hidden');</script>
@endif
@endif

@endsection
