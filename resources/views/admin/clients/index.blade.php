@extends('layouts.admin')

@section('page-title', 'Client Records')

@section('main-content')
<div class="mb-6 flex justify-between items-center">
    <h2 class="text-lg font-semibold text-gray-700">All Clients</h2>

    <form method="GET" class="flex">
        <input type="text"
               name="search"
               value="{{ $search }}"
               placeholder="Search by name, email, or phone..."
               class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none focus:ring-2 focus:ring-purple-600 w-72">
        <button type="submit" class="bg-gray-600 text-white px-4 py-2 rounded-r-lg hover:bg-gray-700 transition">
            <i class="fas fa-search"></i>
        </button>
        @if($search)
        <a href="{{ route('admin.clients.index') }}" class="ml-2 bg-gray-300 text-gray-700 px-3 py-2 rounded-lg hover:bg-gray-400 transition">
            <i class="fas fa-times"></i>
        </a>
        @endif
    </form>
</div>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Client</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Phone</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Bookings</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total Spent</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Booking</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($clients as $client)
            <tr class="hover:bg-gray-50 transition">
                <td class="px-6 py-4">
                    <div class="font-semibold text-gray-800">{{ $client->client_name }}</div>
                    @if($client->hasClientEmail())
                    <div class="text-sm text-gray-500">{{ $client->client_email }}</div>
                    @else
                    <div class="text-sm text-gray-400 italic" dusk="client-no-email">No email</div>
                    @endif
                </td>
                <td class="px-6 py-4 text-gray-600">{{ $client->client_phone }}</td>
                <td class="px-6 py-4">
                    <span class="bg-purple-100 text-purple-700 text-xs font-semibold px-2 py-1 rounded-full">
                        {{ $client->total_bookings }} {{ Str::plural('booking', $client->total_bookings) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-gray-700 font-medium">
                    ₱{{ number_format($client->total_spent, 2) }}
                </td>
                <td class="px-6 py-4 text-gray-600">
                    {{ \Carbon\Carbon::parse($client->last_booking_date)->format('M d, Y') }}
                </td>
                <td class="px-6 py-4">
                    <a href="{{ route('admin.clients.show', $client->client_key) }}" dusk="client-view-bookings"
                       class="inline-flex items-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-xs font-semibold rounded-lg hover:shadow-lg transition-all duration-200">
                        <i class="fas fa-eye mr-1"></i>View Bookings
                    </a>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                    <i class="fas fa-users text-3xl mb-2 block text-gray-300"></i>
                    No clients found{{ $search ? ' for "' . $search . '"' : '' }}
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-6">
    {{ $clients->appends(['search' => $search])->links() }}
</div>
@endsection
