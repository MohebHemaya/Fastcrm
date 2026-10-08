import '@testing-library/jest-dom/vitest';
import { afterAll, afterEach, beforeAll } from 'vitest';
import { server } from './mocks/server';
import { resetMocks } from './mocks/handlers';

// Polyfill ResizeObserver for JSDOM / HeroUI Tabs
class MockResizeObserver {
  observe() {}
  unobserve() {}
  disconnect() {}
}
globalThis.ResizeObserver = MockResizeObserver;
if (typeof window !== 'undefined') {
  window.ResizeObserver = MockResizeObserver;
}

beforeAll(() => {
  server.listen({ onUnhandledRequest: 'error' });
});

afterEach(() => {
  server.resetHandlers();
  resetMocks();
});

afterAll(() => {
  server.close();
});
