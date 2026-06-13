<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['document', 'limit' => 4, 'size' => 'sm', 'urls' => null]));

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

foreach (array_filter((['document', 'limit' => 4, 'size' => 'sm', 'urls' => null]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>

<?php
    $images = collect();
    if ($urls) {
        $images = collect($urls)->map(fn ($u) => (object) ['url' => $u]);
    } elseif ($document->relationLoaded('attachments') && $document->attachments->isNotEmpty()) {
        $images = $document->attachments;
    }
    $thumbClass = $size === 'lg' ? 'h-20 w-20' : 'h-12 w-12';
?>

<?php if($images->isNotEmpty()): ?>
    <div <?php echo e($attributes->merge(['class' => 'flex flex-wrap gap-2'])); ?>>
        <?php $__currentLoopData = $images->take($limit); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $img): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                // Attachment models are served through the authorized route;
                // pre-resolved URLs (passed via :urls) are used as-is. Don't read
                // $img->url on a model — Eloquent treats it as a relationship.
                $url = $img instanceof \App\Models\DocumentAttachment
                    ? route('attachments.show', $img)
                    : ($img->url ?? null);
            ?>
            <?php if($url): ?>
                
                <a href="<?php echo e($url); ?>" target="_blank" rel="noopener" data-lightbox-src="<?php echo e($url); ?>"
                   class="block overflow-hidden rounded-lg ring-1 ring-gray-200 transition hover:ring-emerald-400"
                   title="View image">
                    <img src="<?php echo e($url); ?>" alt="Document attachment" class="<?php echo e($thumbClass); ?> object-cover bg-gray-100"
                         loading="lazy"
                         onerror="this.classList.add('opacity-40'); this.alt='Image unavailable';">
                </a>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($images->count() > $limit): ?>
            <span class="flex <?php echo e($thumbClass); ?> items-center justify-center rounded-lg bg-gray-100 text-xs font-semibold text-gray-600">+<?php echo e($images->count() - $limit); ?></span>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/components/document-images.blade.php ENDPATH**/ ?>