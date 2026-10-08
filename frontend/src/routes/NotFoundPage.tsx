import React from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@heroui/react';
import ar from '@/i18n/ar';
import { HomeIcon, ExclamationCircleIcon } from '@/components/icons';

export const NotFoundPage: React.FC = () => {
  return (
    <div className="min-h-[60vh] flex flex-col items-center justify-center text-center ps-4 pe-4">
      <div className="p-4 bg-rose-50 text-rose-600 rounded-3xl mb-4">
        <ExclamationCircleIcon className="w-12 h-12" />
      </div>
      <h1 className="text-4xl font-extrabold text-slate-900 mb-2">404</h1>
      <h2 className="text-xl font-bold text-slate-800 mb-2">{ar.errors.notFoundTitle}</h2>
      <p className="text-slate-500 max-w-md mb-8 text-sm">{ar.errors.notFoundDesc}</p>
      <Button
        as={Link}
        to="/"
        color="primary"
        variant="solid"
        className="font-bold min-h-[44px]"
        startContent={<HomeIcon className="w-4 h-4" />}
      >
        {ar.errors.backHome}
      </Button>
    </div>
  );
};

export default NotFoundPage;
