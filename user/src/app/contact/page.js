import Link from 'next/link';
import {
  FiClock,
  FiHeadphones,
  FiMail,
  FiMapPin,
  FiPhone,
  FiShield,
} from 'react-icons/fi';
import ContactInquiryForm from './ContactInquiryForm';
import './contact.css';

const contactCards = [
  {
    title: 'Visit Us',
    description: 'Electronics City, Phase 1, Bengaluru, Karnataka 560100',
    icon: FiMapPin,
  },
  {
    title: 'Call / WhatsApp',
    description: '+91 80 1234 5678\n+91 98765 43210',
    icon: FiPhone,
  },
  {
    title: 'Email Us',
    description: 'info@pcbgen.in\nsupport@pcbgen.in',
    icon: FiMail,
  },
  {
    title: 'Business Hours',
    description: 'Mon-Fri: 9:00 AM - 6:00 PM\nSat: 10:00 AM - 2:00 PM',
    icon: FiClock,
  },
];

const supportAreas = [
  'Product and order questions',
  'Account and cart support',
  'Technical guidance for components',
  'Partnership and business inquiries',
];

export const metadata = {
  title: 'Contact - Electava',
  description: 'Contact Electava for product questions, support, partnerships, and general inquiries.',
};

export default function ContactPage() {
  return (
    <div className="contact-page">
      <section className="contact-hero">
        <div className="contact-hero-pattern" />
        <div className="container">
          <div className="contact-hero-content">
            <h1>Talk to our team for support, inquiries, and partnerships.</h1>
            <p>
              Use this page for general communication with Electava. If you need PCB design, BOM analysis,
              manufacturing, assembly, or testing pricing, please use the dedicated quotation page.
            </p>
            <div className="contact-hero-actions">
              <Link href="/quotation" className="btn btn-primary btn-lg">
                Request Quotation
              </Link>
              <a href="mailto:info@pcbgen.in" className="btn btn-secondary btn-lg">
                Email Us
              </a>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="contact-layout">
            <div className="contact-sidebar">
              <div className="contact-cards-grid">
                {contactCards.map(({ title, description, icon: Icon }) => (
                  <article key={title} className="card contact-info-card">
                    <div className="contact-card-icon">
                      <Icon />
                    </div>
                    <div>
                      <h3>{title}</h3>
                      {description.split('\n').map((line) => (
                        <p key={line}>{line}</p>
                      ))}
                    </div>
                  </article>
                ))}
              </div>

              <div className="card contact-support-card">
                <h3>What we can help with</h3>
                <p>We route general inquiries quickly so you can reach the right team without extra back-and-forth.</p>
                <ul className="contact-support-list">
                  {supportAreas.map((item) => (
                    <li key={item}>{item}</li>
                  ))}
                </ul>
              </div>

              <div className="card contact-routing-card">
                <h3>Need a service quotation instead?</h3>
                <p>
                  For PCB services, project builds, BOM analysis, manufacturer comparison, assembly, and testing requests,
                  use the quotation page so our team gets the right technical details from the start.
                </p>
                <Link href="/quotation" className="btn btn-secondary">
                  Go to Quotation Page
                </Link>
              </div>
            </div>

            <div className="card contact-form-shell">
              <div className="contact-form-intro">
                <h2>Send a message</h2>
                <p>Share your question, request, or update and we&apos;ll connect you with the right person.</p>
              </div>
              <ContactInquiryForm />
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
