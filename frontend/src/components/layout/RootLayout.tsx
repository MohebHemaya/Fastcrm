import React from 'react';
import { Outlet, Link } from 'react-router-dom';
import { HeroUIProvider } from '@heroui/react';
import ToastContainer from '@/components/ui/ToastContainer';
import ar from '@/i18n/ar';
import { ShieldCheckIcon, CircleStackIcon } from '@/components/icons';

export const RootLayout: React.FC = () => {

  return (
    <HeroUIProvider locale="ar-EG">
      <div className="min-h-screen bg-slate-50 text-slate-800 flex flex-col font-sans" dir="rtl">
        {/* Navigation Bar */}
        <header className="sticky top-0 z-40 bg-white/90 backdrop-blur border-b border-slate-200">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-primary-600 text-white flex items-center justify-center font-bold text-xl shadow-md">
                ف
              </div>
              <div>
                <Link to="/" className="font-bold text-lg text-slate-900 block leading-tight hover:text-primary-600 transition-colors">
                  {ar.app.title}
                </Link>
                <span className="text-xs text-slate-500 font-medium">{ar.app.subtitle}</span>
              </div>
            </div>

            <div className="flex items-center gap-4">
              <div className="flex items-center gap-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full">
                <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>المرحلة 0: البنية التحتية</span>
              </div>
            </div>
          </div>
        </header>

        {/* Main Content Area */}
        <main className="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <Outlet />
        </main>

        {/* Footer */}
        <footer className="bg-white border-t border-slate-200 py-4 text-center text-xs text-slate-500">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
            <span>{ar.app.title} &copy; {new Date().getFullYear()}</span>
            <div className="flex items-center gap-4">
              <span className="inline-flex items-center gap-1">
                <ShieldCheckIcon className="w-3.5 h-3.5 text-primary-600" />
                <span>اتصال آمن بملفات تعريف الارتباط (Sanctum SPA)</span>
              </span>
              <span className="inline-flex items-center gap-1">
                <CircleStackIcon className="w-3.5 h-3.5 text-emerald-600" />
                <span>قاعدة بيانات MariaDB/MySQL</span>
              </span>
            </div>
          </div>
        </footer>

        {/* Global Toasts */}
        <ToastContainer />
      </div>
    </HeroUIProvider>
  );
};

export default RootLayout;
