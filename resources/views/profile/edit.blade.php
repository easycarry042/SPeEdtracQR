<x-app-layout>
    <div class="page-shell page-shell-loose">
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            @can('act on internal requests')
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-2xl">
                    @include('profile.partials.signature-form')
                </div>
            </div>
            @endcan

            {{-- Account deletion is deliberately NOT offered here: staff accounts
                 carry the audit trail of everything they processed, so only an
                 administrator may archive one (User management). --}}
            <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <h2 class="text-lg font-medium text-gray-900">Staff badge</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Your badge QR signs endorsement decisions. Print it, keep it with you,
                        and reissue it if the card is lost.
                    </p>
                    <a href="{{ route('profile.badge') }}" class="cr-btn cr-btn-sm mt-4">Open my badge</a>
                </div>
            </div>
    </div>
</x-app-layout>
