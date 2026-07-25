<?php

namespace Tests\Unit;

use App\Enums\DocumentStatus;
use PHPUnit\Framework\TestCase;

class DocumentStatusTest extends TestCase
{
    public function test_every_status_has_a_nonempty_citizen_description(): void
    {
        foreach (DocumentStatus::cases() as $status) {
            $description = $status->description();

            $this->assertNotEmpty($description, "{$status->value} is missing a description");
            $this->assertStringEndsWith('.', $description, "{$status->value} description should read as a full sentence");
        }
    }

    public function test_description_differs_from_label(): void
    {
        foreach (DocumentStatus::cases() as $status) {
            $this->assertNotSame($status->label(), $status->description());
        }
    }
}
