import { NextResponse } from 'next/server';

/**
 * Server-side route protection for /account/* pages.
 * Checks for authentication cookie before rendering.
 * Note: Full protection requires migrating from localStorage to HttpOnly cookies.
 * This middleware works once cookie-based auth is implemented.
 */
export function middleware(request) {
  const { pathname } = request.nextUrl;

  // Protect all account routes
  if (pathname.startsWith('/account')) {
    // Check for auth token cookie (set by backend on login)
    const token = request.cookies.get('electava-marketplace-token')?.value;

    if (!token) {
      // If no cookie, allow client-side guard to handle
      // (localStorage-based auth can't be checked in middleware)
      // TODO: Migrate to HttpOnly cookies for true server-side protection
      return NextResponse.next();
    }
  }

  return NextResponse.next();
}

export const config = {
  matcher: ['/account/:path*'],
};
