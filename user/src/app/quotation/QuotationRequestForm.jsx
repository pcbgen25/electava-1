'use client';

import { useState } from 'react';
import { FiSend, FiShield } from 'react-icons/fi';

const services = [
  'PCB Design (Idea to Gerber)',
  'BOM Analysis',
  'Manufacturer Comparison',
  'PCB Assembly',
  'Testing & Debugging',
  'Full Project Delivery',
];

const projectStages = [
  'Concept stage',
  'Schematic ready',
  'Gerber ready',
  'BOM ready',
  'Prototype revision',
  'Production planning',
];

const timelines = [
  'As soon as possible',
  'Within 1 week',
  'Within 2-4 weeks',
  'Within 1-2 months',
  'Still planning',
];

export default function QuotationRequestForm() {
  const [form, setForm] = useState({
    fullName: '',
    email: '',
    phone: '',
    company: '',
    service: '',
    stage: '',
    timeline: '',
    quantity: '',
    attachmentLink: '',
    description: '',
    ndaAgreed: false,
  });
  const [tokenNumber, setTokenNumber] = useState(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  function handleChange(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  async function handleSubmit(event) {
    event.preventDefault();
    setIsSubmitting(true);
    try {
      const response = await fetch('http://localhost:5000/api/service-token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          userEmail: form.email,
          serviceType: form.service,
          details: JSON.stringify(form)
        })
      });
      const data = await response.json();
      if (data.success) {
        setTokenNumber(data.token);
      } else {
        alert('Failed to generate service token');
      }
    } catch (err) {
      console.error(err);
      alert('Network error while requesting quotation.');
    }
    setIsSubmitting(false);
  }

  if (tokenNumber) {
    return (
      <div className="quotation-form" style={{ textAlign: 'center', padding: 'var(--space-3xl) var(--space-lg)' }}>
        <FiShield size={48} color="var(--accent-primary)" style={{ margin: '0 auto var(--space-md)' }} />
        <h2 style={{ fontSize: '2rem', marginBottom: 'var(--space-sm)' }}>Quotation Request Received!</h2>
        <p style={{ color: 'var(--text-secondary)', marginBottom: 'var(--space-lg)' }}>
          Your service request has been logged successfully.
        </p>
        <div style={{ background: 'var(--bg-tertiary)', padding: 'var(--space-xl)', borderRadius: 'var(--radius-lg)', display: 'inline-block' }}>
          <p style={{ textTransform: 'uppercase', fontSize: '0.8rem', color: 'var(--text-tertiary)', margin: '0 0 var(--space-xs)' }}>Your Service Token Number</p>
          <div style={{ fontSize: '1.5rem', fontWeight: 'bold', color: 'var(--text-primary)', letterSpacing: '2px' }}>{tokenNumber}</div>
        </div>
        <p style={{ marginTop: 'var(--space-lg)', color: 'var(--text-tertiary)', fontSize: '0.9rem' }}>
          Please save this token number for future tracking in your workspace.
        </p>
      </div>
    );
  }

  return (
    <form className="quotation-form" onSubmit={handleSubmit}>
      <div className="quotation-form-grid">
        <div className="form-group">
          <label htmlFor="quotation-full-name">Full Name</label>
          <input
            id="quotation-full-name"
            className="input-field"
            placeholder="Enter your full name"
            required
            value={form.fullName}
            onChange={(event) => handleChange('fullName', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="quotation-email">Email Address</label>
          <input
            id="quotation-email"
            className="input-field"
            type="email"
            placeholder="you@example.com"
            required
            value={form.email}
            onChange={(event) => handleChange('email', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="quotation-phone">Phone Number</label>
          <input
            id="quotation-phone"
            className="input-field"
            placeholder="+91 98765 43210"
            value={form.phone}
            onChange={(event) => handleChange('phone', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="quotation-company">Company / Organization</label>
          <input
            id="quotation-company"
            className="input-field"
            placeholder="Your company name"
            value={form.company}
            onChange={(event) => handleChange('company', event.target.value)}
          />
        </div>
      </div>

      <div className="quotation-form-grid">
        <div className="form-group">
          <label htmlFor="quotation-service">Service Required</label>
          <select
            id="quotation-service"
            className="input-field"
            required
            value={form.service}
            onChange={(event) => handleChange('service', event.target.value)}
          >
            <option value="">Select a service</option>
            {services.map((service) => (
              <option key={service} value={service.toLowerCase().replace(/[^a-z0-9]+/g, '-')}>
                {service}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="quotation-stage">Project Stage</label>
          <select
            id="quotation-stage"
            className="input-field"
            required
            value={form.stage}
            onChange={(event) => handleChange('stage', event.target.value)}
          >
            <option value="">Select project stage</option>
            {projectStages.map((stage) => (
              <option key={stage} value={stage.toLowerCase().replace(/[^a-z0-9]+/g, '-')}>
                {stage}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="quotation-timeline">Target Timeline</label>
          <select
            id="quotation-timeline"
            className="input-field"
            value={form.timeline}
            onChange={(event) => handleChange('timeline', event.target.value)}
          >
            <option value="">Select a timeline</option>
            {timelines.map((timeline) => (
              <option key={timeline} value={timeline.toLowerCase().replace(/[^a-z0-9]+/g, '-')}>
                {timeline}
              </option>
            ))}
          </select>
        </div>

        <div className="form-group">
          <label htmlFor="quotation-quantity">Expected Quantity</label>
          <input
            id="quotation-quantity"
            className="input-field"
            placeholder="Prototype, 100 units, 1K units..."
            value={form.quantity}
            onChange={(event) => handleChange('quantity', event.target.value)}
          />
        </div>
      </div>

      <div className="form-group">
        <label htmlFor="quotation-description">Project Description</label>
        <textarea
          id="quotation-description"
          className="input-field"
          rows="6"
          placeholder="Share your PCB, BOM, manufacturing, assembly, or testing requirements."
          required
          value={form.description}
          onChange={(event) => handleChange('description', event.target.value)}
        />
      </div>

      <div className="form-group">
        <label htmlFor="quotation-attachment-link">Attachment Link</label>
        <input
          id="quotation-attachment-link"
          className="input-field"
          type="url"
          placeholder="Paste Google Drive, Dropbox, or other file-sharing link"
          value={form.attachmentLink}
          onChange={(event) => handleChange('attachmentLink', event.target.value)}
        />
      </div>

      <label className="quotation-checkbox">
        <input
          type="checkbox"
          required
          checked={form.ndaAgreed}
          onChange={(event) => handleChange('ndaAgreed', event.target.checked)}
        />
        <span>I confirm the request details are accurate and I want the team to review them under NDA.</span>
      </label>

      <button type="submit" className="btn btn-primary btn-lg quotation-submit-btn" disabled={isSubmitting}>
        <FiSend /> {isSubmitting ? 'Processing...' : 'Request Quotation'}
      </button>

      <div className="quotation-form-note">
        <FiShield />
        <span>Your request details stay protected and are reviewed only by the Electava service team.</span>
      </div>
    </form>
  );
}
