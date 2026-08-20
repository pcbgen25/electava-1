'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { FiMail, FiLock } from 'react-icons/fi';
import BrandWordmark from '@/components/BrandWordmark/BrandWordmark';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';
import { getApiUrl } from '@/lib/api';
import './auth.css';

export default function LoginPage() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const router = useRouter();
  const { signIn } = useMarketplaceAuth();

  useEffect(() => {
    const params = new URLSearchParams(window.location.search);
    setEmail(params.get('email') || '');
  }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setIsSubmitting(true);

    try {
      const response = await fetch(getApiUrl('/auth/login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, password }),
      });
      const data = await response.json();

      if (!response.ok) {
        setError(data.error || 'Invalid email or password.');
        return;
      }

      signIn(data.user);
      router.push('/account');
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
          <h1>Welcome Back</h1>
          <p>Sign in to your Electava account</p>
        </div>

        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label><FiMail size={14} style={{ marginRight: '6px' }} />Email</label>
            <input className="input-field" type="email" value={email}
              onChange={e => setEmail(e.target.value)} placeholder="you@example.com" required />
          </div>
          <div className="form-group">
            <label><FiLock size={14} style={{ marginRight: '6px' }} />Password</label>
            <input className="input-field" type="password" value={password}
              onChange={e => setPassword(e.target.value)} placeholder="••••••••" required />
          </div>
          <div className="auth-forgot">
            <Link href="/forgot-password">Forgot password?</Link>
          </div>
          {error && <p className="auth-error">{error}</p>}
          <button type="submit" className="btn btn-primary btn-lg" style={{ width: '100%' }} disabled={isSubmitting}>
            {isSubmitting ? 'Signing In...' : 'Sign In'}
          </button>
        </form>

        <p className="auth-switch">
          Don&apos;t have an account? <Link href="/register">Create one</Link>
        </p>
      </div>
    </div>
  );
}
