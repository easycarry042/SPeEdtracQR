<?php

namespace App\Support;

use App\Models\Document;
use Illuminate\Support\Collection;

/**
 * SLA pressure on a set of documents.
 *
 * Each stage carries its own budget (DocumentStatus::slaHours()) and spends it
 * from `status_changed_at`: the manual status model moves that timestamp on
 * every stage change, so a request that has been sitting for a week in
 * Processing is not judged by the day it was filed.
 *
 * This used to be inlined in DashboardController. It moved here when the
 * at-risk list moved to the staff dashboard — two callers needed the same
 * arithmetic, and "at risk" has to mean the same thing on both.
 */
class SlaWatch
{
    /** A document is at risk once it has spent this share of its stage budget. */
    public const AT_RISK_RATIO = 0.75;

    /**
     * Annotate each document with its SLA standing and keep only the ones at
     * risk, worst first — the order a queue should be worked in.
     *
     * Ordered by signed hours past the budget: the furthest overdue first, then
     * the closest to breaching. Sorting on `sla_remaining_hours` instead put
     * every overdue request at zero and so left the worst offenders in
     * whatever order they came back from the database. It is also the key
     * DashboardController's routing slip picks its one document with, so the
     * slip names the top of this list rather than some other request.
     *
     * @param  Collection<int, Document>  $documents
     * @return Collection<int, Document>
     */
    public static function atRisk(Collection $documents): Collection
    {
        return $documents
            ->map(fn (Document $document): ?Document => self::annotate($document))
            ->filter(fn (?Document $document): bool => $document !== null
                && $document->sla_ratio >= self::AT_RISK_RATIO)
            ->sortByDesc('sla_hours_over')
            ->values();
    }

    /**
     * Stamp one document's SLA standing onto it, or return null when its stage
     * has no budget to spend — terminal stages, and the side states whose clock
     * is deliberately paused.
     */
    public static function annotate(Document $document): ?Document
    {
        $budget = $document->statusEnum()->slaHours();
        $anchor = $document->status_changed_at ?? $document->updated_at ?? $document->created_at;

        if (! $budget || ! $anchor) {
            return null;
        }

        // diffInHours() is fractional. The two figures on screen are whole
        // hours, but the decisions keep the precise value, so a request ten
        // minutes past its budget still reads as overdue rather than as having
        // exactly none of it left.
        $elapsed = $anchor->diffInHours(now());

        $document->sla_budget_hours = $budget;
        $document->sla_elapsed_hours = (int) round($elapsed);
        $document->sla_remaining_hours = (int) max(0, ceil($budget - $elapsed));
        $document->sla_overdue = $elapsed > $budget;
        $document->sla_ratio = $elapsed / $budget;
        // Signed, and precise: positive is hours overdue, negative is hours
        // left. One key that ranks both halves of the list (see atRisk()).
        $document->sla_hours_over = $elapsed - $budget;

        return $document;
    }

    /**
     * Share of the stage budget spent, capped at 100 — the width of the bar.
     * Past the cap the overrun is reported in hours beside it, because a bar
     * that can only ever be full says nothing about how late a thing is.
     */
    public static function percentUsed(Document $document): int
    {
        return (int) min(round(($document->sla_ratio ?? 0) * 100), 100);
    }
}
