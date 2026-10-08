import React from 'react';
import { useRouteError, isRouteErrorResponse, Link } from 'react-router-dom';
import { Button, Card, CardBody } from '@heroui/react';
import { ArrowPathIcon, ExclamationTriangleIcon, HomeIcon } from '@/components/icons';
import { ar } from '@/i18n/ar';

export const RouteErrorBoundary: React.FC = () => {
  const error = useRouteError();

  let errorMessage: string = ar.errors.generalError;
  let errorDetails = '';

  if (isRouteErrorResponse(error)) {
    errorMessage = `${error.status} - ${error.statusText || error.data?.message || 'خطأ في الصفحة'}`;
  } else if (error instanceof Error) {
    errorDetails = error.message;
  } else if (typeof error === 'string') {
    errorDetails = error;
  }

  return (
    <div className="min-h-[60vh] flex items-center justify-center p-4" dir="rtl">
      <Card className="max-w-md w-full border border-danger-200 bg-content1 shadow-md">
        <CardBody className="p-6 flex flex-col items-center text-center space-y-4">
          <div className="p-3 rounded-full bg-danger-50 text-danger">
            <ExclamationTriangleIcon className="h-8 w-8" />
          </div>

          <div className="space-y-1">
            <h2 className="text-lg font-bold text-default-900">
              حدث خطأ غير متوقع
            </h2>
            <p className="text-sm text-default-500">
              {errorMessage}
            </p>
            {errorDetails && (
              <p className="text-xs text-danger font-mono bg-danger-50/50 p-2 rounded-lg mt-2 text-start break-all" dir="ltr">
                {errorDetails}
              </p>
            )}
          </div>

          <div className="flex items-center gap-3 pt-2">
            <Button
              color="primary"
              variant="solid"
              startContent={<ArrowPathIcon className="h-4 w-4" />}
              onPress={() => window.location.reload()}
            >
              إعادة التحميل
            </Button>
            <Button
              as={Link}
              to="/"
              variant="flat"
              startContent={<HomeIcon className="h-4 w-4" />}
            >
              الرئيسية
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
};

export default RouteErrorBoundary;
