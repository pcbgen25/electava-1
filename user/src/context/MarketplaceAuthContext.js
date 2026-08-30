'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const USER_KEY  = 'electava-marketplace-user';
const TOKEN_KEY = 'electava-marketplace-token';
const MarketplaceAuthContext = createContext(null);

function buildUserProfile(payload) {
  const firstName = (payload.firstName || '').trim() || '';
  const lastName  = (payload.lastName  || '').trim() || '';

  return {
    id:          payload.id   || null,
    firstName,
    lastName,
    fullName:    payload.fullName || `${firstName} ${lastName}`.trim(),
    email:       payload.email    || '',
    company:     payload.company  || '',
    phone:       payload.phone    || '',
    title:       payload.title    || 'Marketplace User',
    role:        payload.role     || 'marketplace_user',
    memberSince: payload.memberSince || null,
  };
}

export function MarketplaceAuthProvider({ children }) {
  const [user,    setUser]    = useState(null);
  const [token,   setToken]   = useState(null);
  const [isReady, setIsReady] = useState(false);

  // Restore session from localStorage on mount
  useEffect(() => {
    try {
      const storedToken = window.localStorage.getItem(TOKEN_KEY);
      const storedUser  = window.localStorage.getItem(USER_KEY);
      if (storedToken && storedUser) {
        setToken(storedToken);
        setUser(buildUserProfile(JSON.parse(storedUser)));
      }
    } catch {
      // Corrupted storage — treat as logged out
      window.localStorage.removeItem(TOKEN_KEY);
      window.localStorage.removeItem(USER_KEY);
    } finally {
      setIsReady(true);
    }
  }, []);

  const persist = useCallback((profile, jwtToken) => {
    const next = buildUserProfile(profile);
    setUser(next);
    setToken(jwtToken);
    try {
      window.localStorage.setItem(USER_KEY,  JSON.stringify(next));
      window.localStorage.setItem(TOKEN_KEY, jwtToken);
    } catch {
      console.error('Unable to save session.');
    }
    return next;
  }, []);

  const signIn = useCallback(({ user: profile, token: jwtToken }) => {
    return persist(profile, jwtToken);
  }, [persist]);

  const registerUser = useCallback(({ user: profile, token: jwtToken }) => {
    return persist(profile, jwtToken);
  }, [persist]);

  const updateProfile = useCallback((patch) => {
    if (!user) return;
    const updated = buildUserProfile({ ...user, ...patch });
    setUser(updated);
    try {
      window.localStorage.setItem(USER_KEY, JSON.stringify(updated));
    } catch { /* ignore */ }
    return updated;
  }, [user]);

  const signOut = useCallback(() => {
    setUser(null);
    setToken(null);
    try {
      window.localStorage.removeItem(USER_KEY);
      window.localStorage.removeItem(TOKEN_KEY);
    } catch { /* ignore */ }
  }, []);

  /**
   * Get Authorization header for API requests.
   * Usage: fetch(url, { headers: getAuthHeaders() })
   */
  const getAuthHeaders = useCallback(() => {
    return token ? { Authorization: `Bearer ${token}` } : {};
  }, [token]);

  const value = useMemo(() => ({
    user,
    token,
    isReady,
    isAuthenticated: Boolean(user && token),
    signIn,
    registerUser,
    updateProfile,
    signOut,
    getAuthHeaders,
  }), [isReady, registerUser, signIn, signOut, updateProfile, user, token, getAuthHeaders]);

  return (
    <MarketplaceAuthContext.Provider value={value}>
      {children}
    </MarketplaceAuthContext.Provider>
  );
}

export function useMarketplaceAuth() {
  const context = useContext(MarketplaceAuthContext);
  if (!context) {
    throw new Error('useMarketplaceAuth must be used within a MarketplaceAuthProvider');
  }
  return context;
}
