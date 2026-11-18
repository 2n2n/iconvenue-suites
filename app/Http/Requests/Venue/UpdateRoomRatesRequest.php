<?php

declare(strict_types=1);

namespace App\Http\Requests\Venue;

use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Edited cells from the Room Rates grid. platform_id empty = the suite's regular rate.
 */
class UpdateRoomRatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'cells' => ['required', 'array', 'min:1', 'max:2000'],
            'cells.*.venue_id' => ['required', 'integer', 'exists:venues_and_suites,id'],
            'cells.*.platform_id' => ['nullable', 'integer', 'exists:booking_platforms,id'],
            'cells.*.price' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cells.required' => 'There are no changes to save.',
            'cells.*.price.numeric' => 'Each rate must be a number.',
            'cells.*.price.min' => 'Rates cannot be negative.',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $cells = (array) $this->input('cells', []);
                $suiteIds = Venue::where('type', 'suite')
                    ->whereIn('id', array_filter(array_map(fn ($c) => is_array($c) ? ($c['venue_id'] ?? null) : null, $cells)))
                    ->pluck('id')
                    ->all();

                foreach ($cells as $i => $cell) {
                    if (! is_array($cell)) {
                        continue;
                    }
                    if (isset($cell['venue_id']) && ! in_array((int) $cell['venue_id'], $suiteIds, true)) {
                        $validator->errors()->add("cells.{$i}.venue_id", 'Rates can only be set for suites.');
                    }
                    $isRegular = ! isset($cell['platform_id']) || $cell['platform_id'] === '' || $cell['platform_id'] === null;
                    $isEmpty = ! isset($cell['price']) || $cell['price'] === '' || $cell['price'] === null;
                    if ($isRegular && $isEmpty) {
                        $validator->errors()->add("cells.{$i}.price", 'The regular rate cannot be empty.');
                    }
                }
            },
        ];
    }

    /** @return array<int, array{venue_id: int|string, platform_id?: int|string|null, price?: mixed}> */
    public function cells(): array
    {
        return array_values((array) $this->validated('cells'));
    }
}
