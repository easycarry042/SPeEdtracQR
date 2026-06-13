<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['document', 'chain' => null, 'compact' => false]));

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

foreach (array_filter((['document', 'chain' => null, 'compact' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $routingChain = $chain ?? $document->getRoutingChain();
    $currentId = (int) $document->current_department_id;
?>

<?php if($routingChain->isNotEmpty()): ?>
    <div <?php echo e($attributes->merge(['class' => 'overflow-x-auto pb-1'])); ?>>
        <div class="flex items-center gap-1 min-w-max">
            <?php $__currentLoopData = $routingChain; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $i => $step): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $isCurrent = (int) $step->id === $currentId && $document->status !== 'completed';
                    $isDone = false;
                    if ($document->status === 'completed') {
                        $isDone = true;
                    } else {
                        foreach ($routingChain as $j => $s) {
                            if ((int) $s->id === $currentId && $j > $i) {
                                $isDone = true;
                                break;
                            }
                        }
                    }
                    $nodeSize = $compact ? 'h-6 w-6 text-xs' : 'h-7 w-7 text-xs';
                    $labelClass = $compact ? 'max-w-[72px] text-[10px]' : 'max-w-[88px] text-xs';
                ?>

                <div class="flex flex-col items-center gap-1">
                    <div class="flex <?php echo e($nodeSize); ?> items-center justify-center rounded-full font-bold
                        <?php echo e($isCurrent ? 'bg-emerald-600 text-white ring-2 ring-emerald-300' : ($isDone ? 'bg-emerald-200 text-emerald-700' : 'bg-gray-100 text-gray-400')); ?>">
                        <?php if($isDone): ?>
                            <svg class="<?php echo e($compact ? 'h-3.5 w-3.5' : 'h-4 w-4'); ?>" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                            </svg>
                        <?php else: ?>
                            <?php echo e($i + 1); ?>

                        <?php endif; ?>
                    </div>
                    <span class="<?php echo e($labelClass); ?> truncate text-center font-medium leading-tight
                        <?php echo e($isCurrent ? 'text-emerald-700 font-bold' : ($isDone ? 'text-emerald-500' : 'text-gray-400')); ?>">
                        <?php echo e($step->name); ?>

                    </span>
                </div>

                <?php if(!$loop->last): ?>
                    <div class="mb-4 h-0.5 <?php echo e($compact ? 'w-6' : 'w-8'); ?> shrink-0 <?php echo e($isDone ? 'bg-emerald-300' : 'bg-gray-200'); ?>"></div>
                <?php endif; ?>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/components/routing-stepper.blade.php ENDPATH**/ ?>