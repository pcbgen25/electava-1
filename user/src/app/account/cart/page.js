'use client';

import Link from 'next/link';

const savedCarts = [
  { id: 1, name: 'Power Supply Components', items: 12, modified: '2026-08-15', subtotal: '₹8,450.00' },
  { id: 2, name: 'Sensor Module Build', items: 8, modified: '2026-08-10', subtotal: '₹5,220.00' },
  { id: 3, name: 'PCB Prototype Parts', items: 24, modified: '2026-07-28', subtotal: '₹15,870.00' },
];

export default function CartPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>My Carts</h2>
        <p>Manage your saved shopping carts and continue where you left off.</p>
      </div>

      <div className="acct-card-grid">
        {savedCarts.map(cart => (
          <div key={cart.id} className="acct-card">
            <h3>{cart.name}</h3>
            <p>{cart.items} items · Last modified {cart.modified}</p>
            <p style={{ color: 'var(--text-primary)', fontWeight: 600, marginTop: 8 }}>{cart.subtotal}</p>
            <div className="acct-card-actions">
              <Link href="#" className="acct-btn acct-btn-primary">View Cart</Link>
              <button className="acct-btn acct-btn-danger">Delete</button>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
