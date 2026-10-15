import type { ReactNode } from 'react';
import { LinearGradient } from 'expo-linear-gradient';

import { brandColors } from '@/theme';

/** The website's teal gradient, behind the sign-in and update screens. */
export function Backdrop({ children }: { children: ReactNode }) {
  return (
    <LinearGradient colors={[brandColors.deep, brandColors.main]} start={{ x: 0.1, y: 0 }} end={{ x: 0.9, y: 1 }} style={{ flex: 1 }}>
      {children}
    </LinearGradient>
  );
}
