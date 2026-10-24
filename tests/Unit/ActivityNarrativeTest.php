<?php

namespace Tests\Unit;

use App\Support\ActivityNarrative;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The activity timeline is only useful if the colour means something. These
 * are the real log lines the system writes, taken from the activity table.
 */
class ActivityNarrativeTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function lines(): array
    {
        return [
            // Trouble — the entries someone has to act on.
            'returned for revision' => ['Returned for revision at Municipal Engineering Office by Arnel Domingo: Please attach the list.', 'red'],
            'requirement rejected' => ['Reviewed requirement "Community Tax Certificate (Cedula)": Rejected', 'red'],
            'hold ran over' => ['Hold overdue by 8 day(s) — chased assignee and supervisor', 'red'],
            'released without a scan' => ['Decision confirmed WITHOUT a QR scan — reason: Sticker torn off the folder cover.', 'red'],

            // Waiting on someone.
            'needs revision' => ['Reviewed requirement "Community Tax Certificate (Cedula)": Needs revision', 'amber'],
            'citizen re-uploaded' => ['Citizen re-uploaded "Community Tax Certificate (Cedula)" for re-review', 'amber'],
            'staff nudged' => ['Nudged the responsible staff about an overdue document', 'red'],

            // Finished well.
            'completed' => ['Completed: Approved → Completed', 'green'],
            'requirement approved' => ['Reviewed requirement "Barangay Business Clearance": Approved', 'green'],

            // Routine.
            'assigned' => ['Assigned to Paolo Mendoza', 'blue'],
            'public message' => ['Posted public message on document', 'blue'],
            'citizen filed' => ['Citizen submitted online request', 'blue'],
        ];
    }

    #[DataProvider('lines')]
    public function test_each_log_line_takes_the_tone_its_content_deserves(string $line, string $tone): void
    {
        $this->assertSame($tone, ActivityNarrative::tone($line), $line);
    }

    public function test_the_em_dash_separates_the_headline_from_its_reason(): void
    {
        $row = ActivityNarrative::for('Decision confirmed WITHOUT a QR scan — reason: Sticker torn off the folder cover.');

        $this->assertSame('Decision confirmed WITHOUT a QR scan', $row['headline']);
        // The "reason:" label is dropped — the detail already sits under the
        // headline, so the word earns nothing.
        $this->assertSame('Sticker torn off the folder cover.', $row['detail']);
    }

    public function test_a_colon_inside_a_quoted_requirement_name_does_not_split_the_line(): void
    {
        $row = ActivityNarrative::for('Reviewed requirement "Community Tax Certificate (Cedula)": Rejected');

        $this->assertSame('Reviewed requirement "Community Tax Certificate (Cedula)"', $row['headline']);
        $this->assertSame('Rejected', $row['detail']);
    }

    public function test_a_line_with_no_separator_is_left_whole(): void
    {
        $row = ActivityNarrative::for('Assigned to Paolo Mendoza');

        $this->assertSame('Assigned to Paolo Mendoza', $row['headline']);
        $this->assertNull($row['detail']);
    }

    public function test_a_bare_model_event_is_given_a_name_a_person_would_use(): void
    {
        // Spatie logs these as the bare verb. "updated" sitting alone in a
        // timeline tells the reader nothing about what happened.
        $this->assertSame('Record updated', ActivityNarrative::for('updated')['headline']);
        $this->assertSame('Record created', ActivityNarrative::for('created')['headline']);
    }

    public function test_severity_beats_outcome_when_both_words_appear(): void
    {
        // "Reviewed … : Rejected" contains neither "approved" nor "completed",
        // but this one contains both a green word and a red one.
        $this->assertSame('red', ActivityNarrative::tone('Approved earlier, then returned for revision'));
    }
}
