'use client';

import { useState } from 'react';
import Link from 'next/link';
import { FiArrowLeft, FiMail, FiShield } from 'react-icons/fi';
import BrandWordmark from '@/components/BrandWordmark/BrandWordmark';
import '../login/auth.css';

export default function ForgotPasswordForm() {
  const [email, setEmail] = useState('');
  const [submittedEmail, setSubmittedEmail] = useState('');

  function handleSubmit(e) {
    e.preventDefault();
    setSubmittedEmail(email.trim());
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <div className="auth-header">
          <BrandWordmark size="lg" centered className="auth-brand" />
          <h1>Recover Password</h1>
          <p>Enter your account email and we will send password reset instructions.</p>
        </div>

        {!submittedEmail ? (
          <form onSubmit={handleSubmit}>
            <div className="form-group">
              <label><FiMail size={14} style={{ marginRight: '6px' }} />Email</label>
              <input
                className="input-field"
                type="email"
                value={email}
                onChange={e => setEmail(e.target.value)}
                placeholder="you@example.com"
                required
              />
            </div>

            <p className="auth-helper-text">
              Use the email address connected to your Electava account.
            </p>

            <button type="submit" className="btn btn-primary btn-lg" style={{ width: '100%' }}>
              Send Recovery Link
            </button>
          </form>
        ) : (
          <div className="auth-success">
            <div className="auth-success-icon">
              <FiShield size={20} />
            </div>
            <h2>Check your inbox</h2>
            <p>
              We sent recovery instructions to <strong>{submittedEmail}</strong>. This demo page is ready for future email integration.
            </p>
            <button
              type="button"
              className="btn btn-secondary btn-lg"
              style={{ width: '100%' }}
              onClick={() => {
                setSubmittedEmail('');
                setEmail('');
              }}
            >
              Use Another Email
            </button>
          </div>
        )}

        <p className="auth-switch">
          Remembered your password? <Link href="/login">Sign in</Link>
        </p>

        <div className="auth-back-link">
          <Link href="/login">
            <FiArrowLeft size={14} />
            <span>Back to login</span>
          </Link>
        </div>
      </div>
    </div>
  );
}
