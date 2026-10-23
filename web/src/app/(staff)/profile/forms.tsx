'use client';

import { useEffect, useRef, useState, useTransition } from 'react';
import { useRouter } from 'next/navigation';
import {
  deleteAvatar,
  deleteSignature,
  saveSignature,
  updatePassword,
  updateProfileDetails,
  uploadAvatar,
  type ProfileResult,
} from '@/app/actions/profile';
import { routes } from '@/lib/routes';

/** The Profile page's forms — ports of `profile/partials/*`. */
function Notice({ result }: { result: ProfileResult | null }) {
  if (!result) {
    return null;
  }

  return result.ok ? (
    <p className="mt-3 rounded-[8px] border border-green-wash bg-green-wash px-4 py-2.5 text-[13px] font-medium text-green-deep" role="status">
      {result.message}
    </p>
  ) : (
    <p className="mt-3 text-[13px] text-status-red" role="alert">
      {result.message}
    </p>
  );
}

function useAction() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [result, setResult] = useState<ProfileResult | null>(null);

  const run = (action: () => Promise<ProfileResult>, after?: (result: ProfileResult) => void): void => {
    startTransition(async () => {
      const outcome = await action();
      setResult(outcome);
      after?.(outcome);

      if (outcome.ok) {
        router.refresh();
      }
    });
  };

  return { pending, result, run };
}

const INPUT =
  'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/20';

export function AvatarForm({ avatarUrl, initials }: { avatarUrl: string | null; initials: string }) {
  const { pending, result, run } = useAction();
  const [preview, setPreview] = useState<string | null>(null);
  const [name, setName] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => () => {
    if (preview) {
      URL.revokeObjectURL(preview);
    }
  }, [preview]);

  const reset = (): void => {
    setName('');
    setPreview(null);

    if (inputRef.current) {
      inputRef.current.value = '';
    }
  };

  return (
    <section>
      <header>
        <h2 className="text-[15px] font-semibold text-green-deep">Profile Picture</h2>
        <p className="mt-1 text-[13px] text-ink-soft">
          Shown on your profile, in the staff directory, and beside your name across the system. Colleagues can see it;
          citizens never do.
        </p>
      </header>
      <Notice result={result} />

      <form
        className="mt-4"
        onSubmit={(event) => {
          event.preventDefault();
          const form = new FormData(event.currentTarget);
          run(() => uploadAvatar(form), (outcome) => outcome.ok && reset());
        }}
      >
        <div className="flex flex-wrap items-center gap-5">
          {preview || avatarUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img src={preview ?? avatarUrl ?? ''} alt="" className="h-20 w-20 rounded-full border border-hairline object-cover" />
          ) : (
            <span className="inline-flex h-20 w-20 items-center justify-center rounded-full border border-hairline bg-green-wash text-[26px] font-bold text-green-deep">
              {initials}
            </span>
          )}
          <div>
            <label htmlFor="avatar" className="cr-btn cursor-pointer">
              Choose picture…
            </label>
            <input
              id="avatar"
              ref={inputRef}
              name="avatar"
              type="file"
              accept=".jpg,.jpeg,.png,.webp"
              className="sr-only"
              onChange={(event) => {
                const file = event.target.files?.[0];
                setName(file?.name ?? '');
                setPreview(file ? URL.createObjectURL(file) : null);
              }}
            />
            <p className="mt-2 text-[12px] text-ink-soft">
              {name ? <span className="font-medium text-ink">{name}</span> : 'JPG, PNG, or WebP, up to 2 MB.'}
            </p>
          </div>
        </div>
        <div className="mt-4 flex flex-wrap items-center gap-3">
          <button type="submit" className="cr-btn cr-btn-primary" disabled={!name || pending}>
            Save picture
          </button>
          {name ? (
            <button type="button" className="cr-btn" onClick={reset}>
              Cancel
            </button>
          ) : null}
        </div>
      </form>

      {avatarUrl ? (
        <button
          type="button"
          disabled={pending}
          className="mt-3 text-[13px] font-semibold text-status-red hover:underline"
          onClick={() => {
            if (window.confirm('Remove your profile picture? Your initials will be shown instead.')) {
              run(() => deleteAvatar());
            }
          }}
        >
          Remove picture
        </button>
      ) : null}
    </section>
  );
}

export function DetailsForm({ name, email }: { name: string; email: string }) {
  const { pending, result, run } = useAction();

  return (
    <section>
      <header>
        <h2 className="text-lg font-medium text-gray-900">Profile Information</h2>
        <p className="mt-1 text-sm text-gray-600">Update your account&rsquo;s profile information and email address.</p>
      </header>
      <form
        className="mt-6 space-y-6"
        onSubmit={(event) => {
          event.preventDefault();
          const form = new FormData(event.currentTarget);
          run(() => updateProfileDetails(form));
        }}
      >
        <div>
          <label htmlFor="name" className="block text-sm font-medium text-gray-700">
            Name
          </label>
          <input id="name" name="name" type="text" defaultValue={name} required autoComplete="name" className={INPUT} />
        </div>
        <div>
          <label htmlFor="email" className="block text-sm font-medium text-gray-700">
            Email
          </label>
          <input id="email" name="email" type="email" defaultValue={email} required autoComplete="username" className={INPUT} />
        </div>
        <div className="flex items-center gap-4">
          <button type="submit" disabled={pending} className="cr-btn cr-btn-primary">
            Save
          </button>
        </div>
      </form>
      <Notice result={result} />
    </section>
  );
}

