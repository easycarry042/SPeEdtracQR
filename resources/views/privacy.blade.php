{{-- Privacy notice (RA 10173, Data Privacy Act of 2012). The request form's
     consent checkbox and the footer both link here, so a citizen can read what
     they are agreeing to before they agree.

     Every statement below describes what the system actually does. The two
     facts the code cannot know — how long records are kept, and who the
     City's Data Protection Officer is — are marked "To be confirmed" until the
     City supplies them. Do not publish with those markers still showing. --}}
@php
    $updated = 'October 17, 2026';
    $tbc = 'rounded bg-amber-100 px-1.5 py-0.5 font-semibold text-amber-900';

    $sections = [
        'collect' => 'What we collect',
        'use' => 'How we use it',
        'see' => 'Who can see it',
        'public' => 'What the public tracking page shows',
        'keep' => 'How long we keep it',
        'protect' => 'How we protect it',
        'rights' => 'Your rights',
        'contact' => 'Contact and complaints',
    ];
@endphp
<x-citizen-layout hide-speedy>
    <x-slot name="title">Privacy Notice</x-slot>

    <article class="mx-auto max-w-[760px] pb-6 text-[16px] leading-relaxed text-[#353535] sm:text-[17px]">
        <header>
            <x-page-intro eyebrow="City Government of San Pedro, Laguna" title="Privacy Notice">
                How <span translate="no">SPeED TraQR</span> handles the personal information you give when you file
                or track a request, under the Data Privacy Act of 2012 (RA&nbsp;10173).
            </x-page-intro>
            <p class="mt-3 text-[14px] text-[#4b5a50]">Last updated: <time datetime="2026-10-17">{{ $updated }}</time></p>
        </header>

        <nav aria-label="On this page" class="mt-8 rounded-[16px] border border-[rgba(0,64,4,0.12)] bg-[#f5faf3] p-5">
            <p class="text-[13px] font-black uppercase tracking-[0.14em] text-[#004004]">On this page</p>
            <ol class="mt-3 grid gap-x-6 gap-y-1.5 text-[15px] sm:grid-cols-2">
                @foreach ($sections as $id => $label)
                    <li><a href="#{{ $id }}" class="font-semibold text-[#01721a] underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[#01721a]">{{ $label }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="privacy-body mt-4">
            <section id="collect" class="scroll-mt-24">
                <h2>What we collect</h2>
                <p>When you submit a request, we collect only what is needed to process it and reach you about it:</p>
                <ul>
                    <li>your full name and email address;</li>
                    <li>your contact number, if you choose to give one;</li>
                    <li>the type of request, its purpose and any description you write;</li>
                    <li>the requirement files you upload (images, PDFs or Word documents);</li>
                    <li>messages and files you send to the office later, from your tracking page.</li>
                </ul>
                <p>The system also records each step of your request: when it was filed, every office that scanned it in or out, and who released it.</p>
            </section>

            <section id="use" class="scroll-mt-24">
                <h2>How we use it</h2>
                <p>We use your information only to handle the request you filed:</p>
                <ul>
                    <li>to route it to the offices that must act on it;</li>
                    <li>to email you your tracking number, QR code and status updates;</li>
                    <li>to contact you if a requirement needs to be corrected or something is missing;</li>
                    <li>to release the finished document to you.</li>
                </ul>
                <p>Processing records are also used, without names, to measure processing times against the targets set by the Ease of Doing Business Act (RA&nbsp;11032). We do not sell your information or use it for advertising.</p>
            </section>

            <section id="see" class="scroll-mt-24">
                <h2>Who can see it</h2>
                <p>Only authorised City Hall staff can see your personal details, and only staff of the offices your request is routed to. Every action they take on it is logged with their name, so access can be audited.</p>
                <p>Uploaded files are stored privately. They are never posted on a public web address and can be opened only by signed-in staff of the offices concerned.</p>
            </section>

            <section id="public" class="scroll-mt-24">
                <h2>What the public tracking page shows</h2>
                <p>Anyone with your tracking number or QR code can see your request’s current status, the office that has it, and its routing history, and can print its claim slip, which carries your name so staff can release the document to you.</p>
                <p>Your email address and contact number are shown only partly hidden (for example, j•••@gmail.com and •••4567), enough for you to recognise them. Your uploaded files are never shown there. Keep your tracking number and QR code to yourself, as you would a claim stub.</p>
            </section>

            <section id="keep" class="scroll-mt-24">
                <h2>How long we keep it</h2>
                <p>
                    We keep your request and its files for
                    <span class="{{ $tbc }}">[To be confirmed: retention period set by the City]</span>
                    after it is released, as required for government records. After that period, personal details and files are securely deleted.
                </p>
            </section>

            <section id="protect" class="scroll-mt-24">
                <h2>How we protect it</h2>
                <ul>
                    <li>Staff accounts are protected by passwords and given only the access their role needs.</li>
                    <li>Uploaded files are kept on private storage, not on a public web address.</li>
                    <li>QR codes are signed, so ordinary scanner apps cannot read them and they cannot be forged.</li>
                    <li>The site uses only the cookies it needs to keep your session secure. There are no advertising or tracking cookies.</li>
                </ul>
            </section>

            <section id="rights" class="scroll-mt-24">
                <h2>Your rights</h2>
                <p>Under the Data Privacy Act you have the right to:</p>
                <ul>
                    <li>be informed of how your personal information is processed;</li>
                    <li>access the personal information we hold about you;</li>
                    <li>correct information that is wrong or incomplete;</li>
                    <li>object to processing, or ask for your information to be erased or blocked, where the law allows;</li>
                    <li>be compensated for damages caused by unlawful processing;</li>
                    <li>file a complaint with the National Privacy Commission.</li>
                </ul>
                <p>Note that withdrawing your information while a request is still in process may mean the request cannot be completed.</p>
            </section>

            <section id="contact" class="scroll-mt-24">
                <h2>Contact and complaints</h2>
                <p>To use any of these rights, or to ask about this notice, contact the City’s Data Protection Officer:</p>
                <p>
                    <span class="{{ $tbc }}">[To be confirmed: name or office of the Data Protection Officer]</span><br>
                    <span class="{{ $tbc }}">[To be confirmed: DPO email address and phone number]</span>
                </p>
                <p>
                    For questions about a request, email
                    <a href="mailto:speedtraqr@gmail.com">speedtraqr@gmail.com</a>
                    and include your tracking number.
                </p>
                <p>
                    If you are not satisfied with our response, you may file a complaint with the
                    <a href="https://privacy.gov.ph" rel="noopener" target="_blank">National Privacy Commission</a>.
                </p>
            </section>
        </div>
    </article>
</x-citizen-layout>
