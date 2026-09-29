@extends('layouts.admin')
@section('page-title', ($isBookingEntry ?? false) ? 'Create New Booking' : 'Booking Calendar')
@section('main-content')

{{-- Header --}}
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-4">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900">
                {{ ($isBookingEntry ?? false) ? 'Select Date to Book' : 'Booking Calendar' }}
            </h2>
            <p class="text-sm text-gray-500 mt-1">
                Click an open date to create a booking. Click a client name to view booking status and details.
            </p>
        </div>
        @if($isBookingEntry ?? false)
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition text-sm font-medium">
            <i class="fas fa-list mr-2"></i>All Bookings
        </a>
        @endif
    </div>
</div>

{{-- Controls --}}
<div class="flex flex-wrap items-center justify-between gap-4 mb-4">
    <div class="flex items-center gap-2">
        <button id="prevMonth" class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50 transition">
            <i class="fas fa-chevron-left"></i>
        </button>
        <h3 id="monthLabel" class="text-xl font-bold text-gray-800 w-44 text-center"></h3>
        <button id="nextMonth" class="px-3 py-2 bg-white border rounded-lg hover:bg-gray-50 transition">
            <i class="fas fa-chevron-right"></i>
        </button>
        <button id="todayBtn" class="px-3 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">Today</button>
    </div>

    <div class="flex items-center gap-3 flex-wrap">
        <select id="statusFilter" class="px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
            <option value="">All Statuses</option>
            <option value="confirmed">Confirmed</option>
            <option value="completed">Completed</option>
            <option value="pending">Pending</option>
        </select>
        <select id="sectionFilter" class="px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-purple-500">
            <option value="">All Sections</option>
            <option value="venue">Venues Only</option>
            <option value="suite">Suites Only</option>
        </select>
        <button onclick="printCalendar()" class="px-3 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition text-sm">
            <i class="fas fa-print mr-1"></i>Print
        </button>
        <button onclick="exportCSV()" class="px-3 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
            <i class="fas fa-file-csv mr-1"></i>Export
        </button>
    </div>
</div>

{{-- Stats --}}
<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-4" id="statsBar">
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-purple-600" id="statTotal">-</div>
        <div class="text-xs text-gray-500 mt-1">Total Locations</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-green-500" id="statAvailable">-</div>
        <div class="text-xs text-gray-500 mt-1">Open Days</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-yellow-500" id="statPartial">-</div>
        <div class="text-xs text-gray-500 mt-1">Partial Days</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-red-500" id="statOccupied">-</div>
        <div class="text-xs text-gray-500 mt-1">Fully Booked</div>
    </div>
    <div class="bg-white rounded-lg shadow p-4 text-center">
        <div class="text-2xl font-bold text-blue-500" id="statRate">-</div>
        <div class="text-xs text-gray-500 mt-1">Occupancy Rate</div>
    </div>
</div>

{{-- Legend --}}
<div class="flex flex-wrap gap-3 mb-4 bg-white rounded-lg shadow px-4 py-3">
    <span class="text-xs font-semibold text-gray-500 mr-2 self-center">Legend:</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-green-600 inline-block"></span> Confirmed</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-purple-600 inline-block"></span> Completed</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-orange-600 inline-block"></span> Pending</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-green-50 border border-green-200 inline-block"></span> Available</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-amber-50 border border-amber-300 inline-block"></span> Partially Booked</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-red-50 border border-red-200 inline-block"></span> Fully Booked</span>
    <span class="flex items-center gap-1 text-xs"><span class="w-3 h-3 rounded bg-blue-100 border border-blue-300 inline-block"></span> Today</span>
</div>

<div id="notification" class="hidden mb-3 px-4 py-2 rounded-lg text-sm font-medium"></div>

{{-- Calendar --}}
<div class="bg-white rounded-lg shadow overflow-x-auto" id="calendarWrap">
    <div id="calendarLoading" class="py-20 text-center text-gray-400">
        <i class="fas fa-spinner fa-spin text-3xl mb-2"></i>
        <p>Loading calendar...</p>
    </div>
    <table id="calendarTable" class="min-w-full hidden text-xs border-collapse">
        <thead id="calendarHead" class="bg-gray-50 sticky top-0 z-10"></thead>
        <tbody id="calendarBody"></tbody>
    </table>
</div>

