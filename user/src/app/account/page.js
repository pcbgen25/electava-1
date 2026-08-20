'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';
import { FiLogOut } from 'react-icons/fi';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';
import './account.css';

const accountLinks = [
  {
    title: 'Orders and Lists',
    links: ['My Orders', 'My Carts', 'My Lists', 'Quotes', 'Product Notifications', 'Returns and Order Issues'],
  },
  {
    title: 'Account & Settings',
    links: ['Profile', 'Email Settings', 'Login & Security', 'Addresses', 'Payment Information'],
  },
  {
    title: 'Account and Reports',
    links: ['Lead Time Trends', 'Order History', 'Purchase Reports'],
  },
];

const recentOrders = [
  { date: '2026-08-18', salesOrder: 'ELV-SO-10042', purchaseOrder: 'PO-LOCAL-7841', status: 'Processing', owner: 'Electava' },
  { date: '2026-08-12', salesOrder: 'ELV-SO-10029', purchaseOrder: 'PO-LOCAL-7718', status: 'Shipped', owner: 'Electava' },
];

function buildAccountNumbers(user) {
  const numericId = Number(user.id) || 17270745;
  const customerNumber = String(17270000 + numericId).slice(0, 8);
  const accountNumber = String(17200000 + numericId).slice(0, 8);

  return { customerNumber, accountNumber };
}

export default function AccountPage() {
  const router = useRouter();
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
      <div className="account-alert-bar">
        ATTENTION: Due to high order volume, orders may require an additional business day to process.
      </div>

      <div className="container account-portal-container">
        <aside className="account-sidebar-panel">
          <div className="account-customer-block">
            <h1>Hello, {displayName}</h1>
            <p>{user.email}</p>
            <p>Customer Number: {customerNumber}</p>
            <p>Account Number: {accountNumber}</p>
          </div>

          <nav className="account-menu">
            {accountLinks.map(group => (
              <section key={group.title}>
                <h2>{group.title}</h2>
                {group.links.map(item => (
                  <Link key={item} href="#">
                    {item}
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
          <section className="account-box recent-orders-box">
            <div className="account-box-header">
              <h2>Recent Orders</h2>
            </div>

            <div className="account-orders-table">
              <table>
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Sales Order Number</th>
                    <th>Purchase Order Number</th>
                    <th>Status</th>
                    <th>Owner</th>
                  </tr>
                </thead>
                <tbody>
                  {recentOrders.map(order => (
                    <tr key={order.salesOrder}>
                      <td>{order.date}</td>
                      <td>{order.salesOrder}</td>
                      <td>{order.purchaseOrder}</td>
                      <td>{order.status}</td>
                      <td>{order.owner}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="account-orders-actions">
              <Link href="#" className="account-primary-button">View All Orders</Link>
            </div>
          </section>

          <section className="account-box account-team-box">
            <h2>My Electava Team</h2>
            <p><strong>Electava Sales</strong></p>
            <p>Phone: +91-98765-43210</p>
            <p><a href="mailto:orders@electava.com">orders@electava.com</a></p>
          </section>

          <section className="business-account-card">
            <div>
              <p className="business-eyebrow">Business Account</p>
              <h2>Create a business account or connect to your company</h2>
              <Link href="#" className="account-primary-button">Get Started</Link>
            </div>
            <div className="business-photo" aria-hidden="true">
              <span>Electava</span>
            </div>
          </section>
        </main>
      </div>
    </div>
  );
}
