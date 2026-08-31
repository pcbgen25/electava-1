'use client';

import { useState, useMemo, useEffect } from 'react';
import Link from 'next/link';
import { FiSearch, FiPackage, FiX } from 'react-icons/fi';
import { getApiUrl, authFetch } from '@/lib/api';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';

export default function MyOrdersPage() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [statusFilter, setStatusFilter] = useState('All');
  const [searchQuery, setSearchQuery] = useState('');
  const [startDate, setStartDate] = useState('');
  const [endDate, setEndDate] = useState('');
  const { signOut } = useMarketplaceAuth();

  useEffect(() => {
    const fetchOrders = async () => {
      try {
        const token = window.localStorage.getItem('electava-marketplace-token');
        if (!token) { setLoading(false); return; }

        const res = await authFetch(getApiUrl('/account/orders'), {}, signOut);
        if (res.status === 401 || res.status === 403) {
          setError('Session expired. Please sign in again.');
          setLoading(false);
          return;
        }
        if (!res.ok) throw new Error('Failed to fetch orders');
        const data = await res.json();
        setOrders(data);
      } catch (err) {
        console.error(err);
        setError('Failed to load orders.');
      } finally {
        setLoading(false);
      }
    };
    fetchOrders();
  }, [signOut]);



  const filteredOrders = useMemo(() => {
    return orders.filter((order) => {
      // Status filter
      if (statusFilter !== 'All' && order.status.toLowerCase() !== statusFilter.toLowerCase()) {
        return false;
      }

      // Search query (Order # or Items)
      if (searchQuery.trim()) {
        const query = searchQuery.toLowerCase();
        const matchesId = order.id.toLowerCase().includes(query);
        const matchesItems = order.itemsSummary.toLowerCase().includes(query);
        const matchesTracking = order.trackingNumber && order.trackingNumber.toLowerCase().includes(query);
        if (!matchesId && !matchesItems && !matchesTracking) {
          return false;
        }
      }

      // Date range filter
      if (startDate && order.date < startDate) {
        return false;
      }
      if (endDate && order.date > endDate) {
        return false;
      }

      return true;
    });
  }, [orders, statusFilter, searchQuery, startDate, endDate]);

  const hasActiveFilters = statusFilter !== 'All' || searchQuery !== '' || startDate !== '' || endDate !== '';

  const handleResetFilters = () => {
    setStatusFilter('All');
    setSearchQuery('');
    setStartDate('');
    setEndDate('');
  };

  return (
    <section className="account-box">
      <div className="acct-page-header">
        <h2>My Orders</h2>
        <p>Track, manage, and view the status of your recent electronic component orders.</p>
      </div>

      {/* Filter Bar */}
      <div className="acct-filter-bar">
        <div style={{ position: 'relative', display: 'inline-flex', alignItems: 'center' }}>
          <input
            type="text"
            placeholder="Search by Order #, parts..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            style={{ minWidth: '220px', paddingLeft: '32px' }}
          />
          <FiSearch style={{ position: 'absolute', left: '10px', color: 'var(--text-tertiary)', pointerEvents: 'none' }} />
        </div>

        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          aria-label="Filter by order status"
        >
          <option value="All">All Statuses</option>
          <option value="Processing">Processing</option>
          <option value="Shipped">Shipped</option>
          <option value="Delivered">Delivered</option>
          <option value="Cancelled">Cancelled</option>
        </select>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <label style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>From:</label>
          <input
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            aria-label="Start date"
          />
        </div>

        <div style={{ display: 'flex', alignItems: 'center', gap: '8px' }}>
          <label style={{ fontSize: '0.82rem', color: 'var(--text-secondary)' }}>To:</label>
          <input
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            aria-label="End date"
          />
        </div>

        {hasActiveFilters && (
          <button
            type="button"
            className="acct-btn acct-btn-secondary"
            onClick={handleResetFilters}
            style={{ padding: '7px 14px', fontSize: '0.82rem' }}
          >
            <FiX /> Clear Filters
          </button>
        )}
      </div>

      {/* Orders Table */}
      {filteredOrders.length > 0 ? (
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Items</th>
                <th>Total</th>
                <th>Status</th>
                <th>Tracking</th>
              </tr>
            </thead>
            <tbody>
              {filteredOrders.map((order) => {
                const statusClass = `acct-status--${order.status.toLowerCase()}`;
                return (
                  <tr key={order.id}>
                    <td>
                      <Link href={`/account/orders?id=${order.id}`} title={`View details for ${order.id}`}>
                        {order.id}
                      </Link>
                    </td>
                    <td>{order.date}</td>
                    <td>
                      <div>
                        <strong>{order.itemsCount} {order.itemsCount === 1 ? 'item' : 'items'}</strong>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-secondary)', marginTop: '2px', maxWidth: '320px' }}>
                          {order.itemsSummary}
                        </div>
                      </div>
                    </td>
                    <td>
                      <strong>${order.total.toFixed(2)}</strong>
                    </td>
                    <td>
                      <span className={`acct-status ${statusClass}`}>
                        {order.status}
                      </span>
                    </td>
                    <td>
                      {order.trackingNumber ? (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '2px' }}>
                          <span style={{ fontFamily: 'var(--font-mono, monospace)', fontSize: '0.85rem' }}>
                            {order.trackingNumber}
                          </span>
                          <span style={{ fontSize: '0.75rem', color: 'var(--text-secondary)' }}>
                            {order.carrier}
                          </span>
                        </div>
                      ) : (
                        <span style={{ color: 'var(--text-tertiary)' }}>—</span>
                      )}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="acct-empty">
          <div className="acct-empty-icon">
            <FiPackage />
          </div>
          <h3>No orders found</h3>
          <p>We couldn&apos;t find any orders matching your selected criteria. Try adjusting your filters or date range.</p>
          {hasActiveFilters && (
            <button type="button" className="acct-btn acct-btn-secondary" onClick={handleResetFilters}>
              Reset Filters
            </button>
          )}
        </div>
      )}
    </section>
  );
}