{{-- Booking Detail Card Modal --}}
<div id="bookingModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
    <div id="bookingModalCard" class="bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border-l-4 border-gray-300">
        <div class="flex items-start justify-between px-6 py-4 border-b bg-gray-50">
            <div>
                <p class="text-xs text-gray-500 mb-1" id="modalReference">—</p>
                <h3 class="font-bold text-gray-900 text-lg" id="modalClientName">Booking Details</h3>
            </div>
            <button onclick="closeModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none mt-1">&times;</button>
        </div>
        <div class="px-6 py-4">
            <div class="flex flex-wrap gap-2 mb-4" id="modalStatusRow"></div>
            <div id="modalBody" class="space-y-3 text-sm"></div>
        </div>
        <div class="px-6 py-4 border-t bg-gray-50 flex justify-end gap-2">
            <button onclick="closeModal()" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-100 text-sm">Close</button>
            <a id="modalViewBtn" href="#" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 text-sm">
                <i class="fas fa-external-link-alt mr-1"></i>View Full Booking
            </a>
        </div>
    </div>
</div>

<script>
const HIGHLIGHT_VENUE_ID = {{ $highlightVenueId ?? 'null' }};
const CREATE_BOOKING_URL = @json(route('admin.bookings.create'));

let currentYear = {{ date('Y') }};
let currentMonth = {{ date('n') }};
let calendarData = null;
let statusFilter = '';
let sectionFilter = '';
const bookingCache = {};

const statusConfig = {
    confirmed: {
        chip: 'bg-green-600 text-white',
        badge: 'bg-green-100 text-green-800',
        border: 'border-green-600',
        label: 'Confirmed',
    },
    completed: {
        chip: 'bg-purple-600 text-white',
        badge: 'bg-purple-100 text-purple-800',
        border: 'border-purple-600',
        label: 'Completed',
    },
    pending: {
        chip: 'bg-orange-600 text-white',
        badge: 'bg-orange-100 text-orange-800',
        border: 'border-orange-600',
        label: 'Pending',
    },
};

const payConfig = {
    paid:    { badge: 'bg-green-100 text-green-800', label: 'Paid' },
    partial: { badge: 'bg-yellow-100 text-yellow-800', label: 'Partial' },
    unpaid:  { badge: 'bg-red-100 text-red-800', label: 'Unpaid' },
};

const slotLabels = {
    morning: 'AM',
    afternoon: 'PM',
    evening: 'Eve',
    suite: 'Suite',
    'full-day': 'Full',
};

const slotDisplayLabels = {
    morning: 'Morning',
    afternoon: 'Afternoon',
    evening: 'Evening',
};

function getBookingSlotLabel(booking, venueType) {
    if (venueType === 'suite') {
        return 'Suite';
    }

    const slots = booking.time_slots_raw || [];
    if (!slots.length) {
        return 'Full Day';
    }

    return slots
        .map(slot => slotDisplayLabels[slot] || slot.charAt(0).toUpperCase() + slot.slice(1))
        .join(' · ');
}

