'use client';

import { use, useState } from 'react';
import Link from 'next/link';
import { FiShoppingCart, FiDownload, FiPackage, FiChevronRight } from 'react-icons/fi';
import { useCart } from '@/context/CartContext';
import { getProductById, getStockStatus, formatStock, getProductsByCategory } from '@/data/products';
import { getCategoryById } from '@/data/categories';
import ProductCard from '@/components/ProductCard/ProductCard';
import './product-detail.css';

export default function ProductDetailPage({ params }) {
  const { id } = use(params);
  const product = getProductById(id);
  const { addItem } = useCart();
  const [quantity, setQuantity] = useState(1);

  if (!product) {
    return (
      <div className="container" style={{ padding: '80px 0', textAlign: 'center' }}>
        <h2>Product Not Found</h2>
        <p style={{ color: 'var(--text-secondary)', margin: '16px 0' }}>The product you&apos;re looking for doesn&apos;t exist.</p>
        <Link href="/products" className="btn btn-primary">Browse Products</Link>
      </div>
    );
  }

  const stockStatus = getStockStatus(product.stock);
  const category = getCategoryById(product.category);
  const relatedProducts = getProductsByCategory(product.category)
    .filter(p => p.id !== product.id)
    .slice(0, 4);

  function handleAddToCart() {
    addItem(product, quantity);
  }

  // Find best price for current quantity
  const bestTier = product.priceTiers
    ?.slice()
    .sort((a, b) => b.qty - a.qty)
    .find(t => quantity >= t.qty);
  const unitPrice = bestTier ? bestTier.price : product.price;
  const lineTotal = unitPrice * quantity;

  return (
    <div className="product-detail-page">
      <div className="page-title-bar">
        <div className="container">
          <div className="breadcrumb">
            <Link href="/">Home</Link>
            <span className="breadcrumb-sep"><FiChevronRight size={12} /></span>
            <Link href="/products">Products</Link>
            {category && (
              <>
                <span className="breadcrumb-sep"><FiChevronRight size={12} /></span>
                <Link href={`/products?category=${category.id}`}>{category.name}</Link>
              </>
            )}
            <span className="breadcrumb-sep"><FiChevronRight size={12} /></span>
            <span>{product.partNumber}</span>
          </div>
        </div>
      </div>

      <div className="container">
        <div className="pd-layout">
          {/* Product Image */}
          <div className="pd-image-section">
            <div className="pd-image">
              <FiPackage size={80} />
            </div>
            <div className="pd-image-badges">
              <span className="badge badge-info">{product.manufacturer}</span>
              <span className={`badge badge-${stockStatus.class === 'in-stock' ? 'success' : stockStatus.class === 'low-stock' ? 'warning' : 'danger'}`}>
                {stockStatus.label}
              </span>
            </div>
          </div>

          {/* Product Info */}
          <div className="pd-info-section">
            <div className="pd-manufacturer">{product.manufacturer}</div>
            <h1 className="pd-title">{product.name}</h1>
            <div className="pd-part-number">
              Part #: <span>{product.partNumber}</span>
            </div>
            <p className="pd-description">{product.description}</p>

            {/* Stock */}
            <div className="pd-stock">
              <div className="stock-indicator">
                <span className={`stock-dot ${stockStatus.class}`}></span>
                <span>{stockStatus.label}</span>
              </div>
              <span className="pd-stock-count">{formatStock(product.stock)} available</span>
            </div>

            {/* Pricing Tiers */}
            <div className="pd-pricing">
              <h3>Volume Pricing</h3>
              <div className="table-wrapper">
                <table>
                  <thead>
                    <tr>
                      <th>Quantity</th>
                      <th>Unit Price</th>
                      <th>Extended</th>
                    </tr>
                  </thead>
                  <tbody>
                    {product.priceTiers?.map((tier, i) => (
                      <tr key={i} className={quantity >= tier.qty ? 'active-tier' : ''}>
                        <td>{tier.qty}+</td>
                        <td>${tier.price.toFixed(4)}</td>
                        <td>${(tier.price * tier.qty).toFixed(2)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>

            {/* Add to Cart */}
            <div className="pd-cart-section">
              <div className="pd-price-display">
                <span className="pd-current-price">${unitPrice.toFixed(4)}</span>
                <span className="pd-price-label">per unit</span>
              </div>
              <div className="pd-cart-controls">
                <div className="pd-qty-control">
                  <button onClick={() => setQuantity(q => Math.max(1, q - 1))}>−</button>
                  <input
                    type="number"
                    value={quantity}
                    onChange={e => setQuantity(Math.max(1, parseInt(e.target.value) || 1))}
                    min="1"
                    id="product-quantity-input"
                  />
                  <button onClick={() => setQuantity(q => q + 1)}>+</button>
                </div>
                <div className="pd-line-total">
                  Subtotal: <strong>${lineTotal.toFixed(2)}</strong>
                </div>
                <button
                  className="btn btn-primary btn-lg"
                  onClick={handleAddToCart}
                  id="product-add-to-cart-btn"
                  style={{ width: '100%' }}
                >
                  <FiShoppingCart /> Add to Cart
                </button>
              </div>
            </div>

            {/* Datasheet */}
            <a href={product.datasheet} className="btn btn-secondary" style={{ marginTop: '12px' }}>
              <FiDownload /> Download Datasheet
            </a>
          </div>
        </div>

        {/* Specifications */}
        <section className="pd-specs-section">
          <h2 className="section-title">Specifications</h2>
          <div className="pd-specs-grid">
            {Object.entries(product.specs).map(([key, value]) => (
              <div key={key} className="pd-spec-item">
                <dt>{key}</dt>
                <dd>{value}</dd>
              </div>
            ))}
          </div>
        </section>

        {/* Related Products */}
        {relatedProducts.length > 0 && (
          <section className="section">
            <h2 className="section-title">Related Products</h2>
            <div className="products-grid">
              {relatedProducts.map(p => (
                <ProductCard key={p.id} product={p} />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  );
}
