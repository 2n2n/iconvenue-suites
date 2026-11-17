@extends('layouts.app')

@section('content')
<div class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 text-white shadow-2xl slide-in sticky top-0 h-screen overflow-y-auto flex-shrink-0" style="background: linear-gradient(180deg, #1a0e05 0%, #2d1a08 100%); border-right: 1px solid rgba(201,168,76,0.3);">
        <div class="p-6" style="border-bottom: 1px solid rgba(201,168,76,0.3);">
            <div class="flex items-center space-x-3">
                <img src="{{ asset('images/logo.jpg') }}" alt="Icon Venue & Suites" class="h-10 w-10 object-contain">
                <div>
                    <h2 class="text-xl font-bold">Icon Admin</h2>
                    <p class="text-xs text-gray-400">{{ ucfirst(auth()->user()->role->name) }}</p>
                </div>
            </div>
        </div>
        <nav class="mt-6 px-3 pb-6">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-chart-line mr-3 w-5"></i> 
                <span class="font-medium">Dashboard</span>
            </a>
            <a href="{{ route('admin.bookings.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.bookings.index') || request()->routeIs('admin.bookings.show') || request()->routeIs('admin.bookings.edit') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-calendar-check mr-3 w-5"></i>
                <span class="font-medium">Bookings</span>
            </a>
            <a href="{{ route('admin.bookings.create') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.bookings.create') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-calendar-plus mr-3 w-5"></i>
                <span class="font-medium">New Booking</span>
            </a>
            <a href="{{ route('admin.clients.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.clients.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-users mr-3 w-5"></i>
                <span class="font-medium">Clients</span>
            </a>
            <a href="{{ route('admin.payments.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.payments.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-money-bill-wave mr-3 w-5"></i> 
                <span class="font-medium">Payments</span>
            </a>
            
            @if(!auth()->user()->isAdmin())
            <div class="border-t border-gray-700 my-4"></div>
            <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Browse</p>
            <a href="{{ route('admin.venues.browse') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.venues.browse') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-building mr-3 w-5"></i> 
                <span class="font-medium">Manage Venues</span>
            </a>
            <a href="{{ route('admin.suites.browse') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.suites.browse') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-bed mr-3 w-5"></i> 
                <span class="font-medium">Manage Suites</span>
            </a>
            @endif
            
            @if(auth()->user()->isAdmin())
            <div class="border-t border-gray-700 my-4"></div>
            <p class="px-4 text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Admin Only</p>
            <a href="{{ route('admin.venues.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.venues.*') && request()->get('type') != 'suite' ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-building mr-3 w-5"></i> 
                <span class="font-medium">Venues</span>
            </a>
            <a href="{{ route('admin.suites.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.suites.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-bed mr-3 w-5"></i> 
                <span class="font-medium">Suites</span>
            </a>
            <a href="{{ route('admin.rates.index') }}" dusk="nav-room-rates" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.rates.*') || request()->routeIs('admin.booking-platforms.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-tags mr-3 w-5"></i>
                <span class="font-medium">Room Rates</span>
            </a>
            <a href="{{ route('admin.staff.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.staff.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-users mr-3 w-5"></i> 
                <span class="font-medium">Staff</span>
            </a>
            <a href="{{ route('admin.reports.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.reports.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-file-alt mr-3 w-5"></i> 
                <span class="font-medium">Reports</span>
            </a>
            <a href="{{ route('admin.settings.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.settings.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-cog mr-3 w-5"></i> 
                <span class="font-medium">Settings</span>
            </a>
            <a href="{{ route('admin.carousel.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.carousel.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-images mr-3 w-5"></i> 
                <span class="font-medium">Carousel</span>
            </a>
            <a href="{{ route('admin.addons.index') }}" class="flex items-center px-4 py-3 mb-2 rounded-lg hover:bg-gray-700 transition-all duration-200 {{ request()->routeIs('admin.addons.*') ? 'bg-gradient-to-r from-purple-600 to-pink-600 shadow-lg' : '' }}">
                <i class="fas fa-plus-circle mr-3 w-5"></i> 
                <span class="font-medium">Add-ons</span>
            </a>
            @endif
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
        <!-- Top Bar -->
        <header class="bg-white shadow-md fade-in">
            <div class="flex justify-between items-center px-8 py-4">
                <div>
                    <h1 class="text-3xl font-bold bg-gradient-to-r from-purple-600 to-pink-600 bg-clip-text text-transparent">@yield('page-title')</h1>
                    <p class="text-sm text-gray-500 mt-1">Welcome back, {{ auth()->user()->name }}!</p>
                </div>
                
                <!-- Profile Dropdown -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" class="flex items-center space-x-3 focus:outline-none hover:bg-gray-50 rounded-lg px-4 py-2 transition-all duration-200">
                        @if(auth()->user()->profile_image)
                        <img src="{{ asset('storage/' . auth()->user()->profile_image) }}" 
                             alt="Profile" 
                             class="w-10 h-10 rounded-full object-cover border-2 border-purple-300 shadow">
                        @else
                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-purple-500 to-pink-500 flex items-center justify-center text-white font-bold border-2 border-purple-300 shadow">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        @endif
                        <div class="text-left">
                            <p class="text-sm font-semibold text-gray-700">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ ucfirst(auth()->user()->role->name) }}</p>
                        </div>
                        <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                    </button>
                    
                    <!-- Dropdown Menu -->
                    <div x-show="open" 
                         @click.away="open = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform scale-95"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 transform scale-100"
                         x-transition:leave-end="opacity-0 transform scale-95"
                         class="absolute right-0 mt-2 w-56 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50"
                         style="display: none;">
                        
                        <!-- Profile Info -->
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-800">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ auth()->user()->email }}</p>
                        </div>
                        
                        <!-- Menu Items -->
                        <a href="{{ route('admin.profile.edit') }}" 
                           class="flex items-center px-4 py-3 text-sm text-gray-700 hover:bg-purple-50 transition-colors duration-150">
                            <i class="fas fa-user-circle mr-3 text-purple-600 w-5"></i>
                            <span>My Profile</span>
                        </a>
                        
                        <div class="border-t border-gray-100 my-1"></div>
                        
                        <form action="{{ route('logout') }}" method="POST">
                            @csrf
                            <button type="submit" 
                                    class="w-full flex items-center px-4 py-3 text-sm text-red-600 hover:bg-red-50 transition-colors duration-150">
                                <i class="fas fa-sign-out-alt mr-3 w-5"></i>
                                <span>Logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <!-- Page Content -->
        <main class="flex-1 overflow-x-hidden p-8">
            <div class="fade-in">
                @yield('main-content')
            </div>
        </main>
    </div>
    
    <!-- Floating Price Display (Only for booking pages) -->
    @if(request()->routeIs('admin.bookings.create') && request()->filled('booking_date') && request()->filled('venue_id'))
    <div id="floatingPriceDisplay" class="floating-price-widget hidden">
        <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-lg p-4 shadow-lg">
            <div class="text-center mb-4">
                <p class="text-sm font-medium text-purple-800 mb-1">Estimated Price</p>
                <p class="text-2xl font-bold text-purple-700" id="floatingDisplayPrice">₱0.00</p>
                <p class="text-xs text-purple-600 mt-1" id="floatingPriceDescription">Full day rental</p>
            </div>
            
            <!-- Price Breakdown -->
            <div id="floatingPriceBreakdown" class="border-t border-purple-200 pt-3 space-y-2 text-sm">
                <!-- Venue Price -->
                <div class="flex justify-between items-center">
                    <span class="text-purple-700" id="floatingVenueLabel">Venue</span>
                    <span class="font-medium text-purple-800" id="floatingVenuePrice">₱0.00</span>
                </div>
                
                <!-- Selected Add-ons -->
                <div id="floatingSelectedAddonsBreakdown" class="hidden">
                    <div class="text-xs font-medium text-purple-600 mb-2 border-b border-purple-100 pb-1">Selected Add-ons:</div>
                    <div id="floatingAddonsBreakdownList" class="space-y-1"></div>
                </div>
                
                <!-- Total Line -->
                <div class="border-t border-purple-200 pt-2 flex justify-between items-center font-semibold">
                    <span class="text-purple-800">Total Amount</span>
                    <span class="text-purple-800" id="floatingTotalAmount">₱0.00</span>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>

