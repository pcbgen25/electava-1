'use client';

import Link from 'next/link';
import { FiShoppingCart, FiPackage } from 'react-icons/fi';
import { useCart } from '@/context/CartContext';
import { getStockStatus, formatStock } from '@/data/products';
import './ProductCard.css';

export default function ProductCard({ product }) {
  const { addItem } = useCart();
  const stockStatus = getStockStatus(product.stock);

  return (
    <div className="product-card">
      <Link href={`/products/${product.id}`} style={{ textDecoration: 'none', color: 'inherit' }}>
        <div className="product-card-image">
          <FiPackage />
        </div>
        <div className="product-card-manufacturer">{product.manufacturer}</div>
        <div className="product-card-name">{product.name}</div>
        <div className="product-card-part">{product.partNumber}</div>
        <div className="product-card-price">${product.price.toFixed(2)}</div>
        <div className="product-card-stock">
          <span className={`stock-dot ${stockStatus.class}`}></span>
          <span style={{ color: stockStatus.class === 'in-stock' ? 'var(--accent-success)' : stockStatus.class === 'low-stock' ? 'var(--accent-warning)' : 'var(--accent-danger)' }}>
            {stockStatus.label}
          </span>
          <span style={{ color: 'var(--text-tertiary)' }}>({formatStock(product.stock)})</span>
        </div>
      </Link>
      <div className="product-card-actions">
        <button
          className="btn btn-primary btn-sm"
          onClick={(e) => {
            e.preventDefault();
            addItem(product);
          }}
          id={`add-to-cart-${product.id}`}
        >
          <FiShoppingCart size={14} /> Add to Cart
        </button>
      </div>
    </div>
  );
}
