'use client';

import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';

const STORAGE_KEY = 'electava-marketplace-user';
const MarketplaceAuthContext = createContext(null);

function buildUserProfile(payload) {
  const firstName = (payload.firstName || '').trim() || 'Marketplace';
  const lastName = (payload.lastName || '').trim() || 'User';

  return {
    id: payload.id || 'marketplace-demo-user',
    firstName,
    lastName,
    fullName: `${firstName} ${lastName}`.trim(),
    email: payload.email || 'demo@electava.com',
    company: payload.company || 'Electava Labs',
    phone: payload.phone || '+1 (555) 123-4567',
    title: payload.title || 'Procurement Engineer',
    memberSince: payload.memberSince || '2026-01-10',
  };
}

export function MarketplaceAuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [isReady, setIsReady] = useState(false);

  useEffect(() => {
    try {
      const stored = window.localStorage.getItem(STORAGE_KEY);
      if (stored) {
        setUser(buildUserProfile(JSON.parse(stored)));
      }
    } catch (error) {
      console.error('Unable to restore marketplace user session.', error);
    } finally {
      setIsReady(true);
    }
  }, []);

  const persistUser = useCallback((profile) => {
    const nextUser = buildUserProfile(profile);
    setUser(nextUser);
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(nextUser));
    } catch (error) {
      console.error('Unable to save marketplace user session.', error);
    }
    return nextUser;
  }, []);

  const signIn = useCallback((profile) => {
    return persistUser(profile);
  }, [persistUser]);

  const registerUser = useCallback((profile) => {
    return persistUser(profile);
  }, [persistUser]);

  const updateProfile = useCallback((patch) => {
    const current = user || buildUserProfile({});
    return persistUser({ ...current, ...patch });
  }, [persistUser, user]);

  const signOut = useCallback(() => {
    setUser(null);
    try {
      window.localStorage.removeItem(STORAGE_KEY);
    } catch (error) {
      console.error('Unable to clear marketplace user session.', error);
    }
  }, []);

  const value = useMemo(() => ({
    user,
    isReady,
    isAuthenticated: Boolean(user),
    signIn,
    registerUser,
    updateProfile,
    signOut,
  }), [isReady, registerUser, signIn, signOut, updateProfile, user]);

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
