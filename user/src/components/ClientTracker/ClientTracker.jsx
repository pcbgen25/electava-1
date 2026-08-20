'use client';

import { useEffect, useRef } from 'react';
import { usePathname } from 'next/navigation';

export default function ClientTracker() {
  const pathname = usePathname();
  const trackedPages = useRef(new Set());

  useEffect(() => {
    // Generate a simple session ID if none exists
    let sessionId = sessionStorage.getItem('electava_session');
    if (!sessionId) {
      sessionId = 'S-' + Math.random().toString(36).substring(2, 10);
      sessionStorage.setItem('electava_session', sessionId);
    }

    if (!trackedPages.current.has(pathname)) {
      // Small delay to ensure render
      setTimeout(() => {
        fetch('http://localhost:5000/api/tracking', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            sessionId: sessionId,
            deviceType: window.innerWidth < 768 ? 'Mobile' : 'Desktop',
            browser: navigator.userAgent.includes('Chrome') ? 'Chrome' : 
                     navigator.userAgent.includes('Firefox') ? 'Firefox' : 
                     navigator.userAgent.includes('Safari') ? 'Safari' : 'Other',
            pageVisited: pathname
          })
        }).catch(err => console.debug('Tracking blocked/failed'));
      }, 1000);
      trackedPages.current.add(pathname);
    }
  }, [pathname]);

  return null;
}
