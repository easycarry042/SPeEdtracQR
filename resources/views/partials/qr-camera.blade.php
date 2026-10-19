{{-- A square camera box for window.SpeedQr.start($id). The .qr-viewport /
     .qr-surface pair keeps the decoder reading the stream at its own shape and
     only draws it smaller (see .qr-viewport in app.css): a plain box let the
     video come out shorter than the box html5-qrcode had measured, leaving a
     grey band under the picture, and skewed what it decoded. The corner
     brackets mark the 70% the decoder reads. $class sizes the box. --}}
<div class="qr-viewport relative overflow-hidden rounded-[16px] bg-[#1e1e1e] {{ $class ?? 'w-full max-w-[320px]' }}">
    <div id="{{ $id }}" class="qr-surface"></div>
    <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
        <div class="relative aspect-square w-[70%]">
            <span class="absolute left-0 top-0 h-[30%] w-[30%] rounded-tl-[11px] border-l-[6px] border-t-[6px] border-white"></span>
            <span class="absolute right-0 top-0 h-[30%] w-[30%] rounded-tr-[11px] border-r-[6px] border-t-[6px] border-white"></span>
            <span class="absolute bottom-0 left-0 h-[30%] w-[30%] rounded-bl-[11px] border-b-[6px] border-l-[6px] border-white"></span>
            <span class="absolute bottom-0 right-0 h-[30%] w-[30%] rounded-br-[11px] border-b-[6px] border-r-[6px] border-white"></span>
        </div>
    </div>
</div>
