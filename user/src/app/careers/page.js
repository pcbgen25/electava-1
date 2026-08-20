import Link from 'next/link';
import {
  FiArrowRight,
  FiBriefcase,
  FiCheckCircle,
  FiCpu,
  FiHeart,
  FiMapPin,
  FiTrendingUp,
  FiUsers,
} from 'react-icons/fi';
import CareerApplicationForm from './CareerApplicationForm';
import './careers.css';

const perks = [
  {
    title: 'Build for engineers',
    description: 'Work on tools, sourcing flows, and PCB services that solve real hardware problems every day.',
    icon: FiCpu,
  },
  {
    title: 'Grow with the team',
    description: 'Join a fast-moving team where your ideas shape product direction, operations, and customer experience.',
    icon: FiTrendingUp,
  },
  {
    title: 'Collaborative culture',
    description: 'We value ownership, practical thinking, and kind teamwork across engineering, operations, and support.',
    icon: FiUsers,
  },
  {
    title: 'Meaningful work',
    description: 'Help companies move from idea to manufactured boards with better sourcing, better insight, and less friction.',
    icon: FiHeart,
  },
];

async function getCareers() {
  try {
    const res = await fetch('http://localhost:5000/api/careers', { next: { revalidate: 10 } });
    if (!res.ok) return [];
    return await res.json();
  } catch (error) {
    console.error('Error fetching careers:', error);
    return [];
  }
}

const processSteps = [
  {
    title: 'Share your interest',
    description: 'Send us your background, resume, and the kind of role you want to explore.',
  },
  {
    title: 'Team conversation',
    description: 'We set up an introductory discussion about your experience, working style, and interests.',
  },
  {
    title: 'Practical review',
    description: 'Depending on the role, we may do a short technical, product, or operations exercise.',
  },
  {
    title: 'Final fit check',
    description: 'We align on responsibilities, expectations, and how you can grow with Electava.',
  },
];

export const metadata = {
  title: 'Careers - Electava',
  description: 'Explore career opportunities at Electava across electronics, operations, and product development.',
};

export default async function CareersPage() {
  const roles = await getCareers();
  return (
    <div className="careers-page">
      <section className="careers-hero">
        <div className="careers-hero-pattern" />
        <div className="container">
          <div className="careers-hero-grid">
            <div className="careers-hero-copy">
              <span className="careers-badge">Careers at Electava</span>
              <h1>Help build the future of electronics sourcing and PCB services.</h1>
              <p className="careers-hero-text">
                We are building a simpler path from idea to manufactured board. Join us if you enjoy practical problem-solving, thoughtful product work, and helping engineers move faster.
              </p>
              <div className="careers-hero-actions">
                <Link href="#career-application" className="btn btn-primary btn-lg">
                  Start Application
                </Link>
                <Link href="#open-roles" className="btn btn-secondary btn-lg">
                  View Open Roles
                </Link>
              </div>
              <div className="careers-hero-stats">
                <div>
                  <strong>Hybrid-friendly</strong>
                  <span>Flexible team structure</span>
                </div>
                <div>
                  <strong>Cross-functional</strong>
                  <span>Engineering, ops, and product</span>
                </div>
                <div>
                  <strong>Customer-first</strong>
                  <span>Built for real hardware teams</span>
                </div>
              </div>
            </div>

            <div className="careers-hero-panel card">
              <div className="careers-hero-panel-header">
                <FiBriefcase />
                <span>What we value</span>
              </div>
              <ul className="careers-value-list">
                <li>
                  <FiCheckCircle />
                  <span>Clear thinking over complexity</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Ownership with collaboration</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Fast learning and practical execution</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Respect for the details that matter</span>
                </li>
              </ul>
              <div className="careers-hero-note">
                If you care about electronics, manufacturing workflows, or product building, we would love to hear from you.
              </div>
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="careers-section-heading">
            <h2 className="section-title">Why join Electava</h2>
            <p className="section-subtitle">
              We are building useful systems for engineers, buyers, and manufacturers, and we want teammates who enjoy making complex work feel simple.
            </p>
          </div>

          <div className="careers-perk-grid">
            {perks.map(({ title, description, icon: Icon }) => (
              <div key={title} className="card careers-perk-card">
                <div className="careers-perk-icon">
                  <Icon />
                </div>
                <h3>{title}</h3>
                <p>{description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section className="section careers-openings" id="open-roles">
        <div className="container">
          <div className="careers-section-heading">
            <h2 className="section-title">Open roles</h2>
            <p className="section-subtitle">
              Here are a few areas where we are especially excited to connect with strong candidates.
            </p>
          </div>

          <div className="careers-role-list">
            {roles.map((role) => (
              <article key={role.title} className="card careers-role-card">
                <div className="careers-role-top">
                  <div>
                    <div className="careers-role-meta">
                      <span className="tag">{role.team}</span>
                      <span className="tag">{role.type}</span>
                    </div>
                    <h3>{role.title}</h3>
                  </div>

                  <div className="careers-role-location">
                    <FiMapPin />
                    <span>{role.location}</span>
                  </div>
                </div>

                <p className="careers-role-summary">{role.summary}</p>

                <ul className="careers-role-highlights">
                  {role.highlights.map((item) => (
                    <li key={item}>
                      <FiCheckCircle />
                      <span>{item}</span>
                    </li>
                  ))}
                </ul>

                <div className="careers-role-actions">
                  <Link href="#career-application" className="btn btn-secondary">
                    Apply for This Role <FiArrowRight />
                  </Link>
                </div>
              </article>
            ))}
          </div>
        </div>
      </section>

      <section className="section careers-application" id="career-application">
        <div className="container">
          <div className="careers-application-grid">
            <div className="careers-application-copy">
              <span className="badge badge-success">Career Application</span>
              <h2 className="section-title">Apply directly from the careers page</h2>
              <p className="section-subtitle">
                This page is now fully separate from contact. Use the application form here to tell us about your background,
                preferred role, and the kind of work you want to do at Electava.
              </p>
              <ul className="careers-application-list">
                <li>
                  <FiCheckCircle />
                  <span>Choose the role that fits your strengths best</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Share your LinkedIn, portfolio, or resume link</span>
                </li>
                <li>
                  <FiCheckCircle />
                  <span>Tell us what kind of problems you want to solve with us</span>
                </li>
              </ul>
            </div>

            <div className="card careers-application-card">
              <CareerApplicationForm roles={roles.map((role) => role.title)} />
            </div>
          </div>
        </div>
      </section>

      <section className="section">
        <div className="container">
          <div className="careers-process">
            <div className="careers-section-heading">
              <h2 className="section-title">Hiring process</h2>
              <p className="section-subtitle">
                We keep the process straightforward so you know what to expect from the first conversation.
              </p>
            </div>

            <div className="careers-process-grid">
              {processSteps.map((step, index) => (
                <div key={step.title} className="card careers-process-card">
                  <span className="careers-process-step">Step {index + 1}</span>
                  <h3>{step.title}</h3>
                  <p>{step.description}</p>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      <section className="careers-cta">
        <div className="container">
          <div className="careers-cta-card">
            <h2>Interested in working with us?</h2>
            <p>
              Share your background, preferred role, and profile details using the application form on this page.
            </p>
            <Link href="#career-application" className="btn btn-primary btn-lg">
              Open Application Form
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