function showNotification(msg, type = 'info') {
    const el = document.getElementById('notification');
    el.className = `mb-3 px-4 py-2 rounded-lg text-sm font-medium ${type === 'error' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700'}`;
    el.textContent = msg;
    el.classList.remove('hidden');
    setTimeout(() => el.classList.add('hidden'), 3000);
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// Safe to drop straight into an inline onclick="..." attribute: produces a
// valid JS string literal (via JSON.stringify), then escapes the characters
// that would otherwise break out of the surrounding HTML attribute.
function jsAttr(str) {
    return JSON.stringify(String(str))
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

async function loadCalendar() {
    document.getElementById('calendarLoading').classList.remove('hidden');
    document.getElementById('calendarTable').classList.add('hidden');

    try {
        const res = await fetch(`{{ route('admin.bookings.calendar.data') }}?year=${currentYear}&month=${currentMonth}`);
        calendarData = await res.json();
        renderCalendar();
    } catch (e) {
        showNotification('Failed to load calendar data.', 'error');
    }
}

function renderCalendar() {
    const data = calendarData;
    document.getElementById('monthLabel').textContent = data.month_name;

    document.getElementById('statTotal').textContent = data.stats.total_venues;
    document.getElementById('statAvailable').textContent = data.stats.available;
    document.getElementById('statPartial').textContent = data.stats.partial;
    document.getElementById('statOccupied').textContent = data.stats.occupied;
    document.getElementById('statRate').textContent = data.stats.occupancy_rate + '%';

    const venueList = data.venues.filter(v => v.type === 'venue');
    const suiteList = data.venues.filter(v => v.type === 'suite');
    const dayCount = data.days.length;

    const head = document.getElementById('calendarHead');
    head.innerHTML = '';
    let hRow = '<tr><th class="px-3 py-2 text-left text-gray-600 font-semibold border-b border-r bg-gray-50 sticky left-0 z-20 min-w-36">Location</th>';
    data.days.forEach(d => {
        const todayCls = d.is_today ? 'bg-blue-50 text-blue-700' : d.is_weekend ? 'bg-gray-100' : '';
        hRow += `<th class="px-2 py-2 text-center border-b border-r min-w-20 ${todayCls}">
            <div class="font-bold">${d.day}</div>
            <div class="text-gray-400 font-normal">${d.day_name}</div>
        </th>`;
    });
    head.innerHTML = hRow + '</tr>';

    const body = document.getElementById('calendarBody');
    body.innerHTML = '';
    Object.keys(bookingCache).forEach(key => delete bookingCache[key]);

    if (!sectionFilter || sectionFilter === 'venue') {
        renderSection(body, 'Venues', 'fa-building', 'venue', venueList, data, dayCount);
    }
    if (!sectionFilter || sectionFilter === 'suite') {
        renderSection(body, 'Suites', 'fa-bed', 'suite', suiteList, data, dayCount);
    }

    document.getElementById('calendarLoading').classList.add('hidden');
    document.getElementById('calendarTable').classList.remove('hidden');
}

function renderSection(body, title, icon, type, venues, data, dayCount) {
    if (venues.length === 0) return;

    const sectionIconCls = type === 'venue' ? 'text-purple-600' : 'text-indigo-600';
    body.innerHTML += `<tr class="section-row">
        <td colspan="${dayCount + 1}" class="px-4 py-2 border-b bg-indigo-50 text-indigo-800 font-bold text-sm sticky left-0">
            <i class="fas ${icon} mr-2 ${sectionIconCls}"></i>${title}
            <span class="ml-2 text-xs font-normal opacity-75">(${venues.length})</span>
        </td>
    </tr>`;

    venues.forEach(venue => {
        const highlightCls = HIGHLIGHT_VENUE_ID && Number(HIGHLIGHT_VENUE_ID) === venue.id ? 'ring-2 ring-purple-400 ring-inset bg-purple-50' : '';
        let row = `<tr class="hover:bg-gray-50 transition venue-row ${highlightCls}" data-venue-id="${venue.id}">
            <td class="px-3 py-2 border-b border-r sticky left-0 bg-white z-10 min-w-36">
                <div class="font-semibold text-gray-800 text-xs">${escapeHtml(venue.name)}</div>
                <div class="text-gray-400 text-xs capitalize">${venue.type}${venue.room_number ? ' · Room ' + escapeHtml(venue.room_number) : ''} · ${venue.capacity} pax</div>
            </td>`;

        data.days.forEach(d => {
            const venueDay = d.venues[venue.id] || { bookings: [], availability: { status: 'available', can_book: !d.is_past } };
            const bookings = (venueDay.bookings || []).filter(b => !statusFilter || b.status === statusFilter);
            const availability = venueDay.availability || {};
            row += renderDayCell(d, venue, bookings, availability);
        });

        body.innerHTML += row + '</tr>';
    });
}

function renderDayCell(day, venue, bookings, availability) {
    const todayCls = day.is_today ? 'bg-blue-50' : day.is_past ? 'bg-gray-50' : '';
    const status = availability.status || 'available';
    const canBook = availability.can_book && !day.is_past;

    let bgCls = '';
    if (status === 'partially-booked') bgCls = 'bg-amber-50';
    else if (status === 'fully-booked') bgCls = 'bg-red-50';
    else if (status === 'available' && canBook) bgCls = 'hover:bg-green-50';

    let html = `<td class="px-1 py-1 border-b border-r align-top min-h-12 ${todayCls} ${bgCls} ${canBook ? 'group cursor-pointer' : ''}"`;

    if (canBook) {
        html += ` onclick="openCreateBooking('${day.date}', ${venue.id}, ${jsAttr(venue.name)})"`;
    }

    html += '>';

    if (bookings.length > 0) {
        html += bookings.map(b => {
            const cacheKey = `b-${b.id}-${day.date}-${venue.id}`;
            bookingCache[cacheKey] = { ...b, venue_name: venue.name, cell_date: day.date };
            const cfg = statusConfig[b.status] || { chip: 'bg-gray-500 text-white', label: b.status };
            const slotLabel = getBookingSlotLabel(b, venue.type);
            const slotHint = b.time_slots ? ` · ${b.time_slots}` : '';
            const firstName = escapeHtml(b.client_name.split(' ')[0]);
            return `<button type="button"
                class="booking-chip w-full text-left ${cfg.chip} rounded px-1.5 py-1 mb-0.5 text-xs leading-tight font-medium hover:opacity-90 hover:shadow-sm transition"
                data-booking-key="${cacheKey}"
                title="Click to view: ${escapeHtml(b.client_name)} · ${escapeHtml(slotLabel)} · ${escapeHtml(b.reference)}${slotHint}">
                <span class="block truncate font-semibold">${firstName}</span>
                <span class="block truncate text-[10px] opacity-90 font-normal">${escapeHtml(slotLabel)}</span>
            </button>`;
        }).join('');
    }

    if (canBook) {
        if (status === 'available' && bookings.length === 0) {
            html += `<div class="text-center py-1">
                <span class="text-gray-200 group-hover:hidden text-xs">—</span>
                <span class="hidden group-hover:inline-flex items-center justify-center w-full">
                    <span class="bg-green-500 text-white text-xs rounded px-1.5 py-0.5 font-semibold whitespace-nowrap">
                        <i class="fas fa-plus text-xs mr-0.5"></i>Book
                    </span>
                </span>
            </div>`;
        } else if (status === 'partially-booked') {
            const slots = (availability.available_slots || []).map(s => slotLabels[s] || s).join(', ');
            html += `<div class="text-center mt-0.5">
                <span class="hidden group-hover:inline-flex items-center justify-center w-full">
                    <span class="bg-green-600 text-white text-xs rounded px-1 py-0.5 font-semibold whitespace-nowrap" title="Available: ${slots}">
                        <i class="fas fa-plus text-xs mr-0.5"></i>Book
                    </span>
                </span>
                <span class="text-amber-600 text-[10px] group-hover:hidden">${slots ? '+' + slots : ''}</span>
            </div>`;
        }
    } else if (bookings.length === 0) {
        html += `<div class="text-center py-1"><span class="text-gray-300 text-xs">—</span></div>`;
    }

    html += '</td>';
    return html;
}

function openModal(b) {
    const status = statusConfig[b.status] || { badge: 'bg-gray-100 text-gray-800', border: 'border-gray-400', label: b.status || 'Unknown' };
    const payment = payConfig[b.payment_status] || { badge: 'bg-gray-100 text-gray-800', label: b.payment_status || 'Unknown' };

    document.getElementById('modalReference').textContent = b.reference || '—';
    document.getElementById('modalClientName').textContent = b.client_name || 'Booking Details';
    document.getElementById('modalViewBtn').href = `/admin/bookings/${b.id}`;

    const modalCard = document.getElementById('bookingModalCard');
    modalCard.className = `bg-white rounded-xl shadow-2xl w-full max-w-md overflow-hidden border-l-4 ${status.border}`;

    document.getElementById('modalStatusRow').innerHTML = `
        <span class="px-3 py-1 rounded-full text-xs font-semibold ${status.badge}">
            <i class="fas fa-circle text-[8px] mr-1 align-middle"></i>${status.label}
        </span>
        <span class="px-3 py-1 rounded-full text-xs font-semibold ${payment.badge}">
            Payment: ${payment.label}
        </span>
    `;

    document.getElementById('modalBody').innerHTML = `
        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Location</span><span class="font-semibold text-right">${escapeHtml(b.venue_name || '—')}</span></div>
        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Email</span><span class="text-right break-all">${escapeHtml(b.client_email)}</span></div>
        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Phone</span><span class="text-right">${escapeHtml(b.client_phone)}</span></div>
        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Date</span><span class="text-right">${escapeHtml(b.booking_date)}${b.number_of_days > 1 ? ' → ' + escapeHtml(b.end_date) : ''}</span></div>
        <div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Time Slot</span><span class="text-right">${escapeHtml(b.time_slots || 'Full Day')}</span></div>
        ${b.package ? `<div class="flex justify-between gap-4"><span class="text-gray-500 shrink-0">Package</span><span class="text-right">${escapeHtml(b.package)}</span></div>` : ''}
        <div class="flex justify-between gap-4 pt-2 border-t"><span class="text-gray-500 shrink-0">Amount</span><span class="font-bold text-purple-700 text-right">₱${parseFloat(b.total_amount).toLocaleString('en-PH', {minimumFractionDigits:2})}</span></div>
    `;
    document.getElementById('bookingModal').classList.remove('hidden');
}

function openModalByKey(key) {
    const booking = bookingCache[key];
    if (booking) openModal(booking);
}

function closeModal() {
    document.getElementById('bookingModal').classList.add('hidden');
}

function printCalendar() {
    window.print();
}

function exportCSV() {
    if (!calendarData) return;
    const rows = [['Reference','Client','Email','Phone','Location','Type','Date','End Date','Days','Time Slot','Package','Amount','Status','Payment']];
    calendarData.days.forEach(d => {
        calendarData.venues.forEach(v => {
            const venueDay = d.venues[v.id] || { bookings: [] };
            (venueDay.bookings || []).forEach(b => {
                rows.push([b.reference, b.client_name, b.client_email, b.client_phone, v.name, v.type,
                    b.booking_date, b.end_date, b.number_of_days, b.time_slots,
                    b.package || '', b.total_amount, b.status, b.payment_status]);
            });
        });
    });
    const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,\uFEFF' + encodeURIComponent(csv);
    a.download = `bookings-${calendarData.month_name.replace(' ','-')}.csv`;
    a.click();
    showNotification('CSV exported successfully.');
}

function openCreateBooking(date, venueId, venueName) {
    window.location.href = `${CREATE_BOOKING_URL}?booking_date=${date}&venue_id=${venueId}`;
}

document.getElementById('prevMonth').addEventListener('click', () => {
    const d = new Date(currentYear, currentMonth - 2);
    currentYear = d.getFullYear(); currentMonth = d.getMonth() + 1;
    loadCalendar();
});
document.getElementById('nextMonth').addEventListener('click', () => {
    const d = new Date(currentYear, currentMonth);
    currentYear = d.getFullYear(); currentMonth = d.getMonth() + 1;
    loadCalendar();
});
document.getElementById('todayBtn').addEventListener('click', () => {
    currentYear = {{ date('Y') }}; currentMonth = {{ date('n') }};
    loadCalendar();
});
document.getElementById('statusFilter').addEventListener('change', e => { statusFilter = e.target.value; if (calendarData) renderCalendar(); });
document.getElementById('sectionFilter').addEventListener('change', e => { sectionFilter = e.target.value; if (calendarData) renderCalendar(); });
document.getElementById('bookingModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });

document.getElementById('calendarBody').addEventListener('click', function(e) {
    const chip = e.target.closest('.booking-chip');
    if (chip) {
        e.preventDefault();
        e.stopPropagation();
        openModalByKey(chip.dataset.bookingKey);
    }
});

loadCalendar();
</script>

<style>
@media print {
    .sidebar, nav, button, select, #statsBar, #notification { display: none !important; }
    #calendarWrap { overflow: visible !important; }
}
.section-row td { position: sticky; left: 0; z-index: 5; }
.booking-chip { cursor: pointer; border: none; display: block; min-width: 0; }
.booking-chip:hover { transform: scale(1.02); }
.booking-chip span { pointer-events: none; }

/* Wider, more visible horizontal scrollbar */
#calendarWrap {
    scrollbar-width: auto;
    scrollbar-color: #a855f7 #f3f4f6;
}
#calendarWrap::-webkit-scrollbar {
    height: 16px;
}
#calendarWrap::-webkit-scrollbar-track {
    background: #f3f4f6;
}
#calendarWrap::-webkit-scrollbar-thumb {
    background-color: #a855f7;
    border-radius: 8px;
    border: 3px solid #f3f4f6;
}
#calendarWrap::-webkit-scrollbar-thumb:hover {
    background-color: #9333ea;
}
</style>
@endsection
