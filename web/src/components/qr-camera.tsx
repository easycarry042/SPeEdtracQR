/**
 * A square camera box for `SpeedQr.start(id)`.
 *
 * The `.qr-viewport` / `.qr-surface` pair keeps the decoder reading the stream
 * at its own shape and only draws it smaller: a plain box let the video come
 * out shorter than the box html5-qrcode had measured, leaving a grey band under
 * the picture, and skewed what it decoded. Never `object-fit: cover` on the
 * video. The corner brackets mark the 70% the decoder reads.
 */
export function QrCamera({
  id,
  className = 'w-full max-w-[320px]',
}: {
  id: string;
  className?: string;
}) {
  return (
    <div className={`qr-viewport relative overflow-hidden rounded-[16px] bg-[#1e1e1e] ${className}`}>
      <div id={id} className="qr-surface" />
      <div
        className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center"
        aria-hidden="true"
      >
        <div className="relative aspect-square w-[70%]">
          <span className="absolute left-0 top-0 h-[30%] w-[30%] rounded-tl-[11px] border-l-[6px] border-t-[6px] border-white" />
          <span className="absolute right-0 top-0 h-[30%] w-[30%] rounded-tr-[11px] border-r-[6px] border-t-[6px] border-white" />
          <span className="absolute bottom-0 left-0 h-[30%] w-[30%] rounded-bl-[11px] border-b-[6px] border-l-[6px] border-white" />
          <span className="absolute bottom-0 right-0 h-[30%] w-[30%] rounded-br-[11px] border-b-[6px] border-r-[6px] border-white" />
        </div>
      </div>
    </div>
  );
}
