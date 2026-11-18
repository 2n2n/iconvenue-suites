@extends('layouts.admin')

@section('page-title', 'Booking Platforms')

@section('main-content')
<div class="mb-4">
    <a href="{{ route('admin.rates.index') }}" dusk="back-to-room-rates" class="text-purple-600 hover:text-purple-800 text-sm">
        <i class="fas fa-arrow-left mr-1"></i>Back to Room Rates
    </a>
</div>
<div class="bg-gradient-to-r from-purple-50 to-pink-50 border-l-4 border-purple-600 rounded-lg p-4 mb-6">
    <p class="text-sm text-purple-900">
        Platforms such as Agoda and Booking.com can each have their own nightly rate per suite (set on the <a href="{{ route('admin.rates.index') }}" class="underline font-semibold">Room Rates</a> screen).
        The <strong>Regular rate</strong> is always available and is the suite's own price.
        Inactive platforms cannot be selected for new bookings.
    </p>
</div>

@if(session('error'))
<div class="bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg p-4 mb-6" dusk="platform-error">{{ session('error') }}</div>
@endif

@if($errors->any())
<div class="bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg p-4 mb-6">
    <ul class="list-disc ml-5 text-sm">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

<!-- Add platform -->
<div class="bg-white rounded-lg shadow p-6 mb-6">
    <h3 class="text-lg font-bold text-gray-800 mb-4"><i class="fas fa-plus-circle text-purple-600 mr-2"></i>Add Platform</h3>
    <form action="{{ route('admin.booking-platforms.store') }}" method="POST" class="flex flex-col sm:flex-row gap-3">
        @csrf
        <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" placeholder="e.g. Expedia" dusk="platform-name"
               class="flex-1 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" max="9999" title="Display order"
               class="w-full sm:w-32 px-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-600">
        <button type="submit" dusk="platform-add" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
            Add
        </button>
    </form>
</div>

<!-- Platforms -->
<div class="bg-white rounded-lg shadow overflow-x-auto">
    <table class="min-w-full">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Platform</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Order</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Suites with rate</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bookings</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200">
            @forelse($platforms as $platform)
            <tr dusk="platform-row-{{ $platform->id }}">
                <td class="px-6 py-4" colspan="2">
                    <form action="{{ route('admin.booking-platforms.update', $platform) }}" method="POST" class="flex gap-2 items-center">
                        @csrf
                        @method('PUT')
                        <input type="text" name="name" value="{{ $platform->name }}" required maxlength="100"
                               class="px-3 py-1 border rounded-lg w-48 focus:outline-none focus:ring-2 focus:ring-purple-600">
                        <input type="number" name="sort_order" value="{{ $platform->sort_order }}" min="0" max="9999"
                               class="px-3 py-1 border rounded-lg w-20 focus:outline-none focus:ring-2 focus:ring-purple-600">
                        <button type="submit" class="text-purple-600 hover:text-purple-800 text-sm font-semibold" title="Save">
                            <i class="fas fa-save"></i>
                        </button>
                    </form>
                </td>
                <td class="px-6 py-4 text-gray-600">{{ $platform->rates_count }}</td>
                <td class="px-6 py-4 text-gray-600">{{ $platform->bookings_count }}</td>
                <td class="px-6 py-4">
                    <span dusk="platform-status-{{ $platform->id }}" class="px-3 py-1 rounded-full text-xs font-semibold {{ $platform->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700' }}">
                        {{ $platform->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
                <td class="px-6 py-4">
                    <div class="flex flex-wrap gap-2">
                        <form action="{{ route('admin.booking-platforms.toggle', $platform) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <button type="submit" dusk="platform-toggle-{{ $platform->id }}"
                                    class="px-3 py-2 text-xs font-semibold rounded-lg text-white {{ $platform->is_active ? 'bg-gray-500 hover:bg-gray-600' : 'bg-green-600 hover:bg-green-700' }}">
                                {{ $platform->is_active ? 'Deactivate' : 'Activate' }}
                            </button>
                        </form>
                        @if($platform->bookings_count === 0)
                        <form action="{{ route('admin.booking-platforms.destroy', $platform) }}" method="POST"
                              onsubmit="return confirm('Delete {{ $platform->name }} and its suite rates?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="px-3 py-2 text-xs font-semibold rounded-lg text-white bg-red-600 hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-6 py-8 text-center text-gray-500">No platforms yet. Add Agoda, Booking.com or another channel above.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
