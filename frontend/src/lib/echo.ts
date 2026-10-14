import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

// Make Pusher globally available for laravel-echo
(window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

const pusherKey = (import.meta.env.VITE_PUSHER_APP_KEY as string) || 'c4ed800f3a4fcf8dff40';
const pusherCluster = (import.meta.env.VITE_PUSHER_APP_CLUSTER as string) || 'eu';

/**
 * Dedicated Axios client for broadcasting authorization.
 * Intentionally does NOT share interceptors with the main app client
 * so that any 401 or WebSocket authorization failure never logs the user out.
 */
const broadcastAuthClient = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  xsrfCookieName: 'XSRF-TOKEN',
  xsrfHeaderName: 'X-XSRF-TOKEN',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
});

/**
 * Singleton Laravel Echo instance connected to Pusher Channels.
 * Authorizes private channels via Sanctum-authenticated /api/v1/broadcasting/auth.
 */
export const echo = new Echo({
  broadcaster: 'pusher',
  key: pusherKey,
  cluster: pusherCluster,
  forceTLS: true,
  authorizer: (channel: { name: string }) => {
    return {
      authorize: (socketId: string, callback: (error: Error | null, authData: any) => void) => {
        broadcastAuthClient
          .post('/broadcasting/auth', {
            socket_id: socketId,
            channel_name: channel.name,
          })
          .then((response) => {
            callback(null, response.data);
          })
          .catch((error) => {
            console.warn('[Echo] Authorization failed for channel:', channel.name, error?.message);
            callback(error, null);
          });
      },
    };
  },
});

export default echo;
