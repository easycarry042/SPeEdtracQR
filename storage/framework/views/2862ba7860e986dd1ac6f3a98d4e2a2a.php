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
    <div class="mx-auto max-w-7xl space-y-6">
        <form method="GET" id="historyFilterForm"
              x-data="{ filtersOpen: <?php echo e((request()->filled('document_type') || request()->filled('status') || request()->filled('from') || request()->filled('to')) ? 'true' : 'false'); ?> }"
              class="space-y-3">
            
            <div class="flex flex-wrap items-center gap-3">
                <input type="text" name="search" id="historySearch" value="<?php echo e(request('search')); ?>" autocomplete="off"
                       placeholder="Search tracking #, citizen, or type…"
                       class="flex-1 rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/30 min-w-[200px]">
                <button type="button" @click="filtersOpen = !filtersOpen"
                        :class="filtersOpen ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-gray-200 bg-white text-gray-600'"
                        class="inline-flex items-center gap-2 rounded-xl border px-4 py-2.5 text-sm font-semibold shadow-sm transition hover:bg-gray-50"
                        :aria-expanded="filtersOpen ? 'true' : 'false'">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                    </svg>
                    Filters
                </button>
                <a href="<?php echo e(route('history.export', request()->query())); ?>" id="historyExportLink"
                   class="inline-flex items-center justify-center rounded-xl bg-emerald-800 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-900 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2">
                    Export CSV
                </a>
            </div>

            
            <div x-show="filtersOpen" x-cloak class="grid grid-cols-1 gap-3 rounded-2xl border border-gray-200/90 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-5">
                <select name="document_type" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                    <option value="">All Categories</option>
                    <?php $__currentLoopData = $documentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($type); ?>" <?php if(request('document_type')===$type): echo 'selected'; endif; ?>><?php echo e($type); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <select name="status" class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                    <option value="">All Statuses</option>
                    <?php $__currentLoopData = $statuses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $status): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($status); ?>" <?php if(request('status')===$status): echo 'selected'; endif; ?>><?php echo e(str_replace('_', ' ', ucfirst($status))); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <input type="date" name="from" value="<?php echo e(request('from')); ?>" title="From date"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <input type="date" name="to" value="<?php echo e(request('to')); ?>" title="To date"
                       class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 text-sm shadow-sm focus:border-emerald-400 focus:outline-none">
                <div class="flex items-center gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900">Apply</button>
                    <a href="<?php echo e(route('history')); ?>" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-600 hover:bg-gray-50">Clear</a>
                </div>
            </div>
        </form>

        <div id="historyResults" class="overflow-hidden rounded-2xl border border-gray-200/90 bg-white shadow-md shadow-gray-200/50 transition-opacity duration-150">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead>
                        <tr class="bg-gray-100/90">
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">#</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">File Name</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Tracking ID</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Date</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Category</th>
                            <th class="px-4 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-600">Status</th>
                            <th class="px-4 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-600">Sticker</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php $__empty_1 = true; $__currentLoopData = $documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $index = ($documents->currentPage() - 1) * $documents->perPage() + $loop->iteration;
                            ?>
                            <?php if (isset($component)) { $__componentOriginal43904c7e6a7f200a7120aaf26d029ffa = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal43904c7e6a7f200a7120aaf26d029ffa = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.document-row','data' => ['class' => 'even:bg-gray-50/50','index' => $index,'date' => $doc->created_at->format('M j, Y'),'tracking' => $doc->tracking_number,'fileName' => $doc->citizen_name ?: 'File '.substr($doc->tracking_number, -5),'category' => $doc->document_type,'status' => $doc->status === 'completed' ? 'completed' : $doc->status,'href' => route('track.show', $doc->tracking_number),'stickerHref' => route('documents.sticker', $doc)]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('document-row'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'even:bg-gray-50/50','index' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($index),'date' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($doc->created_at->format('M j, Y')),'tracking' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($doc->tracking_number),'fileName' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($doc->citizen_name ?: 'File '.substr($doc->tracking_number, -5)),'category' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($doc->document_type),'status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($doc->status === 'completed' ? 'completed' : $doc->status),'href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('track.show', $doc->tracking_number)),'sticker-href' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(route('documents.sticker', $doc))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal43904c7e6a7f200a7120aaf26d029ffa)): ?>
<?php $attributes = $__attributesOriginal43904c7e6a7f200a7120aaf26d029ffa; ?>
<?php unset($__attributesOriginal43904c7e6a7f200a7120aaf26d029ffa); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal43904c7e6a7f200a7120aaf26d029ffa)): ?>
<?php $component = $__componentOriginal43904c7e6a7f200a7120aaf26d029ffa; ?>
<?php unset($__componentOriginal43904c7e6a7f200a7120aaf26d029ffa); ?>
<?php endif; ?>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500">No records found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-gray-100 px-4 py-3"><?php echo e($documents->links()); ?></div>
        </div>
    </div>

    <script>
        // Live search: debounce keystrokes, fetch the same page with the current
        // form values, and swap in the fresh table + pagination.
        (function () {
            const form = document.getElementById('historyFilterForm');
            const input = document.getElementById('historySearch');
            const results = document.getElementById('historyResults');
            if (!form || !input || !results) return;

            let timer = null;
            let controller = null;

            input.addEventListener('input', function () {
                clearTimeout(timer);
                timer = setTimeout(refreshResults, 300);
            });

            // Dropdowns and dates refresh immediately on change
            form.querySelectorAll('select, input[type="date"]').forEach(function (el) {
                el.addEventListener('change', refreshResults);
            });

            function refreshResults() {
                const params = new URLSearchParams(new FormData(form));
                const url = '<?php echo e(route('history')); ?>' + (params.toString() ? '?' + params.toString() : '');

                controller?.abort();
                controller = new AbortController();
                results.classList.add('opacity-50');

                fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal })
                    .then(response => response.text())
                    .then(html => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const fresh = doc.getElementById('historyResults');
                        if (fresh) results.innerHTML = fresh.innerHTML;
                        window.history.replaceState({}, '', url);
                        const exportLink = document.getElementById('historyExportLink');
                        if (exportLink) {
                            exportLink.href = '<?php echo e(route('history.export')); ?>' + (params.toString() ? '?' + params.toString() : '');
                        }
                        results.classList.remove('opacity-50');
                    })
                    .catch(error => {
                        if (error.name !== 'AbortError') {
                            results.classList.remove('opacity-50');
                            console.error(error);
                        }
                    });
            }
        })();
    </script>
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
<?php /**PATH C:\Users\conso\Downloads\SPeEdtracQR\resources\views/history/index.blade.php ENDPATH**/ ?>