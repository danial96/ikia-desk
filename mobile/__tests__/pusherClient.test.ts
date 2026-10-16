import { pusherConstructor } from '@/realtime/pusherClient';

jest.mock('pusher-js/react-native', () => ({}), { virtual: true });

describe('pusherConstructor()', () => {
  class Fake {}

  it('takes the constructor itself', () => {
    expect(pusherConstructor(Fake)).toBe(Fake);
  });

  it('unwraps the { default } shape React Native hands back', () => {
    expect(pusherConstructor({ default: Fake, __esModule: true })).toBe(Fake);
  });

  it('says so plainly when there is no constructor, instead of "Object cannot be used as a constructor"', () => {
    expect(() => pusherConstructor({})).toThrow('pusher-js could not be loaded');
    expect(() => pusherConstructor(undefined)).toThrow('pusher-js could not be loaded');
  });
});
