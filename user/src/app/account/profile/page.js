'use client';

export default function ProfilePage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Profile</h2>
        <p>Manage your personal information and account details.</p>
      </div>

      <div className="account-box">
        <form className="acct-form" onSubmit={e => e.preventDefault()}>
          <div className="acct-form-row">
            <div className="acct-field">
              <label>Full Name</label>
              <input type="text" defaultValue="Admin User" />
            </div>
            <div className="acct-field">
              <label>Job Title</label>
              <input type="text" defaultValue="Procurement Manager" />
            </div>
          </div>

          <div className="acct-field">
            <label>Email Address</label>
            <input type="email" defaultValue="admin@electava.com" readOnly style={{ opacity: 0.6, cursor: 'not-allowed' }} />
          </div>

          <div className="acct-form-row">
            <div className="acct-field">
              <label>Company</label>
              <input type="text" defaultValue="Electava Marketplace" />
            </div>
            <div className="acct-field">
              <label>Phone</label>
              <input type="tel" defaultValue="+91-98765-43210" />
            </div>
          </div>

          <div className="acct-actions">
            <button type="submit" className="acct-btn acct-btn-primary">Save Changes</button>
            <button type="button" className="acct-btn acct-btn-secondary">Cancel</button>
          </div>
        </form>
      </div>
    </>
  );
}
