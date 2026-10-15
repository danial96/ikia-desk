import { ApiError, NetworkError } from '@/api/client';

/** A sentence to show a person for any failure. */
export function errorMessage(e: unknown): string {
  if (e instanceof NetworkError) return e.message;
  if (e instanceof ApiError) {
    if (e.status === 403) return e.message && e.message !== 'This action is unauthorized.' ? e.message : 'You do not have permission to do that.';
    if (e.status === 429 && e.retryAfter) return `Too many attempts. Try again in ${e.retryAfter} seconds.`;
    if (e.status >= 500) return 'The server had a problem. Please try again in a moment.';
    return e.message;
  }
  return 'Something went wrong. Please try again.';
}
