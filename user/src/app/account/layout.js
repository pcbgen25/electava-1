'use client';

import Link from 'next/link';
import { useRouter, usePathname } from 'next/navigation';
import { useEffect } from 'react';
import { FiLogOut } from 'react-icons/fi';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';
import './account.css';
import './account-subpages.css';

const accountLinks = [
  {
    title: 'Orders and Lists',
    links: [
      { label: 'My Orders', href: '/account/orders' },
      { label: 'My Carts', href: '/account/cart' },
      { label: 'My Lists', href: '/account/lists' },
      { label: 'Quotes', href: '/account/quotes' },
      { label: 'Product Notifications', href: '/account/notifications' },
      { label: 'Returns and Order Issues', href: '/account/returns' },
    ],
  },
  {
    title: 'Account & Settings',
    links: [
      { label: 'Profile', href: '/account/profile' },
      { label: 'Email Settings', href: '/account/email-settings' },
      { label: 'Login & Security', href: '/account/security' },
      { label: 'Addresses', href: '/account/addresses' },
      { label: 'Payment Information', href: '/account/payment' },
    ],
  },
  {
    title: 'Account and Reports',
    links: [
      { label: 'Lead Time Trends', href: '/account/lead-time-trends' },
      { label: 'Order History', href: '/account/order-history' },
      { label: 'Purchase Reports', href: '/account/purchase-reports' },
    ],
  },
];

function buildAccountNumbers(user) {
  const numericId = Number(user.id) || 17270745;
  const customerNumber = String(17270000 + numericId).slice(0, 8);
  const accountNumber = String(17200000 + numericId).slice(0, 8);
  return { customerNumber, accountNumber };
}

export default function AccountLayout({ children }) {
  const router = useRouter();
  const pathname = usePathname();
  const { user, isReady, isAuthenticated, signOut } = useMarketplaceAuth();

  useEffect(() => {
    if (isReady && !isAuthenticated) {
      router.replace('/login');
    }
  }, [isAuthenticated, isReady, router]);

  if (!isReady || !user) {
    return (
      <div className="account-page account-portal-page">
        <div className="container">
          <p className="account-loading">Loading your account...</p>
        </div>
      </div>
    );
  }

  const { customerNumber, accountNumber } = buildAccountNumbers(user);
  const displayName = user.company && user.company !== 'Electava Marketplace' ? user.company : user.fullName;

  function handleSignOut() {
    signOut();
    router.push('/login');
  }

  return (
    <div className="account-page account-portal-page">
      <div className="container account-portal-container">
        <aside className="account-sidebar-panel">
          <div className="account-customer-block">
            <h1>Hello, {displayName}</h1>
            <p>{user.email}</p>
            <p>Customer Number: {customerNumber}</p>
            <p>Account Number: {accountNumber}</p>
          </div>

          <nav className="account-menu">
            <section>
              <Link href="/account" className={pathname === '/account' ? 'active' : ''}>
                Dashboard
              </Link>
            </section>
            {accountLinks.map(group => (
              <section key={group.title}>
                <h2>{group.title}</h2>
                {group.links.map(item => (
                  <Link
                    key={item.href}
                    href={item.href}
                    className={pathname === item.href ? 'active' : ''}
                  >
                    {item.label}
                  </Link>
                ))}
              </section>
            ))}
          </nav>

          <button type="button" className="account-signout-link" onClick={handleSignOut}>
            <FiLogOut /> Sign Out
          </button>
        </aside>

        <main className="account-main-panel">
          {children}
        </main>
      </div>
    </div>
  );
}
