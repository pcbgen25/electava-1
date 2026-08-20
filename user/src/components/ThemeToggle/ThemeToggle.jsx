'use client';

import { useTheme } from 'next-themes';
import { useEffect, useState } from 'react';
import { FiSun, FiMoon } from 'react-icons/fi';

export default function ThemeToggle() {
  const { theme, setTheme } = useTheme();
  const [mounted, setMounted] = useState(false);

  useEffect(() => {
    setMounted(true);
  }, []);

  const icon = mounted && theme === 'dark' ? <FiSun size={14} /> : <FiMoon size={14} />;
  const label = mounted ? (theme === 'dark' ? 'Light Mode' : 'Dark Mode') : 'Dark / Light';

  return (
    <button
      onClick={() => mounted && setTheme(theme === 'dark' ? 'light' : 'dark')}
      aria-label="Toggle theme"
      style={{ display: 'flex', alignItems: 'center', gap: '4px', background: 'transparent', color: 'inherit', fontSize: 'inherit', cursor: 'pointer' }}
    >
      {icon}
      <span>{label}</span>
    </button>
  );
}
