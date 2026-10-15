import { Redirect } from 'expo-router';

// Signed-in people land on their tasks.
export default function Index() {
  return <Redirect href="/tasks" />;
}
