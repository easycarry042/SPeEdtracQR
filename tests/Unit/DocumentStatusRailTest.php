<?php

namespace Tests\Unit;

use App\Enums\DocumentStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The staff progress bar shows the prototype's four stages, not the five
 * statuses: Pending, In review, Processing, Completed.
 */
class DocumentStatusRailTest extends TestCase
{
    public function test_the_bar_has_the_four_prototype_stages_in_order(): void
    {
        $this->assertSame(
            ['Pending', 'In review', 'Processing', 'Completed'],
            array_column(DocumentStatus::railStages(), 'label'),
        );
    }

    public function test_every_status_on_the_flow_sits_on_exactly_one_stage(): void
    {
        $placed = array_merge(...array_column(DocumentStatus::railStages(), 'statuses'));

        foreach (DocumentStatus::flow() as $status) {
            $this->assertSame(1, count(array_keys($placed, $status->value, true)), $status->value);
        }
    }

    /**
     * @return array<string, array{DocumentStatus, ?DocumentStatus, int}>
     */
    public static function stages(): array
    {
        return [
            'pending' => [DocumentStatus::Pending, null, 1],
            'in progress is review' => [DocumentStatus::InProgress, null, 2],
            'in review' => [DocumentStatus::InReview, null, 2],
            'approved is processing' => [DocumentStatus::Approved, null, 3],
            'completed' => [DocumentStatus::Completed, null, 4],
            'returned goes back to review' => [DocumentStatus::Returned, null, 2],
            'on hold keeps its place' => [DocumentStatus::OnHold, DocumentStatus::Approved, 3],
            'on hold with no record' => [DocumentStatus::OnHold, null, 2],
            'denied where it stopped' => [DocumentStatus::Denied, DocumentStatus::Pending, 1],
        ];
    }

    #[DataProvider('stages')]
    public function test_each_status_lands_on_its_stage(DocumentStatus $status, ?DocumentStatus $before, int $stage): void
    {
        $this->assertSame($stage, $status->railStage($before));
    }
}
