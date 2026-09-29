@extends('layouts.admin')

@section('page-title', 'Booking Details')

@section('main-content')
<div class="grid md:grid-cols-3 gap-6">
    <!-- Booking Information -->
    <div class="md:col-span-2 space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Booking Information</h3>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">Booking Reference</p>
                    <p class="font-mono font-bold text-purple-600 bg-purple-50 px-2 py-1 rounded inline-block">
                        {{ $booking->booking_reference ?? 'IVS-' . date('Y') . '-' . str_pad($booking->id, 4, '0', STR_PAD_LEFT) }}
                    </p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Status</p>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($booking->status == 'confirmed') bg-green-100 text-green-800
                        @elseif($booking->status == 'pending') bg-yellow-100 text-yellow-800
                        @elseif($booking->status == 'cancelled') bg-red-100 text-red-800
                        @elseif($booking->status == 'completed') bg-purple-100 text-purple-800
                        @else bg-blue-100 text-blue-800
                        @endif">
                        {{ ucfirst($booking->status) }}
                    </span>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Venue</p>
                    <p class="font-semibold text-gray-800">{{ $booking->venue->name }}</p>
                </div>
                @if($booking->package)
                <div>
                    <p class="text-gray-600 text-sm">Package</p>
                    <div class="font-semibold text-gray-800">
                        {{ $booking->package->name }}
                        @if($booking->package->description)
                        <p class="text-sm text-gray-600 font-normal mt-1">{{ $booking->package->description }}</p>
                        @endif
                    </div>
                </div>
                @endif
                <div>
                    <p class="text-gray-600 text-sm">Booking Date</p>
                    <p class="font-semibold text-gray-800">{{ $booking->booking_date->format('M d, Y') }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Duration</p>
                    <p class="font-semibold text-gray-800">
                        @if($booking->number_of_days && $booking->number_of_days > 1)
                            {{ $booking->number_of_days }} Days
                            ({{ $booking->booking_date->format('M d') }} – {{ $booking->booking_date->copy()->addDays($booking->number_of_days - 1)->format('M d, Y') }})
                        @else
                            Single Day
                        @endif
                    </p>
                </div>
                @if($booking->venue->type == 'suite')
                <div>
                    <p class="text-gray-600 text-sm">Booking Type</p>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">
                        Suite (22 hours)
                    </span>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Check-in / Check-out</p>
                    <p class="font-semibold text-gray-800">2:00 PM / 12:00 PM</p>
                </div>
                @elseif(!empty($booking->getTimeSlots()))
                <div>
                    <p class="text-gray-600 text-sm">Time Slots</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($booking->getTimeSlots() as $slot)
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            @if($slot === 'morning') bg-blue-100 text-blue-800
                            @elseif($slot === 'afternoon') bg-orange-100 text-orange-800
                            @elseif($slot === 'evening') bg-indigo-100 text-indigo-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ \App\Models\Booking::formatTimeSlotLabel($slot) }}
                        </span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>

            @if($booking->notes)
            <div class="mt-4">
                <p class="text-gray-600 text-sm">Notes</p>
                <p class="text-gray-800">{{ $booking->notes }}</p>
            </div>
            @endif
        </div>

        <!-- Client Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Client Information</h3>
            
            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">Name</p>
                    <p class="font-semibold text-gray-800">{{ $booking->client_name }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Email</p>
                    <p class="font-semibold text-gray-800">{{ $booking->client_email }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Phone</p>
                    <p class="font-semibold text-gray-800">{{ $booking->client_phone }}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Handled By</p>
                    <p class="font-semibold text-gray-800">{{ $booking->staff->name ?? 'N/A' }}</p>
                </div>
            </div>
        </div>

        <!-- Payment Records -->
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold text-gray-800">Payment Records</h3>
                <a href="{{ route('admin.payments.create', $booking) }}" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition text-sm">
                    <i class="fas fa-plus mr-2"></i>Add Payment
                </a>
            </div>

            @if($booking->payments->count() > 0)
            <div class="space-y-3">
                @foreach($booking->payments as $payment)
                <div class="border rounded-lg p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-800">₱{{ number_format($payment->amount, 2) }}</p>
                            <p class="text-sm text-gray-600">{{ $payment->payment_method ?? 'N/A' }}</p>
                            @if($payment->reference_number)
                            <p class="text-sm text-gray-700 mt-1">
                                <span class="text-gray-500">Ref:</span>
                                <span class="font-mono font-medium">{{ $payment->reference_number }}</span>
                            </p>
                            @endif
                            <p class="text-xs text-gray-500">Submitted: {{ $payment->created_at->format('M d, Y h:i A') }}</p>
                            @if($payment->verified_at)
                            <p class="text-xs text-gray-500">
                                {{ $payment->status == 'verified' ? 'Verified' : 'Reviewed' }}: {{ $payment->verified_at->format('M d, Y h:i A') }}
                                @if($payment->verifiedBy) by {{ $payment->verifiedBy->name }}@endif
                            </p>
                            @endif
                            @if($payment->notes)
                            <p class="text-xs {{ $payment->status == 'rejected' ? 'text-red-600' : 'text-gray-600' }} mt-1"><i class="fas fa-info-circle"></i> {{ $payment->notes }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold
                                @if($payment->status == 'verified') bg-green-100 text-green-800
                                @elseif($payment->status == 'pending') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800
                                @endif">
                                {{ ucfirst($payment->status) }}
                            </span>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @if($payment->status == 'pending')
                                <form action="{{ route('admin.payments.verify', $payment) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200"
                                            onclick="return confirm('Verify this payment{{ $payment->reference_number ? ' (Ref: ' . $payment->reference_number . ')' : '' }}?')">
                                        <i class="fas fa-check mr-1"></i>Verify
                                    </button>
                                </form>
                                
                                <button onclick="showRejectModal{{ $payment->id }}()" 
                                        class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-orange-500 to-orange-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200">
                                    <i class="fas fa-times-circle mr-1"></i>Reject
                                </button>
                                @endif
                                
                                @if($payment->status == 'rejected')
                                <form action="{{ route('admin.payments.destroy', $payment) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" 
                                            class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200" 
                                            onclick="return confirm('Delete this rejected payment?')">
                                        <i class="fas fa-trash-alt mr-1"></i>Delete
                                    </button>
                                </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-600 text-center py-4">No payment records yet</p>
            @endif
        </div>
    </div>

    <!-- Summary Sidebar -->
    <div class="space-y-6">
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Payment Summary</h3>
            
            <div class="space-y-3">
                @if($booking->hasDiscount())
                <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-3">
                    <h4 class="font-semibold text-red-800 mb-2">
                        <i class="fas fa-percent mr-1"></i>Discount Applied
                    </h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-red-700">Original Amount:</span>
                            <span class="font-medium text-red-800">₱{{ number_format($booking->getOriginalAmount(), 2) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-red-700">Discount:</span>
                            <span class="font-medium text-red-800">
                                -₱{{ number_format($booking->getDiscountAmount(), 2) }}
                                @if($booking->discount_percentage)
                                    ({{ number_format($booking->discount_percentage, 1) }}%)
                                @endif
                            </span>
                        </div>
                        @if($booking->discount_reason)
                        <div class="flex justify-between">
                            <span class="text-red-700">Reason:</span>
                            <span class="font-medium text-red-800">{{ $booking->discount_reason }}</span>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
                
                <div class="flex justify-between">
                    <span class="text-gray-600">Total Amount</span>
                    <span class="font-semibold text-gray-800">₱{{ number_format($booking->total_amount, 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Paid Amount</span>
                    <span class="font-semibold text-green-600">₱{{ number_format($booking->payments->where('status', 'verified')->sum('amount'), 2) }}</span>
                </div>
                <div class="flex justify-between border-t pt-3">
                    <span class="text-gray-600">Balance</span>
                    <span class="font-semibold text-red-600">₱{{ number_format($booking->total_amount - $booking->payments->where('status', 'verified')->sum('amount'), 2) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-600">Payment Status</span>
                    <span class="px-3 py-1 rounded-full text-xs font-semibold
                        @if($booking->payment_status == 'paid') bg-green-100 text-green-800
                        @elseif($booking->payment_status == 'partial') bg-yellow-100 text-yellow-800
                        @else bg-red-100 text-red-800
                        @endif">
                        {{ ucfirst($booking->payment_status) }}
                    </span>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-xl font-bold mb-4 text-gray-800">Quick Actions</h3>
            
            <div class="space-y-3">
                <!-- Status Actions -->
                @if($booking->status == 'confirmed')
                <form action="{{ route('admin.bookings.complete', $booking) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white py-3 rounded-lg hover:shadow-lg transition-all duration-200 font-semibold" onclick="return confirm('Mark this booking as done?')">
                        <i class="fas fa-check-double mr-2"></i>Mark as Done
                    </button>
                </form>
                @endif
                
                @if($booking->status != 'cancelled' && $booking->status != 'completed')
                <form action="{{ route('admin.bookings.cancel', $booking) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white py-3 rounded-lg hover:shadow-lg transition-all duration-200 font-semibold" onclick="return confirm('Cancel this booking?')">
                        <i class="fas fa-ban mr-2"></i>Cancel Booking
                    </button>
                </form>
                @endif
                
                <!-- Management Actions -->
                <div class="border-t pt-3 mt-3">
                    <a href="{{ route('admin.bookings.edit', $booking) }}" class="block w-full text-center bg-gray-700 text-white py-3 rounded-lg hover:bg-gray-800 transition-all duration-200 font-semibold">
                        <i class="fas fa-edit mr-2"></i>Edit Details
                    </a>
                </div>
                
                <!-- Delete Button (For Completed or Cancelled Bookings) -->
                @if($booking->status == 'cancelled' || $booking->status == 'completed')
                <form action="{{ route('admin.bookings.destroy', $booking) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full bg-gradient-to-r from-red-600 to-red-700 text-white py-3 rounded-lg hover:shadow-lg transition-all duration-200 font-semibold" onclick="return confirm('Permanently delete this booking? This action cannot be undone.')">
                        <i class="fas fa-trash-alt mr-2"></i>Delete Booking
                    </button>
                </form>
                @endif
                
                <!-- Navigation -->
                <a href="{{ route('admin.bookings.index') }}" class="block w-full text-center bg-gray-100 text-gray-700 py-3 rounded-lg hover:bg-gray-200 transition-all duration-200 font-semibold border border-gray-300">
                    <i class="fas fa-arrow-left mr-2"></i>Back to List
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modals for each payment -->
@foreach($booking->payments as $payment)
@if($payment->status == 'pending')
<div id="rejectModal{{ $payment->id }}" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold mb-4 text-gray-800">Reject Payment #{{ $payment->id }}</h3>
        <form action="{{ route('admin.payments.reject', $payment) }}" method="POST">
            @csrf
            <div class="mb-4">
                <label class="block text-gray-700 font-semibold mb-2">Reason for Rejection *</label>
                <textarea name="notes" rows="4" required 
                    class="w-full px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600"
                    placeholder="Please provide a reason for rejecting this payment..."></textarea>
            </div>
            <div class="flex space-x-4">
                <button type="submit" class="bg-red-600 text-white px-6 py-2 rounded-lg hover:bg-red-700 transition">
                    Reject Payment
                </button>
                <button type="button" onclick="closeRejectModal{{ $payment->id }}()" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endforeach

@push('scripts')
<script>
@foreach($booking->payments as $payment)
@if($payment->status == 'pending')
function showRejectModal{{ $payment->id }}() {
    const modal = document.getElementById('rejectModal{{ $payment->id }}');
    modal.classList.remove('hidden');
}

function closeRejectModal{{ $payment->id }}() {
    const modal = document.getElementById('rejectModal{{ $payment->id }}');
    modal.classList.add('hidden');
}
@endif
@endforeach
</script>
@endpush
@endsection
