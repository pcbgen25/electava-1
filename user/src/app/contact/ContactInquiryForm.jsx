'use client';

import { useState } from 'react';
import { FiSend, FiShield } from 'react-icons/fi';

const inquiryTopics = [
  'General Inquiry',
  'Order Support',
  'Technical Support',
  'Partnerships',
  'Account Help',
  'Other',
];

export default function ContactInquiryForm() {
  const [form, setForm] = useState({
    fullName: '',
    email: '',
    phone: '',
    company: '',
    topic: '',
    message: '',
  });

  function handleChange(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function handleSubmit(event) {
    event.preventDefault();
    alert('Message sent! Our team will get back to you shortly.');
    console.log('Contact inquiry:', form);
  }

  return (
    <form className="contact-form" onSubmit={handleSubmit}>
      <div className="contact-form-grid">
        <div className="form-group">
          <label htmlFor="contact-full-name">Full Name</label>
          <input
            id="contact-full-name"
            className="input-field"
            placeholder="Enter your full name"
            required
            value={form.fullName}
            onChange={(event) => handleChange('fullName', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="contact-email">Email Address</label>
          <input
            id="contact-email"
            className="input-field"
            type="email"
            placeholder="you@example.com"
            required
            value={form.email}
            onChange={(event) => handleChange('email', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="contact-phone">Phone Number</label>
          <input
            id="contact-phone"
            className="input-field"
            placeholder="+91 98765 43210"
            value={form.phone}
            onChange={(event) => handleChange('phone', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="contact-company">Company / Organization</label>
          <input
            id="contact-company"
            className="input-field"
            placeholder="Your company name"
            value={form.company}
            onChange={(event) => handleChange('company', event.target.value)}
          />
        </div>
      </div>

      <div className="form-group">
        <label htmlFor="contact-topic">Inquiry Type</label>
        <select
          id="contact-topic"
          className="input-field"
          required
          value={form.topic}
          onChange={(event) => handleChange('topic', event.target.value)}
        >
          <option value="">Select a topic</option>
          {inquiryTopics.map((topic) => (
            <option key={topic} value={topic.toLowerCase().replace(/\s+/g, '-')}>
              {topic}
            </option>
          ))}
        </select>
      </div>

      <div className="form-group">
        <label htmlFor="contact-message">Message</label>
        <textarea
          id="contact-message"
          className="input-field"
          rows="6"
          placeholder="Tell us how we can help."
          required
          value={form.message}
          onChange={(event) => handleChange('message', event.target.value)}
        />
      </div>

      <button type="submit" className="btn btn-primary btn-lg contact-submit-btn">
        <FiSend /> Send Message
      </button>

      <div className="contact-form-note">
        <FiShield />
        <span>Your message is shared only with the Electava support team.</span>
      </div>
    </form>
  );
}
