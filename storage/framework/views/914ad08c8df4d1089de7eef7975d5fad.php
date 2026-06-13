<?php
    $statusClass = match($document->status) {
        'completed' => 'bg-green-200 text-green-800',
        'pending' => 'bg-blue-200 text-blue-800',
        'returned' => 'bg-rose-200 text-rose-800',
        default => 'bg-yellow-200 text-yellow-800',
    };
?>
<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <?php if(auth()->guard()->guest()): ?>
        
         <?php $__env->slot('header', null, []); ?> 
            <h1 class="text-3xl font-bold tracking-tight text-emerald-950 sm:text-4xl">Track Document</h1>
         <?php $__env->endSlot(); ?>
    <?php endif; ?>

    <div class="mx-auto grid max-w-7xl grid-cols-1 gap-8 px-4 sm:px-6 lg:grid-cols-2 lg:px-8">
        <?php if (! ($isPublicView)): ?>
            
            <div class="flex flex-col rounded-xl border border-[#e0e0e0] bg-white p-3 lg:h-[calc(100vh-9rem)]">
                <div class="max-h-[520px] min-h-0 flex-1 space-y-2 overflow-y-auto pr-1 lg:max-h-none">
                    <?php $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <a href="<?php echo e(route('track.show', $item->tracking_number)); ?>" class="flex items-center justify-between rounded-lg border p-3 <?php echo e($item->tracking_number === $document->tracking_number ? 'border-[#1a5c1a] bg-[#e8f5e9]' : 'border-[#e0e0e0] bg-white hover:bg-[#f4faf4]'); ?>">
                            <div class="flex items-center gap-3">
                                <span class="flex h-11 w-11 items-center justify-center rounded-full bg-[#c8efcc] text-[#1a5c1a]">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                                </span>
                                <div>
                                    <p class="text-[14px] font-semibold text-[#1a1a1a]"><?php echo e($item->document_type); ?></p>
                                    <p class="text-[13px] text-[#666666]"><?php echo e($item->status); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-[13px] text-[#666666]"><?php echo e($item->created_at->format('m/d/y')); ?></p>
                                <span class="text-xl text-[#666666]">›</span>
                            </div>
                        </a>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        <?php endif; ?>

        
        <div class="rounded-xl border border-[#e0e0e0] bg-white p-6 lg:h-[calc(100vh-9rem)] lg:overflow-y-auto <?php echo e($isPublicView ? 'lg:col-span-2' : ''); ?>">
            <div class="flex items-start justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-[#c8efcc] text-[#1a5c1a]">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 3h6l4 4v14H7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13 3v5h5"/></svg>
                    </span>
                    <div>
                        <p class="text-lg font-bold text-[#1a1a1a]"><?php echo e($document->document_type); ?></p>
                        <p class="text-[13px] text-[#666666]"><?php echo e($document->citizen_name ?? 'N/A'); ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-[#666666]">Tracking ID:</p>
                    <p class="text-xl font-extrabold text-[#1a5c1a] font-mono"><?php echo e($document->tracking_number); ?></p>
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <?php if (isset($component)) { $__componentOriginal8c81617a70e11bcf247c4db924ab1b62 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.status-badge','data' => ['status' => $document->status]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('status-badge'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($document->status)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $attributes = $__attributesOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__attributesOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62)): ?>
<?php $component = $__componentOriginal8c81617a70e11bcf247c4db924ab1b62; ?>
<?php unset($__componentOriginal8c81617a70e11bcf247c4db924ab1b62); ?>
<?php endif; ?>
                <?php if (! ($isPublicView)): ?>
                    <a href="<?php echo e(route('documents.edit', $document)); ?>"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
                        Edit details
                    </a>
                    <?php if($document->scans->isNotEmpty()): ?>
                        <form method="POST" action="<?php echo e(route('documents.undo-scan', $document)); ?>"
                              onsubmit="return confirm('Undo the most recent scan for this document? It will revert to its previous location.')">
                            <?php echo csrf_field(); ?>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                                Undo last scan
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <?php if (! ($isPublicView)): ?>
                <?php if(session('status')): ?>
                    <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800"><?php echo e(session('status')); ?></div>
                <?php endif; ?>
            <?php endif; ?>

            <?php if($document->attachments->isNotEmpty()): ?>
                <div class="mt-5">
                    <p class="text-[14px] font-bold text-[#1a1a1a]">Attached Images</p>
                    <?php if (isset($component)) { $__componentOriginal788a4d03d87e6a2b510e03f3f117461c = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal788a4d03d87e6a2b510e03f3f117461c = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document-images','data' => ['document' => $document,'limit' => 12,'size' => 'lg','class' => 'mt-2']] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document-images'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($document),'limit' => 12,'size' => 'lg','class' => 'mt-2']); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal788a4d03d87e6a2b510e03f3f117461c)): ?>
