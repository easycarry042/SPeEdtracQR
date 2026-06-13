<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['status' => 'pending']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['status' => 'pending']), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $normalized = strtolower(str_replace([' ', '-'], '_', (string) $status));
    $map = [
        'in_progress' => ['class' => 'bg-amber-100 text-amber-900 ring-amber-200/80', 'label' => 'In Progress'],
        'in_transit' => ['class' => 'bg-amber-100 text-amber-900 ring-amber-200/80', 'label' => 'In Progress'],
        'pending' => ['class' => 'bg-sky-100 text-sky-900 ring-sky-200/80', 'label' => 'Pending'],
        'rejected' => ['class' => 'bg-rose-100 text-rose-900 ring-rose-200/80', 'label' => 'Rejected'],
        'returned' => ['class' => 'bg-rose-100 text-rose-900 ring-rose-200/80', 'label' => 'Rejected'],
        'completed' => ['class' => 'bg-emerald-100 text-emerald-900 ring-emerald-200/80', 'label' => 'Completed'],
    ];
    $style = $map[$normalized] ?? $map['pending'];
?>

<span <?php echo e($attributes->merge(['class' => 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset '.$style['class']])); ?>>
    <?php echo e($style['label']); ?>

</span>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/components/status-badge.blade.php ENDPATH**/ ?>