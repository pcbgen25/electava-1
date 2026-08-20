'use client';

import Link from 'next/link';
import BrandWordmark from '@/components/BrandWordmark/BrandWordmark';
import ThemeToggle from '@/components/ThemeToggle/ThemeToggle';
import './Footer.css';

export default function Footer() {
  return (
    <footer className="footer">
      <div className="container">
        <div className="footer-grid">
          <div className="footer-column footer-brand-column">
            <BrandWordmark size="xl" className="footer-brand-mark" />
            <ul className="footer-brand-links">
              <li><Link href="/about">About Us</Link></li>
              <li><Link href="/contact">Contact Us</Link></li>
              <li><Link href="/careers">Careers</Link></li>
              <li><Link href="/blog">Blog</Link></li>
            </ul>
          </div>

          <div className="footer-column footer-heading-column">
            <ul className="footer-heading-links">
              <li><Link href="/products">Products</Link></li>
              <li><Link href="/manufacturers">Manufacturers</Link></li>
              <li><Link href="/resources">Resources</Link></li>
              <li><Link href="/quotation">Quotation</Link></li>
              <li><Link href="/sell">Sell on Electava</Link></li>
            </ul>
          </div>

          <div className="footer-column">
            <h4>Stay Updated</h4>
            <div className="footer-newsletter">
              <p>Get the latest product updates, deals, and technical resources delivered to your inbox.</p>
              <form className="newsletter-form" onSubmit={e => e.preventDefault()}>
                <input type="email" placeholder="Enter your email" />
                <button type="submit">Subscribe</button>
              </form>
            </div>
            <div className="footer-brand-tools footer-update-tools">
              <span className="footer-locale">English (USD)</span>
              <ThemeToggle />
            </div>
          </div>
        </div>

        <div className="footer-bottom">
          <span>© 2026 Electava. All rights reserved.</span>
          <div className="footer-bottom-links">
            <a href="#">Privacy Policy</a>
            <a href="#">Terms of Service</a>
            <a href="#">Cookie Policy</a>
          </div>
        </div>
      </div>
    </footer>
  );
}
