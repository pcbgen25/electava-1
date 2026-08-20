import Link from 'next/link';
import {
  FiArrowRight,
  FiBarChart2,
  FiCheckCircle,
  FiGlobe,
  FiPackage,
  FiUsers,
} from 'react-icons/fi';
import './sell.css';

const benefits = [
  {
    title: 'Reach engineering buyers',
    description: 'Connect with customers looking for components, modules, tools, and project support in one place.',
    icon: FiUsers,
  },
  {
    title: 'Grow product visibility',
    description: 'Show your catalog to buyers who are actively comparing manufacturers and sourcing options.',
    icon: FiGlobe,
  },
  {
    title: 'List smarter offerings',
    description: 'Highlight stock, lead time, pricing, and technical strengths with a cleaner vendor presentation.',
    icon: FiPackage,
  },
];

const steps = [
  {
    title: 'Share your business profile',
    description: 'Tell us about your product range, manufacturing strengths, and target customer categories.',
  },
  {
    title: 'Team review',
    description: 'We review fit, product relevance, and how your offering aligns with the Electava marketplace.',
  },
  {
    title: 'Apply and connect',
    description: 'Once you are ready, submit your interest through our contact page and our team will follow up.',
  },
];

export const metadata = {
  title: 'Sell on Electava',
  description: 'Apply to sell on Electava and connect your products with electronics buyers and engineering teams.',
};

export default function SellPage() {
  return (
    <div className="sell-page">
      <section className="sell-hero">
        <div className="sell-hero-pattern" />
        <div className="container">
          <div className="sell-hero-grid">
            <div className="sell-hero-copy">
              <h1>Bring your products to engineers, buyers, and growing hardware teams.</h1>
              <p>
                Electava helps manufacturers and suppliers present their products to customers who are already searching,
                comparing, and planning real electronics builds.
              </p>
              <div className="sell-hero-actions">
                <Link href="/contact" className="btn btn-primary btn-lg">
                  Apply Now
                </Link>
                <Link href="#sell-process" className="btn btn-secondary btn-lg">
                  How It Works
                </Link>
              </div>
            </div>

            <div className="card sell-hero-card">
              <div className="sell-hero-card-icon">
                <FiBarChart2 />
              </div>
              <h3>Why sell here?</h3>
              <ul className="sell-hero-points">
                <li>
                  <FiCheckCircle />
                  <span>Reach serious electronics customers</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Showcase manufacturer strengths clearly</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Start with a simple application flow</span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="sell-section-heading">
            <h2 className="section-title">Why suppliers join Electava</h2>
            <p className="section-subtitle">
              We are building a marketplace experience that makes it easier for serious buyers to discover trusted products and partners.
            </p>
          </div>

          <div className="sell-benefits-grid">
            {benefits.map(({ title, description, icon: Icon }) => (
              <article key={title} className="card sell-benefit-card">
                <div className="sell-benefit-icon">
                  <Icon />
                </div>
                <h3>{title}</h3>
                <p>{description}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="section sell-process-section" id="sell-process">
        <div className="container">
          <div className="sell-section-heading">
            <h2 className="section-title">How to apply</h2>
            <p className="section-subtitle">
              We keep the process simple so you can quickly share your interest and let the team review it.
            </p>
          </div>

          <div className="sell-steps-grid">
            {steps.map((step, index) => (
              <article key={step.title} className="card sell-step-card">
                <span className="sell-step-number">Step {index + 1}</span>
                <h3>{step.title}</h3>
                <p>{step.description}</p>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="sell-cta">
        <div className="container">
          <div className="sell-cta-card">
            <h2>Ready to apply?</h2>
            <p>
              Use the contact page to share your business details, product category, and selling interest. Our team will review and get back to you.
            </p>
            <Link href="/contact" className="btn btn-primary btn-lg">
              Apply Through Contact <FiArrowRight />
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
