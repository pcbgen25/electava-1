'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { getApiUrl, authFetch } from '@/lib/api';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';

export default function AccountDashboard() {
  const [recentOrders, setRecentOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const { signOut } = useMarketplaceAuth();

  useEffect(() => {
    const fetchOrders = async () => {
      try {
        const token = window.localStorage.getItem('electava-marketplace-token');
        if (!token) { setLoading(false); return; }

        const res = await authFetch(getApiUrl('/account/orders'), {}, signOut);
        if (!res.ok) { setLoading(false); return; }
        const data = await res.json();
        // show only the latest 3 orders on the dashboard
        setRecentOrders(data.slice(0, 3));
      } catch (err) {
        console.error(err);
      } finally {
        setLoading(false);
      }
    };

    fetchOrders();
  }, [signOut]);


  return (
    <>
      <section className="account-box recent-orders-box">
        <div className="account-box-header">
          <h2>Recent Orders</h2>
        </div>

        <div className="account-orders-table">
          {loading ? (
            <p>Loading orders...</p>
          ) : (
            <table>
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Order Number</th>
                  <th>Items</th>
                  <th>Status</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                {recentOrders.map(order => (
                  <tr key={order.id}>
                    <td>{order.date}</td>
                    <td>{order.id}</td>
                    <td>{order.itemsCount}</td>
                    <td><span className={`acct-status acct-status--${order.status.toLowerCase()}`}>{order.status}</span></td>
                    <td>${order.total.toFixed(2)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>

        <div className="account-orders-actions">
          <Link href="/account/orders" className="account-primary-button">View All Orders</Link>
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
    </>
  );
}
