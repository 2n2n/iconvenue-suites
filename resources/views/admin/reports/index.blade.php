@extends('layouts.admin')

@section('page-title', 'Reports & Analytics')

@section('main-content')
<!-- Header -->
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Reports & Analytics</h1>
    <p class="text-gray-500 text-sm mt-1">Overview of bookings and revenue</p>
</div>

<!-- Date Filter -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <form action="{{ route('admin.reports.index') }}" method="GET" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-gray-700 font-semibold mb-2">Start Date</label>
            <input type="date" name="start_date" value="{{ $startDate->format('Y-m-d') }}" 
                class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        </div>
        <div>
            <label class="block text-gray-700 font-semibold mb-2">End Date</label>
            <input type="date" name="end_date" value="{{ $endDate->format('Y-m-d') }}" 
                class="px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        </div>
        <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
            <i class="fas fa-filter mr-2"></i>Filter
        </button>
        <a href="{{ route('admin.reports.export', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}" 
           class="bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-2 rounded-lg hover:shadow-lg transition-all duration-200 font-semibold">
            <i class="fas fa-file-excel mr-2"></i>Export to Excel
        </a>
    </form>
</div>

<!-- Statistics Cards -->
<div class="grid md:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">Total Bookings</p>
                <p class="text-3xl font-bold text-blue-600 tabular-nums">{{ number_format($bookingStats['total']) }}</p>
            </div>
            <i class="fas fa-calendar-check text-blue-600 text-4xl"></i>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">Confirmed</p>
                <p class="text-3xl font-bold text-green-600 tabular-nums">{{ number_format($bookingStats['confirmed']) }}</p>
            </div>
            <i class="fas fa-check-circle text-green-600 text-4xl"></i>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">Pending</p>
                <p class="text-3xl font-bold text-yellow-600 tabular-nums">{{ number_format($bookingStats['pending']) }}</p>
            </div>
            <i class="fas fa-clock text-yellow-600 text-4xl"></i>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-600 text-sm">Cancelled</p>
                <p class="text-3xl font-bold text-red-600 tabular-nums">{{ number_format($bookingStats['cancelled']) }}</p>
            </div>
            <i class="fas fa-times-circle text-red-600 text-4xl"></i>
        </div>
    </div>
</div>

