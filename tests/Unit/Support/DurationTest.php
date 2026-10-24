<?php

namespace Tests\Unit\Support;

use App\Support\Duration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How the command center prints elapsed time. It used to print raw units:
 * "0d" for a request finished in under an hour, "+482.4h" for one twenty days
 * late.
 */
class DurationTest extends TestCase
{
    /**
     * @return array<string, array{?float, string}>
     */
    public static function spans(): array
    {
        return [
            'under an hour' => [0.88, '53m'],
            'nothing at all' => [0.0, '0m'],
            'whole hours' => [5.0, '5h'],
            'hours and minutes' => [5.3, '5h 18m'],
            'a day and more' => [26.0, '1d 2h'],
            'whole days' => [48.0, '2d'],
            'twenty days late' => [482.4, '20d 2h'],
            'negative reads as its size' => [-2.5, '2h 30m'],
            'unknown' => [null, '—'],
        ];
    }

    #[DataProvider('spans')]
    public function test_hours_read_the_way_a_person_says_them(?float $hours, string $expected): void
    {
        $this->assertSame($expected, Duration::hours($hours));
    }

    public function test_days_are_not_rounded_down_to_nothing(): void
    {
        // A 53-minute average stored in days used to print as "0d".
        $this->assertSame('53m', Duration::days(53 / 1440));
        $this->assertSame('—', Duration::days(null));
    }
}