<?php $attributes = $__attributesOriginal788a4d03d87e6a2b510e03f3f117461c; ?>
<?php unset($__attributesOriginal788a4d03d87e6a2b510e03f3f117461c); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal788a4d03d87e6a2b510e03f3f117461c)): ?>
<?php $component = $__componentOriginal788a4d03d87e6a2b510e03f3f117461c; ?>
<?php unset($__componentOriginal788a4d03d87e6a2b510e03f3f117461c); ?>
<?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (! ($isPublicView)): ?>
                <div class="mt-6 flex flex-wrap gap-2">
                    <a href="<?php echo e(route('documents.sticker', $document)); ?>" target="_blank" class="inline-flex items-center gap-2 rounded-xl bg-emerald-800 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18h12M6 14h12M6 10h12M6 6h12"/></svg>
                        Print QR sticker
                    </a>
                    <a href="<?php echo e(route('scan.index')); ?>" class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-900 transition hover:bg-emerald-100">
                        Open scanner
                    </a>
                </div>
                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/80 p-4 text-left text-sm text-amber-950">
                    <p class="font-semibold">Recording a handoff between departments</p>
                    <p class="mt-1 text-amber-900/90">Use <strong>Scan</strong> (sidebar): pick the <strong>department</strong> that has the paper, then tap <strong>IN</strong> when it arrives there, or <strong>OUT</strong> when it is sent to the next office. Routing uses your <strong>Routing rules</strong> for that document type.</p>
                </div>
            <?php endif; ?>

            <div class="mt-8">
                <div class="mb-3 text-[14px] font-bold text-[#1a1a1a]">Department Progress</div>
                <?php if($routingChain->isNotEmpty()): ?>
                    <?php if (isset($component)) { $__componentOriginal5a5f54077c5570fe4a38b68c9db476a4 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal5a5f54077c5570fe4a38b68c9db476a4 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.routing-stepper','data' => ['document' => $document,'chain' => $routingChain]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('routing-stepper'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['document' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($document),'chain' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($routingChain)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal5a5f54077c5570fe4a38b68c9db476a4)): ?>
<?php $attributes = $__attributesOriginal5a5f54077c5570fe4a38b68c9db476a4; ?>
<?php unset($__attributesOriginal5a5f54077c5570fe4a38b68c9db476a4); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal5a5f54077c5570fe4a38b68c9db476a4)): ?>
<?php $component = $__componentOriginal5a5f54077c5570fe4a38b68c9db476a4; ?>
<?php unset($__componentOriginal5a5f54077c5570fe4a38b68c9db476a4); ?>
<?php endif; ?>
                <?php else: ?>
                    <span class="text-gray-500 text-sm">No routing path configured.</span>
                <?php endif; ?>
            </div>

            <?php if($canAct && $document->status !== 'completed'): ?>
                <div class="mt-6 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    <?php if($isLastStop): ?>
                        <button type="button"
                                class="js-track-complete inline-flex items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-600"
                                data-tracking="<?php echo e($document->tracking_number); ?>">
                            Mark as Done
                        </button>
                    <?php elseif($nextDepartment): ?>
                        <a href="<?php echo e(route('movements.index', ['tab' => 'inbox'])); ?>"
                           class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-emerald-700">
                            Review &amp; send onward
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="mt-8">
                <h3 class="text-2xl font-extrabold text-[#1a1a1a]">Logs</h3>
                <div class="mt-3 space-y-2">
                    <?php $__currentLoopData = $timeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex items-center justify-between border-b border-[#e8f5e9] py-2">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-green-600"></span>
                                <span class="text-[14px] text-[#666666]"><?php echo e($log['event']); ?></span>
                            </div>
                            <span class="text-[13px] font-bold text-[#1a5c1a]"><?php echo e($log['timestamp']); ?></span>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
        </div>
    </div>

    <?php if (! ($isPublicView)): ?>
    <script>
        (function () {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const base = <?php echo json_encode(url('/documents'), 15, 512) ?>;
            document.querySelectorAll('.js-track-complete').forEach(btn => {
                btn.addEventListener('click', async function () {
                    if (!confirm('Mark this document as completed?')) return;
                    btn.disabled = true;
                    const res = await fetch(base + '/' + encodeURIComponent(this.dataset.tracking) + '/complete', {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    });
                    if (res.ok) location.reload();
                    else { alert('Could not complete document.'); btn.disabled = false; }
                });
            });
        })();
    </script>
    <?php endif; ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/track/show.blade.php ENDPATH**/ ?>