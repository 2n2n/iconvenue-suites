{{--
    Suite rate source (Regular / booking platform) + optional platform reference.
    Expects: $platforms (active BookingPlatform collection), $selectedPlatformId, $platformReference.
    Prices come from the selected suite <option data-platform-rates='{"id":price}'>; see getSuiteNightlyRate() in the page script.
--}}
<div class="mt-4" id="rateSourceField">
    <label for="booking_platform_id" class="block text-sm font-medium text-gray-700 mb-2">Rate *</label>
    <select name="booking_platform_id" id="booking_platform_id" dusk="rate-source"
        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-colors">
        <option value="" data-name="Regular rate">Regular rate</option>
        @foreach($platforms as $platform)
        <option value="{{ $platform->id }}" data-name="{{ $platform->name }}" {{ (string) $selectedPlatformId === (string) $platform->id ? 'selected' : '' }}>
            {{ $platform->name }}
        </option>
        @endforeach
    </select>
    <p class="text-xs text-amber-700 mt-1" id="rateFallbackNote" dusk="rate-fallback-note" style="display: none;"></p>
    @error('booking_platform_id')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror

    <div class="mt-4" id="platformReferenceField" style="{{ $selectedPlatformId ? '' : 'display: none;' }}">
        <label for="platform_reference" class="block text-sm font-medium text-gray-700 mb-2">Platform reference (optional)</label>
        <input type="text" name="platform_reference" id="platform_reference" maxlength="100" dusk="platform-reference"
            value="{{ $platformReference }}" placeholder="e.g. Agoda booking ID"
            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 transition-colors">
        @error('platform_reference')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
    </div>
</div>

<script>
    /**
     * Nightly rate for the selected suite option, honouring the selected rate source.
     * Mirrors App\Services\Venue\SuiteRateService::quote(): a platform without a rate falls back to the regular rate.
     */
    /**
     * Show each rate option's nightly price for the selected room,
     * e.g. "Agoda — ₱2,520.00 / night" or "Booking.com — ₱2,500.00 / night (regular)".
     */
    window.refreshRateOptionLabels = function (selectedOption) {
        const rateSelect = document.getElementById('booking_platform_id');
        if (!rateSelect) return;
        const hasRoom = selectedOption && selectedOption.getAttribute('data-type') === 'suite';
        const regular = parseFloat(selectedOption?.getAttribute('data-price')) || 0;
        let rates = {};
        try { rates = JSON.parse(selectedOption?.getAttribute('data-platform-rates') || '{}'); } catch (e) { rates = {}; }
        const peso = (v) => '₱' + v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        Array.from(rateSelect.options).forEach((opt) => {
            const name = opt.dataset.name || opt.text;
            if (!hasRoom) { opt.text = name; return; }
            if (!opt.value) { opt.text = `${name} — ${peso(regular)} / night`; return; }
            const own = rates[opt.value];
            opt.text = own === undefined || own === null
                ? `${name} — ${peso(regular)} / night (regular)`
                : `${name} — ${peso(parseFloat(own) || 0)} / night`;
        });
    };

    window.getSuiteNightlyRate = function (selectedOption) {
        window.refreshRateOptionLabels(selectedOption);
        const regular = parseFloat(selectedOption?.getAttribute('data-price')) || 0;
        const rateSelect = document.getElementById('booking_platform_id');
        const note = document.getElementById('rateFallbackNote');
        const platformId = rateSelect ? rateSelect.value : '';
        const refField = document.getElementById('platformReferenceField');
        if (refField) refField.style.display = platformId ? 'block' : 'none';

        if (!platformId) {
            if (note) note.style.display = 'none';
            return { rate: regular, label: 'Regular rate' };
        }

        let rates = {};
        try { rates = JSON.parse(selectedOption?.getAttribute('data-platform-rates') || '{}'); } catch (e) { rates = {}; }
        const selectedRate = rateSelect.options[rateSelect.selectedIndex];
        const platformName = (selectedRate.dataset.name || selectedRate.text).trim();

        if (rates[platformId] === undefined || rates[platformId] === null) {
            if (note) {
                note.textContent = `No ${platformName} rate set for this room — regular rate applied.`;
                note.style.display = 'block';
            }
            return { rate: regular, label: 'Regular rate' };
        }

        if (note) note.style.display = 'none';
        return { rate: parseFloat(rates[platformId]) || 0, label: platformName };
    };
</script>