<!-- Toast Notifications Container -->
<div id="toast-container" class="fixed top-4 right-4 z-50 space-y-3" style="max-width: 400px;">
    @if(session('success'))
    <div class="toast-notification bg-white rounded-xl shadow-2xl overflow-hidden transform transition-all duration-500 ease-out"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="translate-x-full opacity-0">
        <div class="flex items-start p-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-green-400 to-green-600 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-check text-white text-xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <p class="text-sm text-gray-800 font-medium">{{ session('success') }}</p>
            </div>
            <button @click="show = false" class="ml-4 text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="h-1 bg-gradient-to-r from-green-400 to-green-600 toast-progress"></div>
    </div>
    @endif

    @if(session('info'))
    <div class="toast-notification bg-white rounded-xl shadow-2xl overflow-hidden transform transition-all duration-500 ease-out"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="translate-x-full opacity-0">
        <div class="flex items-start p-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-400 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-info-circle text-white text-xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <p class="text-sm text-gray-800 font-medium">{{ session('info') }}</p>
            </div>
            <button @click="show = false" class="ml-4 text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="h-1 bg-gradient-to-r from-blue-400 to-blue-600 toast-progress"></div>
    </div>
    @endif

    @if($errors->any())
    <div class="toast-notification bg-white rounded-xl shadow-2xl overflow-hidden transform transition-all duration-500 ease-out"
         x-data="{ show: true }"
         x-show="show"
         x-init="setTimeout(() => show = false, 5000)"
         x-transition:enter="translate-x-full opacity-0"
         x-transition:enter-end="translate-x-0 opacity-100"
         x-transition:leave="translate-x-full opacity-0">
        <div class="flex items-start p-4">
            <div class="flex-shrink-0">
                <div class="w-12 h-12 bg-gradient-to-br from-red-400 to-red-600 rounded-full flex items-center justify-center shadow-lg">
                    <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <div class="text-sm text-gray-800 space-y-1">
                    @foreach($errors->all() as $error)
                    <p class="font-medium">{{ $error }}</p>
                    @endforeach
                </div>
            </div>
            <button @click="show = false" class="ml-4 text-gray-400 hover:text-gray-600 transition">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="h-1 bg-gradient-to-r from-red-400 to-red-600 toast-progress"></div>
    </div>
    @endif
