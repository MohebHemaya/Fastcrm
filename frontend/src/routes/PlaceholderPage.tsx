import React from 'react';
import { WrenchScrewdriverIcon } from '@/components/icons';
import PageHeader from '@/components/ui/PageHeader';
import EmptyState from '@/components/ui/EmptyState';
import { ar } from '@/i18n/ar';

export interface PlaceholderPageProps {
  title: string;
  description?: string;
}

export const PlaceholderPage: React.FC<PlaceholderPageProps> = ({
  title,
  description = ar.common.underDevelopment,
}) => {
  return (
    <div>
      <PageHeader
        title={title}
        breadcrumbs={[
          { label: ar.nav.dashboard, href: '/' },
          { label: title },
        ]}
      />

      <EmptyState
        icon={<WrenchScrewdriverIcon className="h-8 w-8 text-warning" />}
        title={ar.common.comingSoon}
        description={description}
      />
    </div>
  );
};

export default PlaceholderPage;
