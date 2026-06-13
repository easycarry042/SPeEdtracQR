<?php
    $statusColor = match($document->status) {
        'completed'  => ['bg' => 'bg-green-100',  'text' => 'text-green-800',  'dot' => 'bg-green-500',  'label' => 'Completed'],
        'pending'    => ['bg' => 'bg-blue-100',   'text' => 'text-blue-800',   'dot' => 'bg-blue-500',   'label' => 'Pending'],
        'returned'   => ['bg' => 'bg-rose-100',   'text' => 'text-rose-800',   'dot' => 'bg-rose-500',   'label' => 'Returned'],
        default      => ['bg' => 'bg-amber-100',  'text' => 'text-amber-800',  'dot' => 'bg-amber-500',  'label' => 'In Transit'],
    };
?>

<?php if (isset($component)) { $__componentOriginala606a25bab88b416124c97ea909da1e5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginala606a25bab88b416124c97ea909da1e5 = $attributes; } ?>
<?php $component = App\View\Components\CitizenLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('citizen-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\CitizenLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('title', null, []); ?> Tracking <?php echo e($document->tracking_number); ?> <?php $__env->endSlot(); ?>

    
    <div class="mb-6">
        <a href="<?php echo e(route('citizen.track')); ?>"
           class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-600 hover:underline">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Track Another Document
        </a>
    </div>

    <div class="mx-auto max-w-2xl space-y-6">

        
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="bg-emerald-600 px-6 py-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-200">Tracking Number</p>
                <p class="mt-0.5 font-mono text-2xl font-extrabold text-white"><?php echo e($document->tracking_number); ?></p>
            </div>

            <div class="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Document Type</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800"><?php echo e($document->document_type); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Citizen</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800"><?php echo e($document->citizen_name ?? 'N/A'); ?></p>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">Submitted</p>
                    <p class="mt-1 text-sm font-semibold text-gray-800"><?php echo e($document->created_at->format('M d, Y')); ?></p>
                </div>
            </div>

            
        </div>

        
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" id="statusCard">
            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Current Status</h2>
                <div class="flex items-center gap-2 text-xs text-gray-400" id="lastChecked">
                    <svg class="h-3.5 w-3.5 animate-spin text-emerald-500 hidden" id="pollSpinner" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    <span id="lastCheckedText">Auto-updates every 30 s</span>
                </div>
            </div>

            <div class="p-6">
                
                <div class="flex flex-wrap items-center gap-4">
                    <span id="statusBadge"
                          class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold <?php echo e($statusColor['bg']); ?> <?php echo e($statusColor['text']); ?>">
                        <span class="h-2 w-2 rounded-full <?php echo e($statusColor['dot']); ?>"></span>
                        <span id="statusLabel"><?php echo e($statusColor['label']); ?></span>
                    </span>

                    <div>
                        <p class="text-xs text-gray-400">Current Location</p>
                        <p class="text-sm font-semibold text-gray-800" id="currentDept">
                            <?php echo e($document->currentDepartment->name ?? 'Not yet assigned'); ?>

                        </p>
                    </div>
                </div>

                
                <div id="updateBanner"
                     class="mt-4 hidden rounded-xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                    Status updated! Refreshing…
                </div>
            </div>
        </div>

        <?php if($routingChain->isNotEmpty()): ?>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white px-6 py-5 shadow-sm">
            <h2 class="mb-4 text-base font-bold text-gray-800">Department Progress</h2>
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
        </div>
        <?php endif; ?>

        
        <?php if($document->status !== 'completed'): ?>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Upload supporting documents</h2>
                <p class="mt-1 text-sm text-gray-500">
                    Files are sent only to
                    <span class="font-semibold text-emerald-800"><?php echo e($document->currentDepartment->name ?? ($routingChain->first()?->name ?? 'the office handling your ticket')); ?></span>.
                </p>
            </div>
            <div class="px-6 py-5">
                <?php if(session('upload_success')): ?>
                    <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                        <?php echo e(session('upload_success')); ?>

                    </div>
                <?php endif; ?>
                <?php if($errors->any()): ?>
                    <div class="mb-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        <?php echo e($errors->first()); ?>

                    </div>
                <?php endif; ?>
                <form method="POST" action="<?php echo e(route('track.citizen-upload', $document->tracking_number)); ?>" enctype="multipart/form-data" class="space-y-4">
                    <?php echo csrf_field(); ?>
                    <div>
                        <label for="citizen_attachments" class="block text-sm font-medium text-gray-700">Photos (up to 5)</label>
                        <input type="file" id="citizen_attachments" name="attachments[]" accept="image/*" multiple required
                               class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-emerald-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-emerald-700" />
                    </div>
                    <div>
                        <label for="citizen_note" class="block text-sm font-medium text-gray-700">Short note (optional)</label>
                        <textarea id="citizen_note" name="note" rows="2" maxlength="1000" placeholder="e.g. Missing ID copy attached"
                                  class="mt-1 block w-full rounded-lg border border-gray-300 text-sm shadow-sm"><?php echo e(old('note')); ?></textarea>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700 sm:w-auto">
                        Send to department
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-bold text-gray-800">Activity Log</h2>
            </div>
            <div class="divide-y divide-gray-50 px-6" id="timeline">
                <?php $__empty_1 = true; $__currentLoopData = $timeline; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                    <div class="flex items-start justify-between gap-4 py-3">
                        <div class="flex items-center gap-3">
                            <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full
                                <?php echo e($log['action'] === 'in' ? 'bg-emerald-500' : 'bg-gray-400'); ?>">
                            </span>
                            <span class="text-sm text-gray-700"><?php echo e($log['event']); ?></span>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-gray-400"><?php echo e($log['timestamp']); ?></span>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                    <p class="py-6 text-center text-sm text-gray-400">No activity recorded yet.</p>
                <?php endif; ?>
            </div>
        </div>

        
        <div class="text-center">
            <a href="<?php echo e(route('citizen.track')); ?>"
               class="inline-flex items-center gap-2 rounded-xl border border-emerald-300 bg-white px-5 py-3 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-50">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7V5a2 2 0 0 1 2-2h2m10 0h2a2 2 0 0 1 2 2v2m0 10v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/>
                    <rect x="9" y="9" width="6" height="6" rx="1"/>
                </svg>
                Scan or Search Another Document
            </a>
        </div>
    </div>

    
    <script>
        const trackingNumber = <?php echo json_encode($document->tracking_number, 15, 512) ?>;
        const statusEndpoint = '/track/' + encodeURIComponent(trackingNumber) + '/status';
        let currentStatus    = <?php echo json_encode($document->status, 15, 512) ?>;

        const statusDotClasses = {
            completed: { bg: 'bg-green-100',  text: 'text-green-800',  dot: 'bg-green-500',  label: 'Completed'  },
            pending:   { bg: 'bg-blue-100',   text: 'text-blue-800',   dot: 'bg-blue-500',   label: 'Pending'    },
            returned:  { bg: 'bg-rose-100',   text: 'text-rose-800',   dot: 'bg-rose-500',   label: 'Returned'   },
            in_transit:{ bg: 'bg-amber-100',  text: 'text-amber-800',  dot: 'bg-amber-500',  label: 'In Transit' },
        };

        function getStatusClasses(status) {
            return statusDotClasses[status] ?? statusDotClasses['in_transit'];
        }

        async function pollStatus() {
            const spinner = document.getElementById('pollSpinner');
            const checkedText = document.getElementById('lastCheckedText');
            spinner.classList.remove('hidden');

            try {
                const res = await fetch(statusEndpoint, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();

                const badge = document.getElementById('statusBadge');
                const label = document.getElementById('statusLabel');
                const dept  = document.getElementById('currentDept');
                const classes = getStatusClasses(data.status);

                // Detect change
                if (data.status !== currentStatus) {
                    document.getElementById('updateBanner').classList.remove('hidden');
                    setTimeout(() => location.reload(), 1800);
                    return;
                }

                // Update badge colours
                badge.className = `inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold ${classes.bg} ${classes.text}`;
                badge.querySelector('span:first-child').className = `h-2 w-2 rounded-full ${classes.dot}`;
                label.textContent = classes.label;
                dept.textContent  = data.current_department ?? 'Not yet assigned';
                currentStatus = data.status;

                const now = new Date();
                checkedText.textContent = 'Last checked ' + now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } catch (_) {
                // silent fail — network may be temporarily down
            } finally {
                spinner.classList.add('hidden');
            }
        }

        // Poll every 30 seconds
        setInterval(pollStatus, 30000);
    </script>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginala606a25bab88b416124c97ea909da1e5)): ?>
<?php $attributes = $__attributesOriginala606a25bab88b416124c97ea909da1e5; ?>
<?php unset($__attributesOriginala606a25bab88b416124c97ea909da1e5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginala606a25bab88b416124c97ea909da1e5)): ?>
<?php $component = $__componentOriginala606a25bab88b416124c97ea909da1e5; ?>
<?php unset($__componentOriginala606a25bab88b416124c97ea909da1e5); ?>
<?php endif; ?>
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/track/show-citizen.blade.php ENDPATH**/ ?>