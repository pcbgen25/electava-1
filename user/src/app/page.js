import Link from 'next/link';
import Image from 'next/image';
import ProductCard from '@/components/ProductCard/ProductCard';
import { getApiUrl } from '@/lib/api';
import './home.css';

async function getProducts() {
  try { const res = await fetch(getApiUrl('/components'), { cache: 'no-store' }); return res.ok ? res.json() : []; } catch(e) { return []; } 
}
async function getCategories() {
  try { const res = await fetch(getApiUrl('/categories'), { cache: 'no-store' }); return res.ok ? res.json() : []; } catch(e) { return []; }
}


export default async function Home() {
  const products = await getProducts();
  const categories = await getCategories();
  const featuredProducts = products.length > 0 ? products.slice(0, 8) : [];

  return (
    <div className="home-page">
      {/* Hero Section */}
      <section className="hero">
        <div className="hero-bg-pattern"></div>
        <div className="container">
          <div className="hero-grid">
            <div className="hero-content">
              <h1>Find Every Electronic<br />Component You Need</h1>
              <p className="hero-subtitle">
                Access millions of electronic components from 800+ manufacturers.
                Competitive pricing, real-time stock, and same-day shipping.
              </p>
              <div className="hero-actions">
                <Link href="/products" className="btn btn-primary btn-lg" id="hero-browse-btn">
                  Browse Components
                </Link>
              </div>
              <div className="hero-stats">
                <div className="hero-stat">
                  <strong>10M+</strong>
                  <span>Products</span>
                </div>
                <div className="hero-stat">
                  <strong>800+</strong>
                  <span>Manufacturers</span>
                </div>
                <div className="hero-stat">
                  <strong>99.8%</strong>
                  <span>Order Accuracy</span>
                </div>
                <div className="hero-stat">
                  <strong>24/7</strong>
                  <span>Support</span>
                </div>
              </div>
            </div>
            
            <div className="hero-image-wrapper">
              <Image 
                src="/hero_electronics.png" 
                alt="Electronic Components" 
                width={600} 
                height={600} 
                className="hero-floating-image"
                priority
              />
            </div>
          </div>
        </div>
      </section>

      {/* Browse by Category */}
      <section className="section">
        <div className="container">
          <h2 className="section-title">Browse by Category</h2>
          <p className="section-subtitle">Explore our extensive catalog of electronic components</p>
          <div className="category-grid">
            {categories.map((cat, i) => (
              <Link
                key={cat.id}
                href={`/products?category=${cat.id}`}
                className="category-card"
                style={{ animationDelay: `${i * 0.05}s` }}
                id={`category-${cat.id}`}
              >
                <div className="category-icon">{cat.icon}</div>
                <h3>{cat.name}</h3>
                <p>{cat.description}</p>
                <span className="category-arrow">→</span>
              </Link>
            ))}
          </div>
        </div>
      </section>

      {/* Featured Products */}
      <section className="section featured-section">
        <div className="container">
          <div className="flex-between">
            <div>
              <h2 className="section-title">Featured Products</h2>
              <p className="section-subtitle">Popular components chosen by engineers</p>
            </div>
            <Link href="/products" className="btn btn-secondary">View All Products →</Link>
          </div>
          <div className="products-grid">
            {featuredProducts.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        </div>
      </section>

      {/* Value Props */}
      <section className="section value-section">
        <div className="container">
          <h2 className="section-title" style={{ textAlign: 'center' }}>Why Engineers Choose Electava</h2>
          <div className="value-grid">
            <div className="value-card">
              <div className="value-icon">⚡</div>
              <h3>Same-Day Shipping</h3>
              <p>Order by 6 PM and get your components delivered the next business day. Lightning-fast logistics.</p>
            </div>
            <div className="value-card">
              <div className="value-icon">🔍</div>
              <h3>Parametric Search</h3>
              <p>Find the exact component you need with advanced filtering by specs, package, voltage, and more.</p>
            </div>
            <div className="value-card">
              <div className="value-icon">💰</div>
              <h3>Volume Pricing</h3>
              <p>Get better prices with volume discounts. See real-time pricing tiers for every component.</p>
            </div>
            <div className="value-card">
              <div className="value-icon">📋</div>
              <h3>BOM Tool</h3>
              <p>Upload your Bill of Materials and get instant pricing and availability for your entire project.</p>
            </div>
            <div className="value-card">
              <div className="value-icon">📄</div>
              <h3>Datasheets</h3>
              <p>Access comprehensive datasheets, application notes, and reference designs for every part.</p>
            </div>
            <div className="value-card">
              <div className="value-icon">🔒</div>
              <h3>100% Authentic</h3>
              <p>Every component is sourced directly from authorized distributors. Zero counterfeits guaranteed.</p>
            </div>
          </div>
        </div>
      </section>

      {/* Manufacturers */}
      <section className="section">
        <div className="container">
          <h2 className="section-title" style={{ textAlign: 'center' }}>Trusted Manufacturers</h2>
          <p className="section-subtitle" style={{ textAlign: 'center' }}>Components from the world&apos;s leading electronics brands</p>
          <div className="manufacturer-grid">
            {['Texas Instruments', 'STMicroelectronics', 'Microchip', 'Espressif', 'Infineon', 'Murata', 'Samsung', 'Bosch', 'Amphenol', 'Omron', 'Panasonic', 'TE Connectivity'].map(name => (
              <div key={name} className="manufacturer-card">
                <span>{name}</span>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="cta-section">
        <div className="container">
          <div className="cta-content">
            <h2>Ready to Source Your Components?</h2>
            <p>Join thousands of engineers and buyers who trust Electava for their electronic component needs.</p>
            <div className="cta-actions">
              <Link href="/register" className="btn btn-primary btn-lg">Create Free Account</Link>
              <Link href="/quotation" className="btn btn-outline btn-lg">Request Quotation</Link>
            </div>
          </div>
        </div>
      </section>
    </div>
  );
}