</div>

<style>
@keyframes toast-progress {
    from { width: 100%; }
    to { width: 0%; }
}

.toast-progress {
    animation: toast-progress 5s linear forwards;
}

.toast-notification {
    animation: slideInRight 0.5s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

/* Sidebar active link */
nav a.bg-gradient-to-r {
    background: linear-gradient(135deg, rgba(201,168,76,0.2), rgba(139,105,20,0.3)) !important;
    border-left: 3px solid #c9a84c;
}

/* Sidebar nav link hover */
nav a:hover {
    background: rgba(201,168,76,0.1) !important;
}

/* Header */
header.bg-white {
    background: linear-gradient(135deg, #1a0e05, #2d1a08) !important;
    border-bottom: 1px solid rgba(201,168,76,0.3) !important;
    box-shadow: 0 2px 20px rgba(0,0,0,0.5) !important;
}

/* Page title gradient text */
.bg-clip-text.text-transparent {
    -webkit-background-clip: text !important;
    background-clip: text !important;
    background-image: linear-gradient(135deg, #c9a84c, #f0d080) !important;
    color: transparent !important;
}

/* Floating price widget */
.floating-price-widget .bg-gradient-to-br {
    background: linear-gradient(135deg, #2d1a08, #1a0e05) !important;
    border: 1px solid rgba(201,168,76,0.4) !important;
    box-shadow: 0 20px 40px rgba(0,0,0,0.5), 0 0 20px rgba(201,168,76,0.1) !important;
}

/* Floating Price Widget */
.floating-price-widget {
    position: fixed;
    top: 120px; /* Increased from 80px to avoid header overlap */
    right: 20px;
    width: 320px;
    z-index: 40;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transform: translateY(-10px);
    opacity: 0;
}

.floating-price-widget:not(.hidden) {
    transform: translateY(0);
    opacity: 1;
}

.floating-price-widget .bg-gradient-to-br {
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    border: 2px solid rgba(139, 92, 246, 0.2);
}

/* Mobile responsiveness - hide on small screens */
@media (max-width: 1023px) {
    .floating-price-widget {
        display: none !important;
    }
}

/* Ensure proper positioning on different screen sizes */
@media (min-width: 1024px) and (max-width: 1400px) {
    .floating-price-widget {
        top: 110px; /* Slightly less top margin for smaller screens */
        right: 15px;
        width: 300px;
    }
}

@media (min-width: 1400px) {
    .floating-price-widget {
        top: 130px; /* More top margin for larger screens */
        right: 25px;
        width: 340px;
    }
}

/* Ensure it doesn't interfere with dropdowns */
.floating-price-widget {
    pointer-events: auto;
}

/* Animation for showing/hiding */
.floating-price-widget.show {
    animation: floatIn 0.5s ease-out forwards;
}

.floating-price-widget.hide {
    animation: floatOut 0.3s ease-in forwards;
}

@keyframes floatIn {
    from {
        transform: translateY(-20px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

@keyframes floatOut {
    from {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
    to {
        transform: translateY(-20px) scale(0.95);
        opacity: 0;
    }
}
</style>

@endsection
