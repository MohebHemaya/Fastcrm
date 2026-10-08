import { useEffect, useState, useCallback, useRef } from 'react';
import { sidebarService, type SidebarBadges } from '@/services/sidebarService';
import { useAuthStore } from '@/lib/stores/useAuthStore';

const POLL_INTERVAL_MS = 60_000; // 60 seconds

export function useSidebarBadges() {
  const [badges, setBadges] = useState<SidebarBadges | null>(null);
  const [isLoading, setIsLoading] = useState<boolean>(false);
  const isAuthenticated = useAuthStore((state) => state.isAuthenticated);
  const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  const fetchBadges = useCallback(async () => {
    if (!isAuthenticated || document.hidden) {
      return;
    }

    try {
      setIsLoading(true);
      const data = await sidebarService.getBadges();
      setBadges(data);
    } catch {
      // Silent on failure as required
    } finally {
      setIsLoading(false);
    }
  }, [isAuthenticated]);

  useEffect(() => {
    if (!isAuthenticated) {
      setBadges(null);
      return;
    }

    // Initial fetch
    fetchBadges();

    // Set interval for periodic polling
    timerRef.current = setInterval(() => {
      fetchBadges();
    }, POLL_INTERVAL_MS);

    // Pause when tab is hidden, resume immediately when tab becomes visible
    const handleVisibilityChange = () => {
      if (!document.hidden) {
        fetchBadges();
      }
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      if (timerRef.current) {
        clearInterval(timerRef.current);
      }
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, [isAuthenticated, fetchBadges]);

  return {
    badges,
    isLoading,
    refresh: fetchBadges,
  };
}
