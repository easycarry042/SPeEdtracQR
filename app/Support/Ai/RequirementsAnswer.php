<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\RequestType;
use Illuminate\Support\Str;

/**
 * "What do I need for a Business Permit?" — answered from the public services
 * catalogue (each request type's requirement checklist).
 *
 * Shared by both of Speedy's modes: the general one, which needs the service
 * named in the question, and the one scoped to a request, which falls back to
 * that request's own type.
 */
final class RequirementsAnswer
{
    /**
     * Whether the question is asking what documents/requirements are needed.
     *
     * Not when it is about collecting a finished document: "what do I bring
     * for pickup?" used to get the filing checklist, when the answer is the
     * QR code.
     */
    public static function asks(string $question): bool
    {
        $q = Str::lower($question);

        if (Str::contains($q, ['pickup', 'pick up', 'pick-up', 'claim', 'collect', 'release'])) {
            return false;
        }

        return Str::contains($q, [
            'requirement', 'require', 'what do i need', 'need to bring', 'documents needed',
            'what to bring', 'bring', 'checklist', 'prepare', 'documents for',
        ]);
    }

    /**
     * The checklist the question is about: a service named in the question if
     * one matches, otherwise $currentType (the request being discussed).
     *
     * @return array{service:string, is_current:bool, items:array<int, array{label:string, mandatory:bool}>}|null
     */
    public static function resolve(string $question, ?string $currentType = null): ?array
    {
        $types = RequestType::with('requirements')->get(['id', 'name']);
        if ($types->isEmpty()) {
            return null;
        }

        $q = Str::lower($question);

        // A service explicitly named in the question wins; longest name first so
        // "Mayor's Permit" is preferred over a bare "Permit"-style partial.
        $match = $types
            ->sortByDesc(fn (RequestType $t): int => Str::length($t->name))
            ->first(fn (RequestType $t): bool => Str::contains($q, Str::lower($t->name)));

        $match ??= $currentType === null ? null : $types->first(
            fn (RequestType $t): bool => Str::lower($t->name) === Str::lower($currentType)
        );

        if (! $match) {
            return null;
        }

        return [
            'service' => $match->name,
            'is_current' => $currentType !== null && Str::lower($match->name) === Str::lower($currentType),
            'items' => $match->requirements
                ->map(fn ($r): array => ['label' => $r->label, 'mandatory' => (bool) $r->is_mandatory])
                ->all(),
        ];
    }

    /**
     * @param  array{service:string, is_current:bool, items:array<int, array{label:string, mandatory:bool}>}  $req
     */
    public static function text(array $req): string
    {
        if (empty($req['items'])) {
            return "I don't have a requirements checklist on file for {$req['service']}. Please contact the handling office to confirm what to prepare.";
        }

        $mandatory = array_values(array_filter($req['items'], fn (array $i): bool => $i['mandatory']));
        $optional = array_values(array_filter($req['items'], fn (array $i): bool => ! $i['mandatory']));

        $lead = $req['is_current']
            ? "For your {$req['service']}, you'll typically need:"
            : "For a {$req['service']}, you'll typically need:";

        $lines = array_map(fn (array $i): string => '• '.$i['label'], $mandatory ?: $req['items']);
        $text = $lead."\n".implode("\n", $lines);

        if ($mandatory && $optional) {
            $text .= "\nOptional / if applicable:\n".implode("\n", array_map(fn (array $i): string => '• '.$i['label'], $optional));
        }

        return $text."\nRequirements can vary by case — the handling office confirms the final list.";
    }
}
