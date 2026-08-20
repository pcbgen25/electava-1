import Link from 'next/link';
import { 
  FiTarget, FiShoppingCart, FiCpu, FiTool, FiFileText, FiList, 
  FiPenTool, FiBarChart, FiCheckCircle, FiSearch, FiGlobe, 
  FiDollarSign, FiShield, FiClock, FiEye, FiLayout, FiActivity, FiLayers
} from 'react-icons/fi';

export const metadata = {
  title: 'About Electava — Electronics Marketplace & PCB Services',
  description: 'Your single destination for electronic components, modules, and complete PCB services.',
};

export default function AboutPage() {
  return (
    <div>
      {/* Hero */}
      <section style={{
        padding: '80px 0',
        background: 'linear-gradient(135deg, var(--bg-primary), var(--bg-secondary))',
        borderBottom: '1px solid var(--border-color)',
        textAlign: 'center'
      }}>
        <div className="container animate-slide-up">
          <h1 style={{ fontSize: '3rem', fontWeight: 900, marginBottom: '24px', letterSpacing: '-0.02em' }}>
            <span style={{ color: 'var(--accent-primary)' }}>Electava:</span> From Idea to Real‑Time Board
          </h1>
          <p style={{ color: 'var(--text-secondary)', fontSize: '1.2rem', maxWidth: '800px', margin: '0 auto', lineHeight: 1.7 }}>
            Your single destination for electronic components, modules, and complete PCB services &ndash; design, assembly, testing, and manufacturer comparison.
          </p>
        </div>
      </section>

      <div className="container">
        {/* Mission */}
        <section className="section">
          <div className="card" style={{ textAlign: 'center', padding: 'var(--space-2xl)' }}>
            <FiTarget size={40} style={{ color: 'var(--accent-primary)', marginBottom: '16px' }} />
            <h2 className="section-title" style={{ marginBottom: '16px', fontSize: '2rem' }}>Our Mission</h2>
            <p style={{ color: 'var(--text-secondary)', fontSize: '1.1rem', lineHeight: 1.8, maxWidth: '800px', margin: '0 auto' }}>
              To empower engineers, makers, and businesses by providing a seamless ecosystem where you can source any electronic component and transform your concept into a fully functional, tested board &ndash; all under one roof.
            </p>
          </div>
        </section>

        {/* Services Overview */}
        <section className="section" style={{ borderTop: '1px solid var(--border-color)', paddingTop: 'var(--space-3xl)' }}>
          <div className="grid grid-2" style={{ alignItems: 'stretch' }}>
            
            {/* Marketplace Card */}
            <div className="card" style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                <FiCpu size={32} style={{ color: 'var(--accent-secondary)' }} />
                <h2 style={{ fontSize: '1.75rem', margin: 0 }}>Marketplace &ndash; Everything Electronic</h2>
              </div>
              <p style={{ color: 'var(--text-secondary)', fontSize: '1.05rem', lineHeight: 1.6 }}>
                Electava is a comprehensive component store with thousands of products from leading manufacturers:
              </p>
              <ul style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                {[
                  'Resistors, Capacitors, Inductors',
                  'ICs, Microcontrollers, Sensors',
                  'Modules, Connectors, Tools',
                  'Datasheets & CAD models for every component',
                  'Intelligent BOM import – upload a list and instantly add to cart'
                ].map((item, i) => (
                  <li key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', fontSize: '1rem' }}>
                    <FiCheckCircle size={20} style={{ color: 'var(--accent-success)', flexShrink: 0, marginTop: '2px' }} />
                    <span>{item}</span>
                  </li>
                ))}
              </ul>
            </div>

            {/* PCB Services Card */}
            <div className="card" style={{ display: 'flex', flexDirection: 'column', gap: '24px' }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '12px' }}>
                <h2 style={{ fontSize: '1.75rem', margin: 0 }}>Complete PCB Services</h2>
              </div>
              <p style={{ color: 'var(--text-secondary)', fontSize: '1.05rem', lineHeight: 1.6 }}>
                We don&apos;t just sell parts &ndash; we help you bring your design to life:
              </p>
              <ul style={{ display: 'flex', flexDirection: 'column', gap: '16px' }}>
                {[
                  { title: 'PCB Design', desc: '– Schematic capture & layout (2‑20 layers)' },
                  { title: 'Manufacturer Comparison', desc: '– Get best quotes from India & China' },
                  { title: 'Assembly', desc: '– SMT / through‑hole with AOI & X‑ray' },
                  { title: 'Bring‑up & Testing', desc: '– Power‑on, functional test, debugging' },
                  { title: 'BOM Analysis', desc: '– Automatic component matching from your BOM' }
                ].map((item, i) => (
                  <li key={i} style={{ display: 'flex', alignItems: 'flex-start', gap: '12px', fontSize: '1rem' }}>
                    <FiCheckCircle size={20} style={{ color: 'var(--accent-success)', flexShrink: 0, marginTop: '2px' }} />
                    <div style={{ display: 'grid', gridTemplateColumns: 'minmax(120px, auto) 1fr', gap: '8px', width: '100%' }}>
                      <span style={{ fontWeight: 700 }}>{item.title}</span>
                      <span style={{ color: 'var(--text-secondary)' }}>{item.desc}</span>
                    </div>
                  </li>
                ))}
              </ul>
            </div>

          </div>
        </section>

        {/* The Electava Advantage */}
        <section className="section" style={{ background: 'var(--bg-secondary)', borderRadius: 'var(--radius-lg)', padding: 'var(--space-2xl)', margin: 'var(--space-2xl) 0' }}>
          <div style={{ textAlign: 'center', marginBottom: 'var(--space-2xl)' }}>
            <h2 className="section-title">The Electava Advantage: Seamless Integration</h2>
            <p className="section-subtitle" style={{ maxWidth: '700px', margin: '0 auto' }}>
              Your journey from idea to working board is now friction‑free.
            </p>
          </div>
          
          <div className="steps" style={{ justifyContent: 'center', flexWrap: 'wrap', gap: '12px' }}>
            {[
              { step: '1', title: 'Design', desc: 'Upload schematic / Gerber' },
              { step: '2', title: 'Compare Quotes', desc: 'Get the best manufacturing rates' },
              { step: '3', title: 'Buy BOM', desc: 'One‑click purchase of components' },
              { step: '4', title: 'Assemble & Test', desc: 'In‑house assembly, bring‑up' },
              { step: '5', title: 'Board Delivered', desc: 'Ready to deploy' }
            ].map((s, i) => (
              <div key={i} style={{ display: 'flex', alignItems: 'center' }}>
                <div className="step active" style={{ display: 'flex', flexDirection: 'column', padding: '16px 24px', gap: '8px', textAlign: 'center', borderRadius: 'var(--radius-lg)' }}>
                  <div style={{ fontSize: '0.8rem', textTransform: 'uppercase', letterSpacing: '0.05em', color: 'rgba(255,255,255,0.7)' }}>Step {s.step}</div>
                  <div style={{ fontSize: '1.1rem', fontWeight: 800 }}>{s.title}</div>
                  <div style={{ fontSize: '0.8rem', opacity: 0.9 }}>{s.desc}</div>
                </div>
                {i < 4 && <div className="step-line completed" style={{ width: '20px', margin: '0 8px' }}></div>}
              </div>
            ))}
          </div>
        </section>

        {/* Why Engineers Choose Electava */}
        <section className="section">
          <h2 className="section-title" style={{ textAlign: 'center', marginBottom: 'var(--space-2xl)' }}>
            Why Engineers & Businesses Choose Electava
          </h2>
          
          <div className="grid grid-3">
            {[
              { title: 'Global Sourcing', desc: 'Access to 100+ trusted manufacturers worldwide.', icon: <FiGlobe /> },
              { title: 'Transparent Quotes', desc: 'Side‑by‑side comparison of PCB fabrication & assembly.', icon: <FiDollarSign /> },
              { title: 'NDA Protected', desc: 'Your designs and intellectual property are safe with us.', icon: <FiShield /> },
              { title: '24‑Hour Response', desc: 'Quick quotes and project support.', icon: <FiClock /> },
              { title: 'CAD & Datasheet Ready', desc: 'Every product comes with technical resources.', icon: <FiFileText /> },
              { title: 'End‑to‑End Visibility', desc: 'Track your orders and service requests in one dashboard.', icon: <FiEye /> },
            ].map((feature, i) => (
              <div key={i} className="card" style={{ display: 'flex', alignItems: 'flex-start', gap: '16px' }}>
                <div style={{ color: 'var(--accent-secondary)', fontSize: '1.8rem', marginTop: '4px' }}>
                  {feature.icon}
                </div>
                <div>
                  <h4 style={{ marginBottom: '8px', fontSize: '1.1rem' }}>{feature.title}</h4>
                  <p style={{ color: 'var(--text-tertiary)', fontSize: '0.9rem', lineHeight: 1.6 }}>{feature.desc}</p>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Call to Action */}
        <section className="section" style={{ textAlign: 'center', paddingBottom: 'var(--space-3xl)' }}>
          <h2 className="section-title" style={{ marginBottom: '16px' }}>Ready to turn your idea into reality?</h2>
          <p className="section-subtitle" style={{ marginBottom: '32px', maxWidth: '700px', margin: '0 auto 32px' }}>
            Explore our store, upload your design, or request a quote today.
          </p>
          <div style={{ display: 'flex', justifyItems: 'center', justifyContent: 'center', gap: '16px', flexWrap: 'wrap' }}>
            <Link href="/products" className="btn btn-primary btn-lg">
              Browse Components
            </Link>
            <Link href="/quotation" className="btn btn-secondary btn-lg">
              Request Quotation
            </Link>
          </div>
        </section>
      </div>
    </div>
  );
}
