'use client';

import { useState, useMemo } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { FiSearch } from 'react-icons/fi';
import { searchProducts } from '@/data/products';
import ProductCard from '@/components/ProductCard/ProductCard';

export default function SearchPage() {
  const searchParams = useSearchParams();
  const q = searchParams.get('q') || '';
  const [sortBy, setSortBy] = useState('relevance');

  const results = useMemo(() => {
    let found = searchProducts(q);
    if (sortBy === 'price-low') found.sort((a, b) => a.price - b.price);
    if (sortBy === 'price-high') found.sort((a, b) => b.price - a.price);
    return found;
  }, [q, sortBy]);

  return (
    <div>
      <div className="page-title-bar">
        <div className="container">
          <div className="breadcrumb">
            <Link href="/">Home</Link>
            <span className="breadcrumb-sep">/</span>
            <span>Search</span>
          </div>
          <h1>
            <FiSearch style={{ marginRight: '8px', verticalAlign: 'middle' }} />
            Search Results for &quot;{q}&quot;
          </h1>
          <p style={{ color: 'var(--text-secondary)', marginTop: '4px', fontSize: '0.9rem' }}>
            {results.length} results found
          </p>
        </div>
      </div>
      <div className="container" style={{ padding: '24px 24px' }}>
        <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: '16px' }}>
          <select
            className="input-field"
            value={sortBy}
            onChange={e => setSortBy(e.target.value)}
            style={{ width: 'auto', fontSize: '0.82rem' }}
          >
            <option value="relevance">Relevance</option>
            <option value="price-low">Price: Low to High</option>
            <option value="price-high">Price: High to Low</option>
          </select>
        </div>
        {results.length > 0 ? (
          <div className="products-grid" style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: '16px' }}>
            {results.map(product => (
              <ProductCard key={product.id} product={product} />
            ))}
          </div>
        ) : (
          <div className="empty-state">
            <FiSearch size={48} style={{ marginBottom: '16px', opacity: 0.4 }} />
            <h3>No results found</h3>
            <p>Try different keywords or browse our <Link href="/products">product catalog</Link>.</p>
          </div>
        )}
      </div>
    </div>
  );
}
