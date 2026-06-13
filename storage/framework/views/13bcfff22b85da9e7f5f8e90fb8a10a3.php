<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames(([
    'index' => null,
    'date' => null,
    'tracking' => '',
    'fileName' => '',
    'category' => '',
    'status' => 'pending',
    'href' => null,
    'stickerHref' => null,
]));

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

foreach (array_filter(([
    'index' => null,
    'date' => null,
    'tracking' => '',
    'fileName' => '',
    'category' => '',
    'status' => 'pending',
    'href' => null,
    'stickerHref' => null,
]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<tr <?php echo e($attributes->class('border-b border-gray-200/90 transition-colors duration-150 hover:bg-emerald-50/40 last:border-b-0')); ?>>
    <?php if($index !== null): ?>
        <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-500"><?php echo e($index); ?></td>
    <?php endif; ?>
    <td class="max-w-[200px] truncate px-4 py-3 text-sm font-medium text-gray-900"><?php echo e($fileName); ?></td>
    <td class="whitespace-nowrap px-4 py-3 text-sm font-semibold text-gray-900 font-mono">
        <?php if($href): ?>
            <a href="<?php echo e($href); ?>" class="text-emerald-900 underline-offset-2 hover:text-emerald-700 hover:underline"><?php echo e($tracking); ?></a>
        <?php else: ?>
            <?php echo e($tracking); ?>

        <?php endif; ?>
    </td>
    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700"><?php echo e($date); ?></td>
    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700"><?php echo e($category); ?></td>
    <td class="px-4 py-3"><?php if (isset($component)) { $__componentOriginal8c81617a70e11bcf247c4db924ab1b62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-badge','data' => ['status' => $status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $attributes = $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $component = $__componentOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?></td>
    <?php if($stickerHref): ?>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?php echo e($stickerHref); ?>" target="_blank" rel="noopener" class="text-sm font-semibold text-emerald-800 hover:text-emerald-950 hover:underline">Print QR</a>
        </td>
    <?php endif; ?>
</tr>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/components/document-row.blade.php ENDPATH**/ ?>