<!-- Revenue Statistics -->
<div class="grid md:grid-cols-2 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold mb-4 text-gray-800">Revenue Summary</h3>
        <div class="space-y-4">
            <div class="flex justify-between items-center border-b pb-3 gap-4">
                <span class="text-gray-600 font-medium">Total Verified Revenue</span>
                <span class="text-2xl font-bold text-green-600 tabular-nums whitespace-nowrap">₱{{ number_format($revenueStats['total'], 2) }}</span>
            </div>
            <div class="flex justify-between items-center border-b pb-3 gap-4">
                <span class="text-gray-600 font-medium">Pending Payments</span>
                <span class="text-xl font-bold text-yellow-600 tabular-nums whitespace-nowrap">₱{{ number_format($revenueStats['pending'], 2) }}</span>
            </div>

            {{-- Breakdown by payment method --}}
            @if($revenueStats['by_method']->count() > 0)
            <div class="pt-1">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-3">Breakdown by Payment Method</p>
                <div class="space-y-3">
                    @foreach($revenueStats['by_method'] as $item)
                    @php
                        $icons = [
                            'gcash'         => ['icon' => 'fas fa-mobile-alt',    'color' => 'text-blue-500',    'bg' => 'bg-blue-50',    'badge' => 'bg-blue-100 text-blue-800'],
                            'paymaya'       => ['icon' => 'fas fa-wallet',         'color' => 'text-green-500',   'bg' => 'bg-green-50',   'badge' => 'bg-green-100 text-green-800'],
                            'maya'          => ['icon' => 'fas fa-wallet',         'color' => 'text-green-500',   'bg' => 'bg-green-50',   'badge' => 'bg-green-100 text-green-800'],
                            'cash'          => ['icon' => 'fas fa-money-bill-wave', 'color' => 'text-emerald-700', 'bg' => 'bg-emerald-50 border border-emerald-200', 'badge' => 'bg-emerald-600 text-white', 'total' => 'bg-white text-emerald-900 border border-emerald-300 shadow-sm'],
                            'bank transfer' => ['icon' => 'fas fa-university',     'color' => 'text-indigo-500',  'bg' => 'bg-indigo-50',  'badge' => 'bg-indigo-100 text-indigo-800'],
                            'credit card'   => ['icon' => 'fas fa-credit-card',    'color' => 'text-purple-500',  'bg' => 'bg-purple-50',  'badge' => 'bg-purple-100 text-purple-800'],
                            'other'         => ['icon' => 'fas fa-receipt',        'color' => 'text-gray-500',    'bg' => 'bg-gray-50',    'badge' => 'bg-gray-100 text-gray-800'],
                            'unspecified'   => ['icon' => 'fas fa-question-circle','color' => 'text-gray-400',    'bg' => 'bg-gray-50',    'badge' => 'bg-gray-100 text-gray-800'],
                        ];
                        $key = strtolower(trim($item->method));
                        $style = $icons[$key] ?? ['icon' => 'fas fa-receipt', 'color' => 'text-gray-500', 'bg' => 'bg-gray-50', 'badge' => 'bg-gray-100 text-gray-800', 'total' => 'text-gray-900'];
                        $isCash = $key === 'cash';
                    @endphp
                    <div class="{{ $style['bg'] }} rounded-lg px-3 py-3">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex items-start gap-2 min-w-0 flex-1">
                                <i class="{{ $style['icon'] }} {{ $style['color'] }} w-4 text-center mt-1 flex-shrink-0"></i>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2 py-0.5 rounded-full text-xs font-bold {{ $style['badge'] }}">
                                            @if($isCash)
                                                <i class="fas fa-money-bill-wave mr-1"></i>Cash
                                            @else
                                                {{ ucwords($item->method) }}
                                            @endif
                                        </span>
                                        <span class="text-xs text-gray-500">({{ number_format($item->count) }} payment{{ $item->count > 1 ? 's' : '' }})</span>
                                    </div>
                                    @if($item->clients->count() > 0)
                                    <div class="mt-2 space-y-1">
                                        @foreach($item->clients as $client)
                                        <div class="flex items-center justify-between gap-3 text-xs">
                                            <span class="{{ $isCash ? 'text-emerald-900' : 'text-gray-700' }} font-medium truncate opacity-100">{{ $client['name'] }}</span>
                                            <span class="{{ $isCash ? 'text-emerald-900 font-bold bg-white px-1.5 py-0.5 rounded' : 'text-gray-700' }} tabular-nums whitespace-nowrap flex-shrink-0 opacity-100">₱{{ number_format($client['amount'], 2) }}</span>
                                        </div>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                            <span class="text-base font-extrabold tabular-nums whitespace-nowrap flex-shrink-0 opacity-100 px-2.5 py-1 rounded-md {{ $style['total'] ?? 'text-gray-900' }}">₱{{ number_format($item->total, 2) }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Bookings & revenue by rate source (Regular / booking platform) --}}
    <div class="bg-white rounded-lg shadow p-6" dusk="report-by-platform">
        <h3 class="text-xl font-bold mb-1 text-gray-800 flex items-center">
            <i class="fas fa-globe mr-2 text-purple-600"></i>
            By Booking Platform
        </h3>
        <p class="text-xs text-gray-500 mb-4">Bookings created in this period (excluding cancelled) and verified payments received, by rate source.</p>
        @if($revenueStats['by_platform']->isNotEmpty())
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 uppercase border-b">
                        <th class="py-2 pr-4">Platform</th>
                        <th class="py-2 pr-4 text-right">Bookings</th>
                        <th class="py-2 pr-4 text-right">Booked Total</th>
                        <th class="py-2 text-right">Verified Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($revenueStats['by_platform'] as $row)
                    <tr dusk="report-platform-row-{{ \Illuminate\Support\Str::slug($row->label) }}">
                        <td class="py-2 pr-4 font-semibold text-gray-800">{{ $row->label }}</td>
                        <td class="py-2 pr-4 text-right tabular-nums">{{ number_format($row->count) }}</td>
                        <td class="py-2 pr-4 text-right tabular-nums whitespace-nowrap">₱{{ number_format($row->booked_total, 2) }}</td>
                        <td class="py-2 text-right tabular-nums whitespace-nowrap font-bold text-green-700">₱{{ number_format($row->revenue, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <p class="text-gray-500 text-sm">No bookings in this period.</p>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
            <i class="fas fa-building mr-2 text-purple-600"></i>
            Top Performing Venues
        </h3>
        <div class="space-y-3">
            @forelse($topVenues as $venue)
            <div class="flex justify-between items-center border-b pb-2">
                <div>
                    <p class="font-semibold text-gray-800">{{ $venue->name }}</p>
                    <p class="text-sm text-gray-600">{{ $venue->bookings_count }} booking(s)</p>
                </div>
                <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-semibold">
                    #{{ $loop->iteration }}
                </span>
            </div>
            @empty
            <p class="text-gray-600 text-center py-4">No venue bookings in this period</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Top Suites Section -->
<div class="grid md:grid-cols-1 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-xl font-bold mb-4 text-gray-800 flex items-center">
            <i class="fas fa-bed mr-2 text-blue-600"></i>
            Top Performing Suites
        </h3>
        <div class="grid md:grid-cols-2 gap-4">
            @forelse($topSuites as $suite)
            <div class="flex justify-between items-center border-b pb-2">
                <div>
                    <p class="font-semibold text-gray-800">{{ $suite->name }}</p>
                    <p class="text-sm text-gray-600">{{ $suite->bookings_count }} booking(s)</p>
                </div>
                <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm font-semibold">
                    #{{ $loop->iteration }}
                </span>
            </div>
            @empty
            <p class="text-gray-600 text-center py-4 col-span-2">No suite bookings in this period</p>
            @endforelse
        </div>
    </div>
</div>

<!-- Verified Payments -->
<div class="bg-white rounded-lg shadow mb-8">
    <div class="p-6 border-b">
        <h3 class="text-xl font-bold text-gray-800">Payment Records</h3>
        <p class="text-sm text-gray-500 mt-1">Verified payments with client name and payment method</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venue/Suite</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference No.</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($verifiedPayments as $payment)
                @php
                    $methodKey = strtolower(trim($payment->payment_method ?: 'Unspecified'));
                    $isCash = $methodKey === 'cash';
                @endphp
                <tr>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800">{{ $payment->booking->client_name ?? 'Unknown' }}</div>
                        <div class="text-xs text-gray-500 tabular-nums">{{ $payment->booking->client_phone ?? '' }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $payment->booking->venue->name ?? 'N/A' }}</td>
                    <td class="px-6 py-4">
                        @if($isCash)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                            <i class="fas fa-money-bill-wave mr-1"></i>Cash
                        </span>
                        @elseif($payment->payment_method)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                            {{ $payment->payment_method }}
                        </span>
                        @else
                        <span class="text-xs text-gray-400">Unspecified</span>
                        @endif
                    </td>
                    <td class="px-6 py-4 text-gray-600 font-mono text-sm">
                        {{ $payment->reference_number ?: '—' }}
                    </td>
                    <td class="px-6 py-4 text-right tabular-nums whitespace-nowrap">
                        <span class="inline-block font-extrabold opacity-100 {{ $isCash ? 'text-emerald-900 bg-emerald-50 border border-emerald-200 px-2.5 py-1 rounded-md' : 'text-gray-800' }}">₱{{ number_format($payment->amount, 2) }}</span>
                    </td>
                    <td class="px-6 py-4 text-gray-600 whitespace-nowrap">{{ $payment->created_at->format('M d, Y') }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-4 text-center text-gray-600">No verified payments found for this period</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Bookings Table -->
<div class="bg-white rounded-lg shadow">
    <div class="p-6 border-b">
        <h3 class="text-xl font-bold text-gray-800">Booking Details</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Venue/Suite</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Time Slot</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Payment</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Method</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($recentBookings as $booking)
                @php
                    $verifiedMethods = $booking->payments
                        ->where('status', 'verified')
                        ->pluck('payment_method')
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                <tr>
                    <td class="px-6 py-4 text-gray-600 tabular-nums whitespace-nowrap">#{{ $booking->id }}</td>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-gray-800">{{ $booking->client_name }}</div>
                        <div class="text-sm text-gray-600 tabular-nums whitespace-nowrap">{{ $booking->client_phone }}</div>
                    </td>
                    <td class="px-6 py-4 text-gray-600">{{ $booking->venue->name }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            @if($booking->venue->type == 'venue') bg-purple-100 text-purple-800
                            @else bg-blue-100 text-blue-800
                            @endif">
                            <i class="fas fa-{{ $booking->venue->type == 'venue' ? 'building' : 'bed' }} mr-1"></i>
                            {{ ucfirst($booking->venue->type) }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-gray-600 whitespace-nowrap">{{ $booking->booking_date->format('M d, Y') }}</td>
                    <td class="px-6 py-4 text-gray-600">{{ $booking->getReportTimeSlotDisplay() }}</td>
                    <td class="px-6 py-4 text-right font-semibold text-gray-800 tabular-nums whitespace-nowrap">₱{{ number_format($booking->total_amount, 2) }}</td>
                    <td class="px-6 py-4">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold
                            @if($booking->status == 'confirmed') bg-green-100 text-green-800
                            @elseif($booking->status == 'pending') bg-yellow-100 text-yellow-800
                            @elseif($booking->status == 'cancelled') bg-red-100 text-red-800
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
                        @if($verifiedMethods->isEmpty())
                        <span class="text-xs text-gray-400">—</span>
                        @else
                        <div class="flex flex-wrap gap-1">
                            @foreach($verifiedMethods as $method)
                            @php $isCash = strtolower(trim($method)) === 'cash'; @endphp
                            @if($isCash)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800">
                                <i class="fas fa-money-bill-wave mr-1"></i>Cash
                            </span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">
                                {{ $method }}
                            </span>
                            @endif
                            @endforeach
                        </div>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="10" class="px-6 py-4 text-center text-gray-600">No bookings found for this period</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6">
    {{ $recentBookings->links() }}
</div>
@endsection
