'use client';

import { useState } from 'react';

const initialPrefs = [
  { id: 'order_confirm', label: 'Order Confirmations', desc: 'Receive email when an order is placed or confirmed.', on: true },
  { id: 'shipping', label: 'Shipping Updates', desc: 'Get notified when your order ships or is delivered.', on: true },
  { id: 'price_drop', label: 'Price Drop Alerts', desc: 'Be alerted when watched components drop in price.', on: true },
  { id: 'new_products', label: 'New Product Announcements', desc: 'Hear about newly added components and manufacturers.', on: false },
  { id: 'newsletter', label: 'Weekly Newsletter', desc: 'Industry insights, sourcing tips, and Electava updates.', on: false },
  { id: 'promo', label: 'Promotional Offers', desc: 'Special discounts and limited-time deals.', on: false },
];

export default function EmailSettingsPage() {
  const [prefs, setPrefs] = useState(initialPrefs);

  function toggle(id) {
    setPrefs(prev => prev.map(p => p.id === id ? { ...p, on: !p.on } : p));
  }

  return (
    <>
      <div className="acct-page-header">
        <h2>Email Settings</h2>
        <p>Control which emails you receive from Electava.</p>
      </div>

      <div className="account-box">
        {prefs.map(p => (
          <div key={p.id} className="acct-toggle-row">
            <div className="acct-toggle-label">
              <h4>{p.label}</h4>
              <p>{p.desc}</p>
            </div>
            <label className="acct-toggle">
              <input type="checkbox" checked={p.on} onChange={() => toggle(p.id)} />
              <span className="acct-toggle-track" />
            </label>
          </div>
        ))}

        <div className="acct-actions" style={{ marginTop: 24 }}>
          <button className="acct-btn acct-btn-primary">Save Preferences</button>
        </div>
      </div>
    </>
  );
}
