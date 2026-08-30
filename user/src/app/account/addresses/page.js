'use client';

const addresses = [
  { id: 1, label: 'Office', name: 'Electava HQ', line1: '42 MG Road, Suite 301', city: 'Bengaluru', state: 'Karnataka', zip: '560001', country: 'India', phone: '+91-98765-43210', isDefault: true },
  { id: 2, label: 'Warehouse', name: 'Electava Warehouse', line1: '15 Industrial Area, Phase 2', city: 'Pune', state: 'Maharashtra', zip: '411018', country: 'India', phone: '+91-87654-32100', isDefault: false },
];

export default function AddressesPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Addresses</h2>
        <p>Manage your shipping and billing addresses.</p>
      </div>

      <div className="acct-actions" style={{ marginBottom: 24 }}>
        <button className="acct-btn acct-btn-primary">+ Add New Address</button>
      </div>

      <div className="acct-card-grid">
        {addresses.map(addr => (
          <div key={addr.id} className="acct-card">
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
              <h3 style={{ margin: 0 }}>{addr.label}</h3>
              {addr.isDefault && <span className="acct-status acct-status--active">Default</span>}
            </div>
            <p style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{addr.name}</p>
            <p>{addr.line1}</p>
            <p>{addr.city}, {addr.state} {addr.zip}</p>
            <p>{addr.country}</p>
            <p>Phone: {addr.phone}</p>
            <div className="acct-card-actions">
              <button className="acct-btn acct-btn-secondary">Edit</button>
              {!addr.isDefault && <button className="acct-btn acct-btn-secondary">Set as Default</button>}
              <button className="acct-btn acct-btn-danger">Delete</button>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
