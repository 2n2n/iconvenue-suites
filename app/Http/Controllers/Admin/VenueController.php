<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Venue;
use App\Models\VenueAddon;
use App\Traits\HandlesImageUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VenueController extends Controller
{
    use HandlesImageUploads;
    // Venues Management
    public function indexVenues()
    {
        $venues = Venue::where('type', 'venue')->latest()->paginate(10);
        return view('admin.venues.index', compact('venues'));
    }

    public function createVenue()
    {
        $type = 'venue';
        return view('admin.venues.create', compact('type'));
    }

    public function storeVenue(Request $request)
    {
        return $this->storeVenueOrSuite($request, 'venue');
    }

    // Suites Management
    public function indexSuites()
    {
        $suites = Venue::where('type', 'suite')->latest()->paginate(10);
        return view('admin.suites.index', compact('suites'));
    }

    public function createSuite()
    {
        $type = 'suite';
        return view('admin.suites.create', compact('type'));
    }

    public function storeSuite(Request $request)
    {
        return $this->storeVenueOrSuite($request, 'suite');
    }

    public function editSuite(Venue $venue)
    {
        return view('admin.suites.edit', compact('venue'));
    }

    public function updateSuite(Request $request, Venue $venue)
    {
        return $this->updateVenueOrSuite($request, $venue, 'suite');
    }

    public function destroySuite(Venue $venue)
    {
        if ($venue->images) {
            foreach ($venue->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $venue->delete();

        return redirect()->route('admin.suites.index')
            ->with('success', 'Suite deleted successfully!');
    }

    // Legacy methods (kept for backward compatibility)
    public function index()
    {
        $venues = Venue::latest()->paginate(10);
        return view('admin.venues.index', compact('venues'));
    }

    public function create()
    {
        return view('admin.venues.create');
    }

    private function storeVenueOrSuite(Request $request, $type)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'room_number' => 'nullable|string|max:50',
            'description' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'price_per_day' => 'required|numeric|min:0',
            'amenities' => 'nullable|array',
            'images.*' => 'nullable|image|max:10240'
        ];

        if ($type === 'venue') {
            $rules['price_morning'] = 'required|numeric|min:0';
            $rules['price_afternoon'] = 'required|numeric|min:0';
            $rules['price_evening'] = 'required|numeric|min:0';
        } else {
            $rules['price_morning'] = 'nullable|numeric|min:0';
            $rules['price_afternoon'] = 'nullable|numeric|min:0';
            $rules['price_evening'] = 'nullable|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $this->storeHDImage($image, $type === 'suite' ? 'suites' : 'venues');
                $images[] = $path;
            }
        }

        $validated['images'] = $images;
        $validated['type'] = $type;
        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Split amenities textarea (one per line) into a clean array
        if (!empty($validated['amenities'])) {
            $raw = is_array($validated['amenities']) ? implode("\n", $validated['amenities']) : $validated['amenities'];
            $validated['amenities'] = array_values(array_filter(array_map('trim', explode("\n", $raw))));
        }

        Venue::create($validated);

        $routeName = $type === 'suite' ? 'admin.suites.index' : 'admin.venues.index';
        $message = $type === 'suite' ? 'Suite created successfully!' : 'Venue created successfully!';

        return redirect()->route($routeName)->with('success', $message);
    }

    public function store(Request $request)
    {
        return $this->storeVenueOrSuite($request, $request->type);
    }

    public function edit(Venue $venue)
    {
        return view('admin.venues.edit', compact('venue'));
    }

    public function update(Request $request, Venue $venue)
    {
        return $this->updateVenueOrSuite($request, $venue, $venue->type);
    }

    private function updateVenueOrSuite(Request $request, Venue $venue, $type)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'room_number' => 'nullable|string|max:50',
            'description' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'price_per_day' => 'required|numeric|min:0',
            'amenities' => 'nullable|array',
            'images.*' => 'nullable|image|max:10240'
        ];

        if ($request->input('type', $type) === 'venue') {
            $rules['price_morning'] = 'required|numeric|min:0';
            $rules['price_afternoon'] = 'required|numeric|min:0';
            $rules['price_evening'] = 'required|numeric|min:0';
        } else {
            $rules['price_morning'] = 'nullable|numeric|min:0';
            $rules['price_afternoon'] = 'nullable|numeric|min:0';
            $rules['price_evening'] = 'nullable|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $images = $venue->images ?? [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $this->storeHDImage($image, $type === 'suite' ? 'suites' : 'venues');
                $images[] = $path;
            }
        }

        $validated['images'] = $images;
        $validated['is_active'] = $request->has('is_active') ? true : false;

        // Split amenities textarea (one per line) into a clean array
        if (!empty($validated['amenities'])) {
            $raw = is_array($validated['amenities']) ? implode("\n", $validated['amenities']) : $validated['amenities'];
            $validated['amenities'] = array_values(array_filter(array_map('trim', explode("\n", $raw))));
        }

        $venue->update($validated);

        $routeName = $type === 'suite' ? 'admin.suites.index' : 'admin.venues.index';
        $message = $type === 'suite' ? 'Suite updated successfully!' : 'Venue updated successfully!';

        return redirect()->route($routeName)->with('success', $message);
    }

    public function destroy(Venue $venue)
    {
        if ($venue->images) {
            foreach ($venue->images as $image) {
                Storage::disk('public')->delete($image);
            }
        }

        $venue->delete();

        return redirect()->route('admin.venues.index')
            ->with('success', 'Venue deleted successfully!');
    }

    // Package Management Methods
    public function packages(Venue $venue)
    {
        $packages = $venue->packages()->orderBy('name')->get();
        $addons = VenueAddon::active()->orderBy('category')->orderBy('sort_order')->get();
        return view('admin.venues.packages', compact('venue', 'packages', 'addons'));
    }

    public function storePackage(Request $request, Venue $venue)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'has_time_based_pricing' => 'nullable|boolean',
            'price_morning' => 'nullable|numeric|min:0',
            'price_afternoon' => 'nullable|numeric|min:0',
            'price_evening' => 'nullable|numeric|min:0',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:venue_addons,id'
        ]);

        $package = $venue->packages()->create([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'has_time_based_pricing' => $request->has_time_based_pricing ?? false,
            'price_morning' => $request->price_morning,
            'price_afternoon' => $request->price_afternoon,
            'price_evening' => $request->price_evening,
            'is_active' => true
        ]);

        $package->addons()->sync($request->addon_ids ?? []);

        return redirect()->route('admin.venues.packages', $venue)
            ->with('success', 'Package added successfully!');
    }

    public function updatePackage(Request $request, Venue $venue, \App\Models\VenuePackage $package)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'has_time_based_pricing' => 'nullable|boolean',
            'price_morning' => 'nullable|numeric|min:0',
            'price_afternoon' => 'nullable|numeric|min:0',
            'price_evening' => 'nullable|numeric|min:0',
            'addon_ids' => 'nullable|array',
            'addon_ids.*' => 'exists:venue_addons,id'
        ]);

        $package->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'has_time_based_pricing' => $request->has_time_based_pricing ?? false,
            'price_morning' => $request->price_morning,
            'price_afternoon' => $request->price_afternoon,
            'price_evening' => $request->price_evening
        ]);

        $package->addons()->sync($request->addon_ids ?? []);

        return redirect()->route('admin.venues.packages', $venue)
            ->with('success', 'Package updated successfully!');
    }

    public function destroyPackage(Venue $venue, \App\Models\VenuePackage $package)
    {
        $package->delete();

        return redirect()->route('admin.venues.packages', $venue)
            ->with('success', 'Package deleted successfully!');
    }

    public function togglePackage(Venue $venue, \App\Models\VenuePackage $package)
    {
        $package->update(['is_active' => !$package->is_active]);

        return redirect()->route('admin.venues.packages', $venue)
            ->with('success', 'Package status updated!');
    }

    public function getPackageData(Venue $venue, \App\Models\VenuePackage $package)
    {
        return response()->json([
            'id' => $package->id,
            'name' => $package->name,
            'description' => $package->description,
            'price' => $package->price,
            'has_time_based_pricing' => $package->has_time_based_pricing,
            'price_morning' => $package->price_morning,
            'price_afternoon' => $package->price_afternoon,
            'price_evening' => $package->price_evening,
            'addon_ids' => $package->addons->pluck('id'),
            'inclusions' => $package->inclusions ?? [],
            'is_active' => $package->is_active
        ]);
    }

    public function removeImage(Request $request, Venue $venue)
    {
        $request->validate([
            'image' => 'required|string'
        ]);

        $imagePath = $request->image;
        $images = $venue->images ?? [];

        // Find and remove the image from the array
        $key = array_search($imagePath, $images);
        if ($key !== false) {
            unset($images[$key]);
            $images = array_values($images); // Re-index array

            // Delete the file from storage
            Storage::disk('public')->delete($imagePath);

            // Update the venue
            $venue->update(['images' => $images]);

            return response()->json(['success' => true, 'message' => 'Image deleted successfully']);
        }

        return response()->json(['success' => false, 'message' => 'Image not found'], 404);
    }

    // Browse methods for staff to manage availability
    public function browseVenues()
    {
        $venues = Venue::where('type', 'venue')
            ->with('activePackages')
            ->latest()
            ->get();
        return view('admin.venues.browse', compact('venues'));
    }

    public function browseSuites()
    {
        $suites = Venue::where('type', 'suite')
            ->latest()
            ->get();
        return view('admin.suites.browse', compact('suites'));
    }

    // Toggle venue availability
    public function toggleVenueAvailability(Venue $venue)
    {
        $venue->update(['is_active' => !$venue->is_active]);
        
        $status = $venue->is_active ? 'available' : 'not available';
        return back()->with('success', "{$venue->name} is now {$status}!");
    }

    // Toggle package availability
    public function togglePackageAvailability($venueId, $packageId)
    {
        $package = \App\Models\VenuePackage::where('venue_id', $venueId)
            ->where('id', $packageId)
            ->firstOrFail();
        
        $package->update(['is_active' => !$package->is_active]);
        
        $status = $package->is_active ? 'available' : 'not available';
        return back()->with('success', "Package '{$package->name}' is now {$status}!");
    }

    /**
     * Store HD image with high quality settings
     */
    private function storeHDImage($imageFile, $directory)
    {
        $filename = time() . '_' . uniqid() . '.' . $imageFile->getClientOriginalExtension();
        
        // Store to storage/app/public
        $storedPath = $imageFile->storeAs($directory, $filename, 'public');
        
        // Copy to public/storage so it's web-accessible (handles non-symlink setups)
        $this->copyToPublicStorage($storedPath);

        return $storedPath;
    }

    /**
     * Copy a stored file to public/storage for web access (Windows XAMPP symlink workaround)
     */
    private function copyToPublicStorage(string $storedPath): void
    {
        $sourcePath = storage_path('app/public/' . $storedPath);
        $publicPath = public_path('storage/' . $storedPath);
        $publicDir  = dirname($publicPath);

        if (!is_dir($publicDir)) {
            mkdir($publicDir, 0755, true);
        }

        if (file_exists($sourcePath) && !file_exists($publicPath)) {
            copy($sourcePath, $publicPath);
        }
    }
}

