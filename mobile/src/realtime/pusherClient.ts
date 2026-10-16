import PusherImport from 'pusher-js/react-native';
import type PusherType from 'pusher-js';

type Ctor = new (key: string, options: Record<string, unknown>) => PusherType;

/**
 * pusher-js ships one bundle that React Native's bundler hands back wrapped ({ default: Pusher }) rather than as the
 * constructor itself — calling `new` on the wrapper threw "Object cannot be used as a constructor". Accept either shape.
 */
export function pusherConstructor(mod: unknown): Ctor {
  const m = mod as { default?: unknown };
  const candidate = typeof mod === 'function' ? mod : m?.default;
  if (typeof candidate !== 'function') throw new Error('pusher-js could not be loaded');
  return candidate as Ctor;
}

export function createPusher(key: string, options: Record<string, unknown>): PusherType {
  const Pusher = pusherConstructor(PusherImport);
  return new Pusher(key, options);
}
