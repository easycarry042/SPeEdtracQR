<section x-data="{ name: '', preview: null }">
    <header>
        <h2 class="text-[15px] font-semibold text-green-deep">Profile Picture</h2>
        <p class="mt-1 text-[13px] text-ink-soft">
            Shown on your profile, in the staff directory, and beside your name across the system. Colleagues can see it; citizens never do.
        </p>
    </header>

    @if(session('status') === 'avatar-updated')
        <p class="mt-3 rounded-[8px] border border-green-wash bg-green-wash px-4 py-2.5 text-[13px] font-medium text-green-deep" role="status">Profile picture updated.</p>
    @elseif(session('status') === 'avatar-removed')
        <p class="mt-3 rounded-[8px] border border-status-amber-wash bg-status-amber-wash px-4 py-2.5 text-[13px] font-medium text-status-amber" role="status">Profile picture removed.</p>
    @endif
    @error('avatar')<p class="mt-3 text-[13px] text-status-red">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('profile.avatar.store') }}" enctype="multipart/form-data" class="mt-4">
        @csrf

        <div class="flex flex-wrap items-center gap-5">
            {{-- Current picture, or the live preview of the one being chosen. --}}
            <template x-if="preview">
                <img :src="preview" alt="" class="h-20 w-20 rounded-full border border-hairline object-cover">
            </template>
            <template x-if="! preview">
                <span>
                    <x-user-avatar :user="$user"
                                   class="h-20 w-20 rounded-full border border-hairline bg-green-wash text-[26px] font-bold text-green-deep inline-flex items-center justify-center" />
                </span>
            </template>

            <div>
                <label for="avatar" class="cr-btn cursor-pointer">Choose picture…</label>
                <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" class="sr-only"
                       @change="
                           const f = $event.target.files[0];
                           name = f ? f.name : '';
                           preview = f ? URL.createObjectURL(f) : null;
                       ">
                <p class="mt-2 text-[12px] text-ink-soft">
                    <span x-show="! name">JPG, PNG, or WebP, up to 2 MB.</span>
                    <span x-show="name" x-text="name" x-cloak class="font-medium text-ink"></span>
                </p>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <button type="submit" class="cr-btn cr-btn-primary" x-bind:disabled="! name">Save picture</button>
            <button type="button" x-show="name" x-cloak class="cr-btn"
                    @click="name=''; preview=null; document.getElementById('avatar').value='';">Cancel</button>
        </div>
    </form>

    @if($user->avatar_path)
        <form method="POST" action="{{ route('profile.avatar.destroy') }}" class="mt-3"
              onsubmit="return confirm('Remove your profile picture? Your initials will be shown instead.');">
            @csrf @method('DELETE')
            <button type="submit" class="text-[13px] font-semibold text-status-red hover:underline">Remove picture</button>
        </form>
    @endif
</section>
