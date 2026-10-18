/**
 * Lightweight in-memory SWR (Stale-While-Revalidate) cache.
 * Provides instant 0ms responses on subsequent page visits and revalidates in background.
 */

interface CacheEntry<T> {
  data: T;
  timestamp: number;
  ttlMs: number;
}

class MemoryCache {
  private cache = new Map<string, CacheEntry<unknown>>();

  /**
   * Set cache item with time-to-live in milliseconds (default: 60 seconds)
   */
  set<T>(key: string, data: T, ttlMs: number = 60_000): void {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      ttlMs,
    });
  }

  /**
   * Get cached item if it exists and is still within TTL
   */
  get<T>(key: string): T | undefined {
    const entry = this.cache.get(key) as CacheEntry<T> | undefined;
    if (!entry) return undefined;

    const isExpired = Date.now() - entry.timestamp > entry.ttlMs;
    if (isExpired) {
      // Keep entry for SWR stale access, but marked expired
      return undefined;
    }
    return entry.data;
  }

  /**
   * Get stale item even if expired (for instantaneous render while background fetch runs)
   */
  getStale<T>(key: string): T | undefined {
    const entry = this.cache.get(key) as CacheEntry<T> | undefined;
    return entry ? entry.data : undefined;
  }

  /**
   * Invalidate exact key or keys starting with a prefix
   */
  invalidate(keyOrPrefix: string): void {
    for (const key of this.cache.keys()) {
      if (key === keyOrPrefix || key.startsWith(keyOrPrefix)) {
        this.cache.delete(key);
      }
    }
  }

  /**
   * Clear all cache
   */
  clear(): void {
    this.cache.clear();
  }

  /**
   * Fetch with Stale-While-Revalidate pattern:
   * Returns stale data immediately if available, then updates in background.
   */
  async swr<T>(
    key: string,
    fetcher: () => Promise<T>,
    options?: {
      ttlMs?: number;
      onRevalidate?: (freshData: T) => void;
    }
  ): Promise<{ data: T; fromCache: boolean }> {
    const ttlMs = options?.ttlMs ?? 60_000;
    const staleData = this.getStale<T>(key);
    const validData = this.get<T>(key);

    if (validData !== undefined) {
      return { data: validData, fromCache: true };
    }

    if (staleData !== undefined) {
      // Background revalidation without blocking caller
      fetcher()
        .then((fresh) => {
          this.set(key, fresh, ttlMs);
          options?.onRevalidate?.(fresh);
        })
        .catch(() => {
          // Ignore background fetch error, stale data is already shown
        });

      return { data: staleData, fromCache: true };
    }

    // Nothing in cache, fetch synchronously
    const fresh = await fetcher();
    this.set(key, fresh, ttlMs);
    return { data: fresh, fromCache: false };
  }
}

export const appCache = new MemoryCache();
export default appCache;
