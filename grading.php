<?php
// /app/grading.php
declare(strict_types=1);

/**
 * Validate a single grade entry.
 *
 * @param array $input  Keys: CA1, CA2, CA3, EXAM (any may be missing/''/null)
 * @param array $limits
 * @return array {
 *   status: 'complete'|'partial'|'exam_only'|'blocked'|'empty',
 *   total:  ?float,
 *   errors: string[],
 *   missing: string[],
 *   values: array<string, ?float>
 * }
 */
function grade_validate(array $input, array $limits): array
{
    // Keys come from $limits (which is populated from grading_components)
$keys = array_keys($limits);
    $values  = [];
    $errors  = [];
    $missing = [];
    $anySet  = false;

    foreach ($keys as $k) {
        $raw = $input[$k] ?? null;

        if ($raw === null || $raw === '') {
            $values[$k]  = null;
            $missing[]   = $k;
            continue;
        }
        if (!is_numeric($raw)) {
            $errors[]    = "{$k} must be a number.";
            $values[$k]  = null;
            continue;
        }

        $anySet     = true;
        $val        = (float) $raw;
        $max        = (float) ($limits[$k] ?? 100);

        if ($val < 0) {
            $errors[] = "{$k} cannot be negative.";
        } elseif ($val > $max) {
            $errors[] = "{$k} ({$val}) exceeds maximum of {$max}.";
        }
        $values[$k] = $val;
    }

    if ($errors) {
        return ['status' => 'blocked', 'total' => null,
                'errors' => $errors, 'missing' => $missing, 'values' => $values];
    }

    if (!$anySet) {
        return ['status' => 'empty', 'total' => null,
                'errors' => [], 'missing' => $missing, 'values' => $values];
    }

        $hasExam = ($values['EXAM'] ?? null) !== null;

    $caCount = 0;
    foreach (array_keys($limits) as $k) {
        if ($k === 'EXAM') continue;
        if (($values[$k] ?? null) !== null) $caCount++;
    }
    $caComponentCount = 0;
    foreach (array_keys($limits) as $k) {
        if ($k !== 'EXAM') $caComponentCount++;
    }

    // Exam required if any CA was entered
    if (!$hasExam && $caCount > 0) {
        return ['status' => 'blocked', 'total' => null,
                'errors' => ['EXAM is required because CA scores were entered.'],
                'missing' => $missing, 'values' => $values];
    }

    // Only exam, no CAs — rare (mid-term transfer), allowed but flagged
    if ($hasExam && $caCount === 0) {
        return ['status' => 'exam_only', 'total' => $values['EXAM'],
                'errors' => [], 'missing' => $missing, 'values' => $values];
    }

    // Complete: all CA components + exam present
    if ($hasExam && $caCount === $caComponentCount && $caComponentCount > 0) {
        return ['status' => 'complete', 'total' => array_sum($values),
                'errors' => [], 'missing' => [], 'values' => $values];
    }

    // Partial: exam + some CAs
    $total = 0.0;
    foreach ($values as $v) {
        if ($v !== null) $total += $v;
    }
    return ['status' => 'partial', 'total' => $total,
            'errors' => [], 'missing' => $missing, 'values' => $values];
}