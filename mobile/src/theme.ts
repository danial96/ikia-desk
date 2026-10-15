import { useColorScheme } from 'react-native';

// Colours follow the website's teal theme (resources/views/layouts/app.blade.php, App\Support\Themes).
const brand = {
  deep: '#04252b',
  main: '#0b6b73',
  accent: '#3fd0c9',
};

const light = {
  bg: '#f3f6f7',
  card: '#ffffff',
  text: '#10242a',
  subtext: '#5b7077',
  border: '#dbe4e7',
  primary: brand.main,
  onPrimary: '#ffffff',
  danger: '#c0392b',
  warning: '#b7791f',
  success: '#2f855a',
  inputBg: '#ffffff',
};

const dark: typeof light = {
  bg: '#061a1f',
  card: '#0d2a31',
  text: '#e8f2f3',
  subtext: '#8fa9ae',
  border: '#16424b',
  primary: brand.accent,
  onPrimary: '#04252b',
  danger: '#ff7b6b',
  warning: '#f0b955',
  success: '#6fd39f',
  inputBg: '#0a2329',
};

export type Colors = typeof light;

export function useColors(): Colors {
  return useColorScheme() === 'dark' ? dark : light;
}

export const brandColors = brand;

export const priorityColor = (c: Colors, priority: string) =>
  priority === 'urgent' ? c.danger : priority === 'high' ? c.warning : priority === 'medium' ? c.primary : c.subtext;

export const spacing = { xs: 4, sm: 8, md: 16, lg: 24, xl: 32 };
