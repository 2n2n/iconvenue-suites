@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center relative overflow-hidden" style="background: linear-gradient(135deg, #1a0e05 0%, #2d1a08 40%, #1a0e05 100%);">

    <!-- Background pattern -->
    <div class="absolute inset-0 opacity-5" style="background-image: url('data:image/svg+xml,%3Csvg width=\'60\' height=\'60\' viewBox=\'0 0 60 60\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Cg fill=\'none\' fill-rule=\'evenodd\'%3E%3Cg fill=\'%23c9a84c\' fill-opacity=\'1\'%3E%3Cpath d=\'M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E');"></div>

    <!-- Glow effects -->
    <div class="absolute top-1/4 left-1/4 w-96 h-96 rounded-full opacity-10 blur-3xl" style="background: radial-gradient(circle, #c9a84c, transparent);"></div>
    <div class="absolute bottom-1/4 right-1/4 w-96 h-96 rounded-full opacity-10 blur-3xl" style="background: radial-gradient(circle, #c9a84c, transparent);"></div>

    <!-- Card -->
    <div class="relative w-full max-w-md mx-4">

        <!-- Gold border glow -->
        <div class="absolute -inset-0.5 rounded-2xl opacity-60 blur-sm" style="background: linear-gradient(135deg, #c9a84c, #8b6914, #c9a84c);"></div>

        <div class="relative rounded-2xl overflow-hidden shadow-2xl" style="background: linear-gradient(160deg, #2d1a08 0%, #1a0e05 100%);">

            <!-- Top accent bar -->
            <div class="h-1 w-full" style="background: linear-gradient(90deg, #8b6914, #c9a84c, #f0d080, #c9a84c, #8b6914);"></div>

            <div class="px-8 pt-8 pb-10">

                <!-- Logo & Title -->
                <div class="text-center mb-8">
                    <div class="inline-block rounded-xl p-1 mb-5 shadow-xl" style="background: linear-gradient(135deg, #c9a84c, #8b6914);">
                        <img src="{{ asset('images/logo.jpg') }}" alt="Icon Venue & Suites" class="h-24 w-24 rounded-lg object-cover">
                    </div>

                    <h1 class="text-2xl font-bold tracking-widest uppercase mb-1" style="color: #c9a84c;">Icon Venue & Suites</h1>

                    <!-- Divider -->
                    <div class="flex items-center justify-center gap-3 my-3">
                        <div class="h-px w-12" style="background: linear-gradient(90deg, transparent, #c9a84c);"></div>
                        <i class="fas fa-shield-alt text-xs" style="color: #c9a84c;"></i>
                        <div class="h-px w-12" style="background: linear-gradient(90deg, #c9a84c, transparent);"></div>
                    </div>

                    <p class="text-xs tracking-widest uppercase font-semibold" style="color: #8b6914;">Authorized Personnel Only</p>
                </div>

                <!-- Error messages -->
                @if($errors->any())
                <div dusk="login-errors" class="mb-6 rounded-lg px-4 py-3 border text-sm" style="background: rgba(220,38,38,0.1); border-color: rgba(220,38,38,0.3); color: #fca5a5;">
                    <div class="flex items-center gap-2 mb-1">
                        <i class="fas fa-exclamation-circle"></i>
                        <span class="font-semibold">Access Denied</span>
                    </div>
                    @foreach($errors->all() as $error)
                        <p class="ml-5 text-xs">{{ $error }}</p>
                    @endforeach
                </div>
                @endif

                @if(session('success'))
                <div class="mb-6 rounded-lg px-4 py-3 border text-sm" style="background: rgba(16,185,129,0.1); border-color: rgba(16,185,129,0.3); color: #6ee7b7;">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
                @endif

                <!-- Form -->
                <form dusk="login-form" action="{{ route('login') }}" method="POST" class="space-y-5">
                    @csrf

                    <!-- Email -->
                    <div>
                        <label class="block text-xs font-semibold tracking-widest uppercase mb-2" style="color: #c9a84c;">
                            <i class="fas fa-user mr-1"></i> Email Address
                        </label>
                        <div class="relative">
                            <input dusk="login-email" type="email" name="email" value="{{ old('email') }}" required autofocus
                                placeholder="Enter your email"
                                class="w-full px-4 py-3 rounded-lg text-sm outline-none transition-all duration-200">
                        </div>
                    </div>

                    <!-- Password -->
                    <div>
                        <label class="block text-xs font-semibold tracking-widest uppercase mb-2" style="color: #c9a84c;">
                            <i class="fas fa-lock mr-1"></i> Password
                        </label>
                        <div class="relative">
                            <input dusk="login-password" type="password" name="password" id="passwordInput" required
                                placeholder="Enter your password"
                                class="w-full px-4 py-3 pr-12 rounded-lg text-sm outline-none transition-all duration-200">
                            <button type="button" onclick="togglePassword()" class="absolute right-3 top-1/2 -translate-y-1/2 transition-colors" style="color: #8b6914;" onmouseover="this.style.color='#c9a84c'" onmouseout="this.style.color='#8b6914'">
                                <i class="fas fa-eye text-sm" id="eyeIcon"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember me -->
                    <div class="flex items-center gap-2">
                        <input type="checkbox" name="remember" id="remember"
                            class="w-4 h-4 rounded cursor-pointer"
                            style="accent-color: #c9a84c;">
                        <label for="remember" class="text-xs cursor-pointer" style="color: #d4b87a;">Keep me signed in</label>
                    </div>

                    <!-- Submit -->
                    <button dusk="login-submit" type="submit"
                        class="w-full py-3 rounded-lg font-bold text-sm tracking-widest uppercase transition-all duration-300 transform hover:scale-[1.02] active:scale-[0.98] shadow-lg mt-2"
                        style="background: linear-gradient(135deg, #8b6914, #c9a84c, #8b6914); color: #1a0e05;"
                        onmouseover="this.style.background='linear-gradient(135deg, #c9a84c, #f0d080, #c9a84c)'"
                        onmouseout="this.style.background='linear-gradient(135deg, #8b6914, #c9a84c, #8b6914)'">
                        <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                    </button>
                </form>

                <!-- Back link -->
                <div class="mt-6 text-center">
                    <a href="{{ route('home') }}" class="text-xs tracking-wider transition-colors" style="color: #5a4010;"
                        onmouseover="this.style.color='#c9a84c'" onmouseout="this.style.color='#5a4010'">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Website
                    </a>
                </div>
            </div>

            <!-- Bottom accent bar -->
            <div class="h-0.5 w-full" style="background: linear-gradient(90deg, transparent, #8b6914, transparent);"></div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const input = document.getElementById('passwordInput');
    const icon  = document.getElementById('eyeIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>
@endsection
