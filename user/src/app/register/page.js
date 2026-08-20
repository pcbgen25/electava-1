'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { FiMail, FiLock, FiUser, FiBriefcase } from 'react-icons/fi';
import BrandWordmark from '@/components/BrandWordmark/BrandWordmark';
import { getApiUrl } from '@/lib/api';
import '../login/auth.css';

export default function RegisterPage() {
  const [form, setForm] = useState({
    firstName: '', lastName: '', email: '', password: '', confirmPassword: '', company: '',
  });
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');
  const [createdEmail, setCreatedEmail] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const router = useRouter();

  async function handleSubmit(e) {
    e.preventDefault();
    if (form.password !== form.confirmPassword) {
      setError('Passwords do not match.');
      setSuccess('');
      return;
    }
    if (form.password.length < 8) {
      setError('Password must be at least 8 characters.');
      setSuccess('');
      return;
    }

    setError('');
    setSuccess('');
    setIsSubmitting(true);

    try {
      const response = await fetch(getApiUrl('/auth/register'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          firstName: form.firstName,
          lastName: form.lastName,
          email: form.email,
          company: form.company,
          password: form.password,
        }),
      });
      const data = await response.json();

      if (!response.ok) {
        setError(data.error || 'Unable to create account. Please try again.');
        return;
      }

      setCreatedEmail(data.user?.email || form.email);
      setSuccess(data.message || 'Account created successfully. You can now sign in.');
      setForm({
        firstName: '', lastName: '', email: '', password: '', confirmPassword: '', company: '',
      });
    } catch (err) {
      setError('Server is not reachable. Please make sure the local API is running.');
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <div className="auth-page">
      <div className="auth-card">
        <div className="auth-header">
          <BrandWordmark size="lg" centered className="auth-brand" />
          <h1>Create Account</h1>
          <p>Join Electava to access millions of components</p>
        </div>
        <form onSubmit={handleSubmit}>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px' }}>
            <div className="form-group">
              <label><FiUser size={14} style={{ marginRight: '4px' }} />First Name</label>
              <input className="input-field" value={form.firstName}
                onChange={e => setForm({...form, firstName: e.target.value})} placeholder="John" required />
            </div>
            <div className="form-group">
              <label>Last Name</label>
              <input className="input-field" value={form.lastName}
                onChange={e => setForm({...form, lastName: e.target.value})} placeholder="Doe" required />
            </div>
          </div>
          <div className="form-group">
            <label><FiMail size={14} style={{ marginRight: '4px' }} />Email</label>
            <input className="input-field" type="email" value={form.email}
              onChange={e => setForm({...form, email: e.target.value})} placeholder="you@example.com" required />
          </div>
          <div className="form-group">
            <label><FiBriefcase size={14} style={{ marginRight: '4px' }} />Company (Optional)</label>
            <input className="input-field" value={form.company}
              onChange={e => setForm({...form, company: e.target.value})} placeholder="Acme Corp" />
          </div>
          <div className="form-group">
            <label><FiLock size={14} style={{ marginRight: '4px' }} />Password</label>
            <input className="input-field" type="password" value={form.password}
              onChange={e => setForm({...form, password: e.target.value})} placeholder="Min 8 characters" required />
          </div>
          <div className="form-group">
            <label>Confirm Password</label>
            <input className="input-field" type="password" value={form.confirmPassword}
              onChange={e => setForm({...form, confirmPassword: e.target.value})} placeholder="Re-enter password" required />
          </div>
          {error && <p className="auth-error">{error}</p>}
          {success && (
            <div className="auth-success-message">
              <strong>{success}</strong>
              <span>Use {createdEmail} on the sign-in page.</span>
            </div>
          )}
          <button type="submit" className="btn btn-primary btn-lg" style={{ width: '100%', marginTop: '8px' }} disabled={isSubmitting}>
            {isSubmitting ? 'Creating Account...' : 'Create Account'}
          </button>
          {success && (
            <button
              type="button"
              className="btn btn-secondary btn-lg"
              style={{ width: '100%', marginTop: '10px' }}
              onClick={() => router.push(`/login?email=${encodeURIComponent(createdEmail)}`)}
            >
              Sign In Now
            </button>
          )}
        </form>
        <p className="auth-switch">
          Already have an account? <Link href="/login">Sign in</Link>
        </p>
      </div>
    </div>
  );
}
