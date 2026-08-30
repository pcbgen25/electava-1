'use client';

const methods = [
  { id: 1, type: 'Visa', last4: '4821', expiry: '09/2028', holder: 'Electava Pvt Ltd', isDefault: true },
  { id: 2, type: 'Mastercard', last4: '7392', expiry: '03/2027', holder: 'Electava Pvt Ltd', isDefault: false },
];

export default function PaymentPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Payment Information</h2>
        <p>Manage your saved payment methods.</p>
      </div>

      <div className="acct-actions" style={{ marginBottom: 24 }}>
        <button className="acct-btn acct-btn-primary">+ Add Payment Method</button>
      </div>

      <div className="acct-card-grid">
        {methods.map(m => (
          <div key={m.id} className="acct-card">
            <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 12 }}>
              <h3 style={{ margin: 0 }}>{m.type} •••• {m.last4}</h3>
              {m.isDefault && <span className="acct-status acct-status--active">Default</span>}
            </div>
            <p>Expires {m.expiry}</p>
            <p>Cardholder: {m.holder}</p>
            <div className="acct-card-actions">
              <button className="acct-btn acct-btn-secondary">Edit</button>
              {!m.isDefault && <button className="acct-btn acct-btn-secondary">Set as Default</button>}
              <button className="acct-btn acct-btn-danger">Remove</button>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
