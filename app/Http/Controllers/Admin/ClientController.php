<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->search;

        $query = Booking::with(['venue', 'payments'])
            ->select('client_name', 'client_email', 'client_phone')
            ->selectRaw('COUNT(*) as total_bookings')
            ->selectRaw('SUM(total_amount) as total_spent')
            ->selectRaw('MAX(booking_date) as last_booking_date')
            ->groupBy('client_email', 'client_name', 'client_phone');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('client_email', 'like', "%{$search}%")
                    ->orWhere('client_phone', 'like', "%{$search}%");
            });
        }

        $clients = $query->orderByDesc('last_booking_date')->paginate(15);

        return view('admin.clients.index', compact('clients', 'search'));
    }

    /**
     * Bookings of one client. The key is the client email, or "tel-<phone>" for clients
     * without an email (email is optional on bookings; see Booking::getClientKeyAttribute()).
     */
    private function clientQuery(string $key)
    {
        if (str_starts_with($key, 'tel-')) {
            $phone = substr($key, 4);

            return Booking::where(function ($q) {
                $q->whereNull('client_email')->orWhere('client_email', '');
            })->where('client_phone', $phone);
        }

        return Booking::where('client_email', $key);
    }

    public function show(string $email)
    {
        $bookings = $this->clientQuery($email)
            ->with(['venue', 'package', 'payments'])
            ->latest()
            ->get();

        abort_if($bookings->isEmpty(), 404);

        $client = [
            'key' => $email,
            'name' => $bookings->first()->client_name,
            'email' => $bookings->first()->hasClientEmail() ? $bookings->first()->client_email : null,
            'phone' => $bookings->first()->client_phone,
        ];

        return view('admin.clients.show', compact('client', 'bookings'));
    }

    public function update(Request $request, string $email)
    {
        $validated = $request->validate([
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'required|string|max:50',
        ]);

        // Update all bookings that belong to this client (matched by its original key: email or tel-<phone>)
        $this->clientQuery($email)->update([
            'client_name' => $validated['client_name'],
            'client_email' => $validated['client_email'] ?? null,
            'client_phone' => $validated['client_phone'],
        ]);

        $newKey = ! empty($validated['client_email']) ? $validated['client_email'] : 'tel-'.$validated['client_phone'];

        return redirect()
            ->route('admin.clients.show', $newKey)
            ->with('success', 'Client information updated successfully.');
    }
}
