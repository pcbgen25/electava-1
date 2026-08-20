'use client';

import { useState } from 'react';
import { FiSend, FiUserCheck } from 'react-icons/fi';

export default function CareerApplicationForm({ roles }) {
  const [form, setForm] = useState({
    fullName: '',
    email: '',
    phone: '',
    role: '',
    experience: '',
    profileLink: '',
    message: '',
  });

  function handleChange(field, value) {
    setForm((current) => ({ ...current, [field]: value }));
  }

  function handleSubmit(event) {
    event.preventDefault();
    alert('Application submitted! Our team will review your details and get back to you.');
    console.log('Career application:', form);
  }

  return (
    <form className="career-form" onSubmit={handleSubmit}>
      <div className="career-form-grid">
        <div className="form-group">
          <label htmlFor="career-full-name">Full Name</label>
          <input
            id="career-full-name"
            className="input-field"
            placeholder="Enter your full name"
            required
            value={form.fullName}
            onChange={(event) => handleChange('fullName', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="career-email">Email Address</label>
          <input
            id="career-email"
            className="input-field"
            type="email"
            placeholder="you@example.com"
            required
            value={form.email}
            onChange={(event) => handleChange('email', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="career-phone">Phone Number</label>
          <input
            id="career-phone"
            className="input-field"
            placeholder="+91 98765 43210"
            value={form.phone}
            onChange={(event) => handleChange('phone', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="career-role">Role of Interest</label>
          <select
            id="career-role"
            className="input-field"
            required
            value={form.role}
            onChange={(event) => handleChange('role', event.target.value)}
          >
            <option value="">Select a role</option>
            {roles.map((role) => (
              <option key={role} value={role.toLowerCase().replace(/[^a-z0-9]+/g, '-')}>
                {role}
              </option>
            ))}
          </select>
        </div>
      </div>

      <div className="career-form-grid">
        <div className="form-group">
          <label htmlFor="career-experience">Experience</label>
          <input
            id="career-experience"
            className="input-field"
            placeholder="2 years, 5 years, internship..."
            value={form.experience}
            onChange={(event) => handleChange('experience', event.target.value)}
          />
        </div>

        <div className="form-group">
          <label htmlFor="career-profile-link">LinkedIn / Portfolio / Resume Link</label>
          <input
            id="career-profile-link"
            className="input-field"
            placeholder="https://..."
            value={form.profileLink}
            onChange={(event) => handleChange('profileLink', event.target.value)}
          />
        </div>
      </div>

      <div className="form-group">
        <label htmlFor="career-message">Why do you want to join Electava?</label>
        <textarea
          id="career-message"
          className="input-field"
          rows="6"
          placeholder="Tell us about your background, strengths, and what kind of work excites you."
          required
          value={form.message}
          onChange={(event) => handleChange('message', event.target.value)}
        />
      </div>

      <button type="submit" className="btn btn-primary btn-lg career-submit-btn">
        <FiSend /> Submit Application
      </button>

      <div className="career-form-note">
        <FiUserCheck />
        <span>Share as much context as you can. Strong practical detail helps us review applications faster.</span>
      </div>
    </form>
  );
}
