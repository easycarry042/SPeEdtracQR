'use client';

import { useEffect, useRef } from 'react';

/**
 * The landing hero's background: a muted, looping video of the city under a
 * white veil, so the headline and buttons stay readable over it.
 *
 * - The veil's strength is one CSS variable on the hero (`--hero-veil`, 0–1):
 *   raise it if the text ever loses contrast over a busy part of the footage.
 *   It is heavier on the left, where the copy sits.
 * - Scrolling fades the video out over the first ~70% of the hero, and its
 *   bottom edge feathers into white, so the next section takes over smoothly.
 * - It pauses while off-screen. With reduced motion asked for, it never plays:
 *   the poster frame stands in.
 */
export function HeroVideo({ src, poster }: { src: string; poster: string }) {
  const layer = useRef<HTMLDivElement>(null);
  const video = useRef<HTMLVideoElement>(null);

  useEffect(() => {
    const element = layer.current;
    const player = video.current;
    const hero = element?.closest('section');

    if (!element || !player || !hero) {
      return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reduceMotion) {
      player.play().catch(() => {
        // Autoplay refused (data saver, low power): the poster stays.
      });
    }

    let frame = 0;
    const fade = (): void => {
      cancelAnimationFrame(frame);
      frame = requestAnimationFrame(() => {
        const travelled = Math.max(0, -hero.getBoundingClientRect().top);
        const progress = Math.min(1, travelled / (hero.offsetHeight * 0.7));
        element.style.opacity = String(1 - progress);
      });
    };

    const seen = new IntersectionObserver(([entry]) => {
      if (reduceMotion) {
        return;
      }

      if (entry.isIntersecting) {
        player.play().catch(() => {});
      } else {
        player.pause();
      }
    });

    fade();
    seen.observe(hero);
    window.addEventListener('scroll', fade, { passive: true });
    window.addEventListener('resize', fade);

    return () => {
      cancelAnimationFrame(frame);
      seen.disconnect();
      window.removeEventListener('scroll', fade);
      window.removeEventListener('resize', fade);
    };
  }, []);

  return (
    <div ref={layer} className="hero-video" aria-hidden="true">
      <video
        ref={video}
        className="hero-video-media"
        src={src}
        poster={poster}
        muted
        loop
        playsInline
        preload="auto"
        disablePictureInPicture
      />
      <div className="hero-video-veil" />
      <div className="hero-video-feather" />
    </div>
  );
}
