import { router } from 'expo-router';

/** Back when there is somewhere to go back to (a page opened from a link has no history), otherwise the task list. */
export function goBack() {
  if (router.canGoBack()) router.back();
  else router.replace('/tasks');
}
