import Link from 'next/link';
import {
  FiCheckCircle,
  FiClock,
  FiFileText,
  FiShield,
} from 'react-icons/fi';
import QuotationRequestForm from './QuotationRequestForm';
import './quotation.css';

const trustItems = [
  {
    title: 'Fast review turnaround',
    description: 'Our team reviews quotation requests quickly so you can move from planning to execution without delay.',
    icon: FiClock,
  },
  {
    title: 'Technical detail first',
    description: 'Share BOM, Gerber, assembly, and testing information up front so the quote fits your actual project scope.',
    icon: FiFileText,
  },
  {
    title: 'NDA-friendly workflow',
    description: 'We keep quotation requests protected and ready for confidential project discussions.',
    icon: FiShield,
  },
];

const serviceList = [
  'PCB design from concept to Gerber',
  'BOM analysis and sourcing review',
  'Manufacturer comparison and production planning',
  'PCB assembly for prototypes and batches',
  'Testing, bring-up, and debugging support',
  'Full project delivery coordination',
];

const processSteps = [
  {
    title: 'Share your project details',
    description: 'Tell us what you need, your stage, and the target timeline.',
  },
  {
    title: 'Share technical file links',
    description: 'Paste your Google Drive or other document link so we can review BOM, Gerber, drawings, and notes.',
  },
  {
    title: 'Receive a tailored quotation',
    description: 'We align the request with the right service path and follow up with next steps.',
  },
];

export const metadata = {
  title: 'Quotation - Electava',
  description: 'Request a quotation for PCB design, BOM analysis, manufacturing, assembly, testing, and delivery services.',
};

export default function QuotationPage() {
  return (
    <div className="quotation-page">
      <section className="quotation-hero">
        <div className="quotation-hero-pattern" />
        <div className="container">
          <div className="quotation-hero-grid">
            <div className="quotation-hero-copy">
              <h1>Request a quotation for your PCB and manufacturing needs.</h1>
              <p>
                Use this page for service-related requests such as PCB design, BOM analysis, manufacturer comparison,
                assembly, testing, and full project delivery. For general communication, use the contact page instead.
              </p>
              <div className="quotation-hero-actions">
                <Link href="#quotation-form" className="btn btn-primary btn-lg">
                  Start Request
                </Link>
                <Link href="/contact" className="btn btn-secondary btn-lg">
                  General Contact
                </Link>
              </div>
            </div>

            <div className="quotation-trust-panel">
              {trustItems.map(({ title, description, icon: Icon }) => (
                <div key={title} className="card quotation-trust-card">
                  <Icon />
                  <div>
                    <h3>{title}</h3>
                    <p>{description}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="quotation-layout">
            <div className="quotation-sidebar">
              <div className="card quotation-services-card">
                <h2 className="section-title">What you can request here</h2>
                <ul className="quotation-list">
                  {serviceList.map((item) => (
                    <li key={item}>
                      <FiCheckCircle />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>
              </div>

              <div className="card quotation-process-card">
                <h2 className="section-title">How it works</h2>
                <div className="quotation-process">
                  {processSteps.map((step, index) => (
                    <div key={step.title} className="quotation-step">
                      <span>{index + 1}</span>
                      <div>
                        <h3>{step.title}</h3>
                        <p>{step.description}</p>
                      </div>
                    </div>
                  ))}
                </div>
              </div>

              <div className="card quotation-routing-card">
                <h2 className="section-title">General question instead of a quote?</h2>
                <p>
                  If you want product support, account help, or a general discussion with the team, the contact page is
                  the better path.
                </p>
                <Link href="/contact" className="btn btn-secondary">
                  Go to Contact Page
                </Link>
              </div>
            </div>

            <div className="card quotation-form-shell" id="quotation-form">
              <div className="quotation-form-intro">
                <h2>Project details</h2>
                <p>
                  Fill in the form with your service requirements and project context so we can review the request
                  properly.
                </p>
              </div>
              <QuotationRequestForm />
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
