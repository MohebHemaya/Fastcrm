import { useState, useEffect } from 'react';

/**
 * Custom hook to detect media query matches.
 * Fully compatible with window.matchMedia mocking in Vitest / RTL.
 */
export function useMediaQuery(query: string, defaultMatches = false): boolean {
  const [matches, setMatches] = useState<boolean>(() => {
    if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
      return window.matchMedia(query).matches;
    }
    return defaultMatches;
  });

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return;
    }

    const mediaQueryList = window.matchMedia(query);
    setMatches(mediaQueryList.matches);

    const listener = (event: MediaQueryListEvent) => {
      setMatches(event.matches);
    };

    if (mediaQueryList.addEventListener) {
      mediaQueryList.addEventListener('change', listener);
      return () => mediaQueryList.removeEventListener('change', listener);
    } else if ((mediaQueryList as any).addListener) {
      (mediaQueryList as any).addListener(listener);
      return () => (mediaQueryList as any).removeListener(listener);
    }
  }, [query]);

  return matches;
}

/**
 * Returns true if the viewport width is < 768px (mobile phones).
 */
export function useIsMobile(): boolean {
  return useMediaQuery('(max-width: 767px)');
}

export default useMediaQuery;
