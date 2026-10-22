'use client';

import { useActionState, useState } from 'react';
import { PublicTrackForm } from '@/components/citizen/public-track-form';
import { login, type LoginState } from './actions';

/**
 * The form half of the sign-in card. The tab switch and the password reveal
 * were Alpine `x-data` state in the Blade view; here they are plain React state.
 */
export function LoginCard({
  initialTab,
  next,
}: {
  initialTab: 'signin' | 'track';
  next?: string;
}) {
  const [tab, setTab] = useState<'signin' | 'track'>(initialTab);
  const [showPassword, setShowPassword] = useState(false);
  const [state, formAction, pending] = useActionState<LoginState, FormData>(login, undefined);

  return (
    <div className="auth-right">
      <div className="auth-tabs" role="tablist" aria-label="What would you like to do?">
        <button
          type="button"
          role="tab"
          aria-selected={tab === 'signin'}
          className={tab === 'signin' ? 'on' : undefined}
          onClick={() => setTab('signin')}
        >
          Staff sign in
        </button>
        <button
          type="button"
          role="tab"
          aria-selected={tab === 'track'}
          className={tab === 'track' ? 'on' : undefined}
          onClick={() => setTab('track')}
        >
          Track a request
        </button>
      </div>

      {tab === 'track' ? (
        <PublicTrackForm heading />
      ) : (
        <div>
          <h2 className="auth-heading">Welcome</h2>
          <p className="auth-subheading">Input your credentials to continue</p>

          <form action={formAction} className="auth-form">
            {next ? <input type="hidden" name="next" value={next} /> : null}

            <div className="form-group">
              <label htmlFor="email" className="form-label">
                Email
              </label>
              <div className="field-shell">
                <span className="field-icon" aria-hidden="true">
                  <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24">
                    <rect x="3.5" y="5.5" width="17" height="13" rx="2" />
                    <path strokeLinecap="round" strokeLinejoin="round" d="m4 7 8 6 8-6" />
                  </svg>
                </span>
                <input
                  id="email"
                  type="email"
                  name="email"
                  required
                  autoFocus
                  autoComplete="username"
                  placeholder="Enter your email"
                  className="form-input form-input-with-icon"
                />
              </div>
            </div>

            <div className="form-group">
              <label htmlFor="password" className="form-label">
                Password
              </label>
              <div className="field-shell">
                <span className="field-icon" aria-hidden="true">
                  <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24">
                    <rect x="4.5" y="10.5" width="15" height="10" rx="2" />
                    <path strokeLinecap="round" d="M8 10.5V8a4 4 0 0 1 8 0v2.5" />
                  </svg>
                </span>
                <input
                  id="password"
                  type={showPassword ? 'text' : 'password'}
                  name="password"
                  required
                  autoComplete="current-password"
                  placeholder="Enter your password"
                  className="form-input form-input-with-icon form-input-with-toggle"
                />

                <button
                  type="button"
                  className="pass-toggle"
                  onClick={() => setShowPassword((shown) => !shown)}
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                >
                  {showPassword ? (
                    <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24" aria-hidden="true">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M3 3l18 18M10.6 5.1A9.8 9.8 0 0 1 12 5c5 0 9 4.5 10 7-.4 1-1.2 2.3-2.4 3.5M6.6 6.6C4.5 8 3 10 2 12c1 2.5 5 7 10 7 1.9 0 3.6-.6 5-1.5M9.9 9.9a3 3 0 0 0 4.2 4.2"
                      />
                    </svg>
                  ) : (
                    <svg fill="none" stroke="currentColor" strokeWidth={1.9} viewBox="0 0 24 24" aria-hidden="true">
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"
                      />
                      <circle cx="12" cy="12" r="3" />
                    </svg>
                  )}
                </button>
              </div>
            </div>

            {state?.error ? (
              <p className="form-error" role="alert">
                {state.error}
              </p>
            ) : null}

            <button type="submit" className="auth-button" disabled={pending}>
              {pending ? 'Signing in…' : 'Login'}
              <svg fill="none" stroke="currentColor" strokeWidth={2.4} viewBox="0 0 24 24" aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d="M5 12h14m-6-6 6 6-6 6" />
              </svg>
            </button>
          </form>
        </div>
      )}
    </div>
  );
}