export function PasswordForm() {
  const { pending, result, run } = useAction();
  const formRef = useRef<HTMLFormElement>(null);

  return (
    <section>
      <header>
        <h2 className="text-lg font-medium text-gray-900">Update Password</h2>
        <p className="mt-1 text-sm text-gray-600">Use a long, random password to keep your account secure.</p>
      </header>
      <form
        ref={formRef}
        className="mt-6 space-y-6"
        onSubmit={(event) => {
          event.preventDefault();
          const form = new FormData(event.currentTarget);
          run(() => updatePassword(form), (outcome) => outcome.ok && formRef.current?.reset());
        }}
      >
        {[
          ['current_password', 'Current Password', 'current-password'],
          ['password', 'New Password', 'new-password'],
          ['password_confirmation', 'Confirm Password', 'new-password'],
        ].map(([field, label, autoComplete]) => (
          <div key={field}>
            <label htmlFor={field} className="block text-sm font-medium text-gray-700">
              {label}
            </label>
            <input id={field} name={field} type="password" required autoComplete={autoComplete} className={INPUT} />
          </div>
        ))}
        <button type="submit" disabled={pending} className="cr-btn cr-btn-primary">
          Save
        </button>
      </form>
      <Notice result={result} />
    </section>
  );
}

/**
 * Draw the signature once. Approving an internal request affixes a frozen
 * copy of it to that step.
 */
export function SignatureForm({ hasSignature }: { hasSignature: boolean }) {
  const { pending, result, run } = useAction();
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const drawing = useRef(false);
  const [dirty, setDirty] = useState(false);
  // Re-fetches the preview after a save replaces the file.
  const [version, setVersion] = useState(0);

  const context = (): CanvasRenderingContext2D | null => {
    const ctx = canvasRef.current?.getContext('2d') ?? null;

    if (ctx) {
      ctx.lineWidth = 2.5;
      ctx.lineCap = 'round';
      ctx.lineJoin = 'round';
      ctx.strokeStyle = '#16211b';
    }

    return ctx;
  };

  // The canvas is CSS-scaled; map pointer coordinates back to bitmap space.
  const point = (event: React.PointerEvent<HTMLCanvasElement>) => {
    const canvas = event.currentTarget;
    const rect = canvas.getBoundingClientRect();

    return {
      x: (event.clientX - rect.left) * (canvas.width / rect.width),
      y: (event.clientY - rect.top) * (canvas.height / rect.height),
    };
  };

  const clear = (): void => {
    const canvas = canvasRef.current;
    context()?.clearRect(0, 0, canvas?.width ?? 0, canvas?.height ?? 0);
    setDirty(false);
  };

  return (
    <section>
      <header>
        <h2 className="text-[15px] font-semibold text-green-deep">E-Signature</h2>
        <p className="mt-1 text-[13px] text-ink-soft">
          Draw your signature once. Approving an internal request affixes it as the official endorsement of your office.
        </p>
      </header>
      <Notice result={result} />

      {hasSignature ? (
        <>
          <div className="mt-4 flex items-center gap-4">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img
              src={`${routes.profileSignatureShow()}?v=${version}`}
              alt="Your registered e-signature"
              className="h-16 rounded-[8px] border border-hairline bg-white px-3 py-2"
            />
            <button
              type="button"
              disabled={pending}
              className="text-[13px] font-semibold text-status-red hover:underline"
              onClick={() => {
                if (
                  window.confirm(
                    'Remove your registered signature? You will not be able to approve internal requests until you draw a new one.',
                  )
                ) {
                  run(() => deleteSignature());
                }
              }}
            >
              Remove
            </button>
          </div>
          <p className="mt-2 text-[12px] text-ink-soft">
            Drawing and saving below replaces it. Signatures already affixed to past approvals are frozen copies and stay
            unchanged.
          </p>
        </>
      ) : null}

      <div className="mt-4">
        <canvas
          ref={canvasRef}
          width={560}
          height={180}
          role="img"
          aria-label="Signature drawing area"
          className="w-full max-w-xl cursor-crosshair touch-none rounded-[10px] border-2 border-dashed border-hairline-strong bg-white"
          onPointerDown={(event) => {
            drawing.current = true;
            const p = point(event);
            const ctx = context();
            ctx?.beginPath();
            ctx?.moveTo(p.x, p.y);
          }}
          onPointerMove={(event) => {
            if (!drawing.current) {
              return;
            }

            const p = point(event);
            const ctx = canvasRef.current?.getContext('2d');
            ctx?.lineTo(p.x, p.y);
            ctx?.stroke();
            setDirty(true);
          }}
          onPointerUp={() => (drawing.current = false)}
          onPointerLeave={() => (drawing.current = false)}
        />
        <p className="mt-1 text-[12px] text-ink-soft">Sign inside the box using your mouse, finger, or stylus.</p>
      </div>

      <div className="mt-4 flex items-center gap-3">
        <button
          type="button"
          disabled={!dirty || pending}
          className={`cr-btn cr-btn-primary${dirty ? '' : ' cursor-not-allowed opacity-40'}`}
          onClick={() => {
            const dataUrl = canvasRef.current?.toDataURL('image/png') ?? '';
            run(() => saveSignature(dataUrl), (outcome) => {
              if (outcome.ok) {
                clear();
                setVersion((v) => v + 1);
              }
            });
          }}
        >
          Save signature
        </button>
        <button type="button" onClick={clear} className="cr-btn">
          Clear
        </button>
      </div>
    </section>
  );
}
