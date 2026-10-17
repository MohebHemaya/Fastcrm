import React, { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, Spinner, Chip, Button } from '@heroui/react';
import {
  ClockIcon,
  ArrowPathIcon,
  UserCircleIcon,
  ShieldCheckIcon,
  ExclamationTriangleIcon,
  ArrowsUpDownIcon,
  CheckCircleIcon,
} from '@/components/icons';
import { workOrderService } from '@/services/workOrderService';
import type { WorkOrderEvent } from '@/types/workOrder';
import ar from '@/i18n/ar';

interface OrderTimelineTabProps {
  orderId: number;
}

export const OrderTimelineTab: React.FC<OrderTimelineTabProps> = ({ orderId }) => {
  const [events, setEvents] = useState<WorkOrderEvent[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchEvents = useCallback(async () => {
    setIsLoading(true);
    try {
      const res = await workOrderService.getEvents(orderId);
      setEvents(res.data || []);
    } catch (err) {
      console.error('Failed to load work order events', err);
      setEvents([]);
    } finally {
      setIsLoading(false);
    }
  }, [orderId]);

  useEffect(() => {
    fetchEvents();
  }, [fetchEvents]);

  const getActionColor = (action: string): 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger' => {
    switch (action) {
      case 'backup_promoted':
        return 'success';
      case 'confirmed_removed':
      case 'driver_cancelled':
      case 'order_cancelled':
        return 'danger';
      case 'backup_removed':
        return 'warning';
      case 'driver_added_manually':
        return 'primary';
      case 'offers_resent':
        return 'secondary';
      case 'outcomes_recorded':
      case 'outcome_recorded':
        return 'primary';
      default:
        return 'default';
    }
  };

  const getActionIcon = (action: string) => {
    switch (action) {
      case 'backup_promoted':
        return <ShieldCheckIcon className="w-4 h-4 text-success" />;
      case 'confirmed_removed':
      case 'driver_cancelled':
      case 'backup_removed':
        return <ExclamationTriangleIcon className="w-4 h-4 text-danger" />;
      case 'status_changed':
        return <ArrowsUpDownIcon className="w-4 h-4 text-secondary" />;
      case 'outcomes_recorded':
        return <CheckCircleIcon className="w-4 h-4 text-warning" />;
      default:
        return <ClockIcon className="w-4 h-4 text-default-500" />;
    }
  };

  if (isLoading) {
    return (
      <div className="flex justify-center items-center py-12">
        <Spinner size="md" label="جاري تحميل سجل الأحداث..." />
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-base font-bold text-foreground">
          {ar.workOrders.timeline.title} ({events.length})
        </h3>
        <Button
          size="sm"
          variant="light"
          startContent={<ArrowPathIcon className="w-3.5 h-3.5" />}
          onPress={fetchEvents}
          onClick={fetchEvents}
        >
          {ar.common.refresh}
        </Button>
      </div>

      {events.length === 0 ? (
        <div className="text-center py-12 text-default-500 space-y-2">
          <ClockIcon className="w-6 h-6 mx-auto text-default-400" />
          <p>{ar.workOrders.timeline.empty}</p>
        </div>
      ) : (
        <div className="relative border-s-2 border-default-200 ms-4 space-y-4 py-2">
          {events.map((event) => (
            <div key={event.id} className="relative ps-6">
              {/* Timeline dot */}
              <div className="absolute -start-[9px] top-1.5 w-4 h-4 rounded-full bg-background border-2 border-primary flex items-center justify-center" />

              <Card className="border border-default-200 shadow-none bg-default-50/50">
                <CardBody className="p-3.5 space-y-2 text-start">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                      {getActionIcon(event.action)}
                      <Chip size="sm" variant="flat" color={getActionColor(event.action)}>
                        {event.action}
                      </Chip>
                    </div>
                    <span dir="ltr" className="text-xs text-default-400 font-mono">
                      {new Date(event.created_at).toLocaleString('ar-EG', {
                        year: 'numeric',
                        month: 'short',
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </span>
                  </div>

                  <p className="text-sm font-medium text-foreground">
                    {event.description}
                  </p>

                  <div className="flex flex-wrap items-center gap-4 text-xs text-default-500 pt-1 border-t border-default-100">
                    {event.user_name && (
                      <span className="flex items-center gap-1">
                        <UserCircleIcon className="w-3.5 h-3.5" />
                        <span>{ar.workOrders.timeline.actor}:</span>
                        <strong className="text-default-700">{event.user_name}</strong>
                      </span>
                    )}

                    {event.driver_name && (
                      <span className="flex items-center gap-1">
                        <ShieldCheckIcon className="w-3.5 h-3.5" />
                        <span>{ar.workOrders.timeline.driver}:</span>
                        <strong className="text-default-700">{event.driver_name}</strong>
                      </span>
                    )}
                  </div>

                  {event.metadata && Object.keys(event.metadata).length > 0 && (
                    <div className="mt-2 text-xs bg-default-100 p-2 rounded text-default-600 font-mono whitespace-pre-wrap">
                      {JSON.stringify(event.metadata, null, 2)}
                    </div>
                  )}
                </CardBody>
              </Card>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default OrderTimelineTab;
