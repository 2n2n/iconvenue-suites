@extends('layouts.admin')

@section('page-title', 'Room Rates')

@section('main-content')
@php
    /** @var \App\Data\Venue\RateGrid $grid */
    // After a failed save, restore what the admin typed: "suiteId:platformId|regular" => price
    $oldCells = collect(old('cells', []))->mapWithKeys(fn ($c) => [
        ($c['venue_id'] ?? '').':'.(($c['platform_id'] ?? '') === '' ? 'regular' : $c['platform_id']) => $c['price'] ?? '',
    ]);
    $fmt = fn (?float $v) => $v === null ? '' : rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
@endphp

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
    <p class="text-sm text-gray-600">
        <i class="fas fa-info-circle text-purple-600 mr-1"></i>
        Changes apply to <strong>new bookings only</strong>. An empty platform cell uses the room's regular rate.
    </p>
    <a href="{{ route('admin.booking-platforms.index') }}" dusk="manage-platforms"
       class="inline-flex items-center px-4 py-2 bg-white border border-purple-300 text-purple-700 text-sm font-semibold rounded-lg hover:bg-purple-50 transition">
        <i class="fas fa-globe mr-2"></i>Manage platforms
    </a>
</div>

@if($errors->any())
<div class="bg-red-50 border-l-4 border-red-500 text-red-800 rounded-lg p-4 mb-4" dusk="rates-errors">
    <p class="font-semibold text-sm mb-1">Nothing was saved. Please fix:</p>
    <ul class="list-disc ml-5 text-sm">
        @foreach(collect($errors->all())->unique() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
</div>
@endif

@if($grid->suites->isEmpty())
<div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
    No active suites yet. <a href="{{ route('admin.suites.create') }}" class="text-purple-600 underline">Add a suite</a> to set its rates.
</div>
@else
<form action="{{ route('admin.rates.update') }}" method="POST" id="roomRatesForm">
    @csrf
    @method('PUT')
    <div id="roomRatesCells"></div>

    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="min-w-full text-sm" id="roomRatesTable">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-3 py-3 w-10 sticky left-0 bg-gray-50 z-10">
                        <input type="checkbox" id="selectAllRooms" dusk="select-all-rooms" class="rounded" title="Select all rooms">
                    </th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase sticky left-10 bg-gray-50 z-10 min-w-[10rem]">Room</th>
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase">Regular</th>
                    @foreach($grid->platforms as $platform)
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">
                        {{ $platform->name }}
                        @unless($platform->is_active)
                        <span class="ml-1 px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 normal-case font-normal">Inactive</span>
                        @endunless
                    </th>
                    @endforeach
                    <th class="px-3 py-3 text-left text-xs font-medium text-gray-500 uppercase whitespace-nowrap">Last updated</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach($grid->suites as $suite)
                @php
                    $regular = (float) $suite->price_per_day;
                    $regularOld = $oldCells->get($suite->id.':regular');
                @endphp
                <tr id="suite-{{ $suite->id }}" dusk="rates-row-{{ $suite->id }}" class="rate-row" data-suite="{{ $suite->id }}">
                    <td class="px-3 py-2 sticky left-0 bg-white z-10">
                        <input type="checkbox" class="room-select rounded" value="{{ $suite->id }}" dusk="select-room-{{ $suite->id }}">
                    </td>
                    <td class="px-3 py-2 sticky left-10 bg-white z-10">
                        <div class="font-semibold text-gray-800">{{ $suite->name }}</div>
                        @if($suite->room_number)<div class="text-xs text-gray-500">Room {{ $suite->room_number }}</div>@endif
                    </td>
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1">
                            <span class="text-gray-400">₱</span>
                            <input type="number" min="0" step="0.01" required
                                   class="rate-cell regular-cell w-28 px-2 py-1.5 border rounded-md text-right focus:outline-none focus:ring-2 focus:ring-purple-600"
                                   dusk="rate-{{ $suite->id }}-regular"
                                   data-suite="{{ $suite->id }}" data-platform=""
                                   data-original="{{ $fmt($regular) }}"
                                   value="{{ $regularOld ?? $fmt($regular) }}">
                        </div>
                    </td>
                    @foreach($grid->platforms as $platform)
                    @php
                        $rate = $grid->rate($suite->id, $platform->id);
                        $key = $suite->id.':'.$platform->id;
                    @endphp
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-1">
                            <span class="text-gray-400">₱</span>
                            <input type="number" min="0" step="0.01"
                                   class="rate-cell w-28 px-2 py-1.5 border rounded-md text-right placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-600"
                                   dusk="rate-{{ $suite->id }}-{{ $platform->id }}"
                                   data-suite="{{ $suite->id }}" data-platform="{{ $platform->id }}"
                                   data-original="{{ $fmt($rate) }}"
                                   placeholder="{{ $fmt($regular) }}"
                                   title="Empty = uses the regular rate"
                                   value="{{ $oldCells->has($key) ? $oldCells->get($key) : $fmt($rate) }}">
                        </div>
                    </td>
                    @endforeach
                    <td class="px-3 py-2 text-xs text-gray-500 whitespace-nowrap">
                        {{ optional($grid->lastUpdated($suite->id))->format('M d, Y') ?? '—' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Apply to selected rooms (fills cells only; nothing is saved until Save) --}}
    <div class="bg-white rounded-lg shadow p-4 mt-4">
        <p class="text-sm font-semibold text-gray-700 mb-3">
            <i class="fas fa-magic text-purple-600 mr-1"></i>For selected rooms
            <span class="font-normal text-gray-500">(<span id="selectedCount">0</span> selected)</span>
        </p>
        <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2">
            <select id="bulkColumn" dusk="bulk-column" class="px-3 py-2 border rounded-lg text-sm">
                <option value="">Regular</option>
                @foreach($grid->platforms as $platform)
                <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                @endforeach
            </select>
            <select id="bulkAction" dusk="bulk-action" class="px-3 py-2 border rounded-lg text-sm">
                <option value="set">Set to ₱</option>
                <option value="add">Raise by ₱</option>
                <option value="sub">Lower by ₱</option>
                <option value="pct_up">Raise by %</option>
                <option value="pct_down">Lower by %</option>
            </select>
            <input type="number" id="bulkValue" dusk="bulk-value" min="0" step="0.01" placeholder="Amount"
                   class="px-3 py-2 border rounded-lg text-sm sm:w-32">
            <button type="button" id="bulkApply" dusk="bulk-apply"
                    class="px-4 py-2 bg-purple-100 text-purple-800 font-semibold text-sm rounded-lg hover:bg-purple-200 transition">
                Apply
            </button>
        </div>
        <p class="text-xs text-red-600 mt-2" id="bulkMessage" dusk="bulk-message" style="display: none;"></p>
    </div>

    {{-- Sticky save bar --}}
    <div class="sticky bottom-0 mt-4 bg-white border-t-2 border-purple-200 shadow-lg rounded-lg p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 z-20">
        <p class="text-sm text-gray-700" dusk="unsaved-count">
            <span id="unsavedCount">0</span> unsaved change<span id="unsavedPlural">s</span>
        </p>
        <div class="flex gap-2">
            <button type="button" id="discardChanges" dusk="discard-changes" disabled
                    class="px-4 py-2 border rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed">
                Discard
            </button>
            <button type="submit" id="saveChanges" dusk="save-changes" disabled
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg text-sm font-semibold hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-save mr-2"></i>Save changes
            </button>
        </div>
    </div>
</form>
@endif

@include('admin.rates.partials.grid-script')
@endsection
