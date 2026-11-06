---
paths:
  - "app/**/*.php"
---

# Modular service layer

Business logic is grouped by **module**: Booking, Venue (venues, suites, packages), Addon, Payment, Client, Staff, Report, Settings, Carousel, Media.

## Layout
| What | Where |
|---|---|
| Service (module API) | `app/Services/{Module}/{Name}Service.php` |
| Single-purpose use case | `app/Actions/{Module}/{Verb}{Entity}.php` (one `handle()` method) |
| DTO | `app/Data/{Module}/{Name}Data.php` (readonly class) |
| Domain exception | `app/Exceptions/{Module}/{Reason}Exception.php` |
| Side-effect trigger | `app/Events/{Module}/`, `app/Listeners/{Module}/` |

## Calling services
- Inject through the constructor or controller method signature; the container builds the graph.
- A module's **service is its public API**. Another module calls `BookingService`, `PaymentService`, … — it reads its own models and asks other modules for theirs.
- Services accept DTOs or typed scalars and return models, collections or DTOs. HTTP concerns (requests, redirects, session flashes) stay in the controller.
- `DB::transaction` wraps the use case inside the service.
- Mail and notifications fire from Events/Listeners or queued Mailables (`app/Mail/` already holds the Mailables).
- Bind an interface in a ServiceProvider when there is an external integration or a second implementation; otherwise inject the concrete class.
- Reuse before writing: images → `App\Helpers\ImageHelper`, `App\Traits\HandlesImageUploads`.

```php
final class BookingService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AddonStockService $addonStock,
    ) {}

    public function create(BookingData $data): Booking
    {
        return DB::transaction(function () use ($data) {
            $booking = Booking::create($data->toAttributes() + ['total_amount' => $this->pricing->quote($data)]);
            $this->addonStock->reserve($booking, $data->addons);
            BookingCreated::dispatch($booking);

            return $booking;
        });
    }
}
```
