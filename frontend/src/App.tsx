import React, { useEffect } from 'react';
import { RouterProvider } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import { router } from './routes';
import { useAuthStore } from './lib/stores/useAuthStore';

export const App: React.FC = () => {
  const checkAuth = useAuthStore((state) => state.checkAuth);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  return (
    <HeroUIProvider locale="ar-EG">
      <div dir="rtl" className="min-h-[100dvh] bg-background font-sans antialiased text-foreground">
        <RouterProvider router={router} />
      </div>
    </HeroUIProvider>
  );
};

export default App;
