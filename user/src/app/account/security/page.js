'use client';

export default function SecurityPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Login &amp; Security</h2>
        <p>Manage your password, two-factor authentication, and active sessions.</p>
      </div>

      <div className="account-box">
        <div className="acct-section">
          <h3>Change Password</h3>
          <form className="acct-form" onSubmit={e => e.preventDefault()}>
            <div className="acct-field">
              <label>Current Password</label>
              <input type="password" placeholder="Enter current password" />
            </div>
            <div className="acct-form-row">
              <div className="acct-field">
                <label>New Password</label>
                <input type="password" placeholder="Enter new password" />
              </div>
              <div className="acct-field">
                <label>Confirm New Password</label>
                <input type="password" placeholder="Confirm new password" />
              </div>
            </div>
            <div className="acct-actions">
              <button type="submit" className="acct-btn acct-btn-primary">Update Password</button>
            </div>
          </form>
        </div>

        <div className="acct-section">
          <h3>Two-Factor Authentication</h3>
          <div className="acct-toggle-row">
            <div className="acct-toggle-label">
              <h4>Enable 2FA</h4>
              <p>Add an extra layer of security using an authenticator app.</p>
            </div>
            <label className="acct-toggle">
              <input type="checkbox" defaultChecked={false} />
              <span className="acct-toggle-track" />
            </label>
          </div>
        </div>

        <div className="acct-section">
          <h3>Active Sessions</h3>
          <div className="acct-table-wrap">
            <table className="acct-table">
              <thead>
                <tr>
                  <th>Device</th>
                  <th>Location</th>
                  <th>Last Active</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Chrome on Windows</td>
                  <td>Mumbai, India</td>
                  <td>Just now</td>
                  <td><span className="acct-status acct-status--active">Current</span></td>
                </tr>
                <tr>
                  <td>Firefox on macOS</td>
                  <td>Delhi, India</td>
                  <td>2 days ago</td>
                  <td><button className="acct-btn acct-btn-danger" style={{ padding: '4px 12px', fontSize: '0.8rem' }}>Revoke</button></td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </>
  );
}
