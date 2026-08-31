'use client';

import { useState } from 'react';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';

export default function ProfilePage() {
  const { user } = useMarketplaceAuth();

  const [fullName, setFullName] = useState(user?.fullName || '');
  const [jobTitle, setJobTitle] = useState(user?.jobTitle || '');
  const [email] = useState(user?.email || '');
  const [company, setCompany] = useState(user?.company || '');
  const [phone, setPhone] = useState(user?.phone || '');

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
              <input
                type="text"
                value={fullName}
                onChange={e => setFullName(e.target.value)}
              />
            </div>
            <div className="acct-field">
              <label>Job Title</label>
              <input
                type="text"
                value={jobTitle}
                onChange={e => setJobTitle(e.target.value)}
              />
            </div>
          </div>

          <div className="acct-field">
            <label>Email Address</label>
            <input type="email" value={email} readOnly style={{ opacity: 0.6, cursor: 'not-allowed' }} />
          </div>

          <div className="acct-form-row">
            <div className="acct-field">
              <label>Company</label>
              <input
                type="text"
                value={company}
                onChange={e => setCompany(e.target.value)}
              />
            </div>
            <div className="acct-field">
              <label>Phone</label>
              <input
                type="tel"
                value={phone}
                onChange={e => setPhone(e.target.value)}
              />
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

