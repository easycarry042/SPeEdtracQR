'use client';

import { useEffect } from 'react';

/** The Blade sticker opened the print dialog on load; so does this. */
export function PrintOnLoad() {
  useEffect(() => {
    window.print();
  }, []);

  return null;
}
