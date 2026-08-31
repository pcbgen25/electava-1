'use client';

import { useState, useMemo, useEffect } from 'react';
import { useSearchParams } from 'next/navigation';
import Link from 'next/link';
import { FiGrid, FiList, FiFilter } from 'react-icons/fi';
import ProductCard from '@/components/ProductCard/ProductCard';
import { getApiUrl } from '@/lib/api';
import './products.css';

export default function ProductsPage() {
  const searchParams = useSearchParams();
  const categoryParam = searchParams.get('category');
  const subcategoryParam = searchParams.get('subcategory');

  const [products, setProducts] = useState([]);
  const [categories, setCategories] = useState([]);
  const [manufacturers, setManufacturers] = useState([]);

  useEffect(() => {
    Promise.all([
      fetch(getApiUrl('/components')).then(r => r.json()).catch(() => []),
      fetch(getApiUrl('/categories')).then(r => r.json()).catch(() => []),
      fetch(getApiUrl('/manufacturers')).then(r => r.json()).catch(() => []),
    ]).then(([prodData, catData, manData]) => {
      setProducts(Array.isArray(prodData) ? prodData : []);
      setCategories(Array.isArray(catData) ? catData : []);
      setManufacturers(Array.isArray(manData) ? manData.map(m => m.name) : []);
    });
  }, []);


  const getCategoryById = (id) => categories.find(c => c.id === id);

  const [selectedCategory, setSelectedCategory] = useState(categoryParam || '');
  const [selectedSubcategory, setSelectedSubcategory] = useState(subcategoryParam || '');
  const [selectedManufacturer, setSelectedManufacturer] = useState('');
  const [sortBy, setSortBy] = useState('name');
  const [priceRange, setPriceRange] = useState([0, 100]);
  const [currentPage, setCurrentPage] = useState(1);
  const [viewMode, setViewMode] = useState('grid');
  const itemsPerPage = 12;

  const filteredProducts = useMemo(() => {
    let result = [...products];

    if (selectedCategory) {
      result = result.filter(p => p.category === selectedCategory);
    }
    if (selectedSubcategory) {
      result = result.filter(p => p.subcategory === selectedSubcategory);
    }
    if (selectedManufacturer) {
      result = result.filter(p => p.manufacturer === selectedManufacturer);
    }
    result = result.filter(p => p.price >= priceRange[0] && p.price <= priceRange[1]);

    switch (sortBy) {
      case 'price-low':
        result.sort((a, b) => a.price - b.price);
        break;
      case 'price-high':
        result.sort((a, b) => b.price - a.price);
        break;
      case 'stock':
        result.sort((a, b) => b.stock - a.stock);
        break;
      default:
        result.sort((a, b) => a.name.localeCompare(b.name));
    }

    return result;
  }, [selectedCategory, selectedSubcategory, selectedManufacturer, sortBy, priceRange]);

  const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
  const paginatedProducts = filteredProducts.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  const currentCategory = getCategoryById(selectedCategory);

  function clearFilters() {
    setSelectedCategory('');
    setSelectedSubcategory('');
    setSelectedManufacturer('');
    setPriceRange([0, 100]);
    setCurrentPage(1);
  }

  return (
    <div className="products-page">
      <div className="page-title-bar">
        <div className="container">
          <div className="breadcrumb">
            <Link href="/">Home</Link>
            <span className="breadcrumb-sep">/</span>
            <span>Products</span>
            {currentCategory && (
              <>
                <span className="breadcrumb-sep">/</span>
                <span>{currentCategory.name}</span>
              </>
            )}
          </div>
          <h1>{currentCategory ? currentCategory.name : 'All Products'}</h1>
          <p style={{ color: 'var(--text-secondary)', marginTop: '4px', fontSize: '0.9rem' }}>
            {filteredProducts.length} products found
          </p>
        </div>
      </div>

      <div className="container">
        <div className="products-layout">
          {/* Sidebar Filters */}
          <aside className="products-sidebar">
            <div className="filter-section">
              <h4><FiFilter size={14} /> Filters</h4>
              <button className="btn btn-sm btn-secondary" onClick={clearFilters} style={{ marginTop: '8px', width: '100%' }}>
                Clear All
              </button>
            </div>

            <div className="filter-section">
              <h4>Category</h4>
              <div className="filter-options">
                <label className="filter-option">
                  <input
                    type="radio"
                    name="category"
                    value=""
                    checked={selectedCategory === ''}
                    onChange={() => { setSelectedCategory(''); setSelectedSubcategory(''); setCurrentPage(1); }}
                  />
                  <span>All Categories</span>
                </label>
                {categories.map(cat => (
                  <label key={cat.id} className="filter-option">
                    <input
                      type="radio"
                      name="category"
                      value={cat.id}
                      checked={selectedCategory === cat.id}
                      onChange={() => { setSelectedCategory(cat.id); setSelectedSubcategory(''); setCurrentPage(1); }}
                    />
                    <span>{cat.icon} {cat.name}</span>
                  </label>
                ))}
              </div>
            </div>

            {currentCategory && (
              <div className="filter-section">
                <h4>Subcategory</h4>
                <div className="filter-options">
                  <label className="filter-option">
                    <input
                      type="radio"
                      name="subcategory"
                      value=""
                      checked={selectedSubcategory === ''}
                      onChange={() => { setSelectedSubcategory(''); setCurrentPage(1); }}
                    />
                    <span>All</span>
                  </label>
                  {currentCategory.subcategories.map(sub => (
                    <label key={sub.id} className="filter-option">
                      <input
                        type="radio"
                        name="subcategory"
                        value={sub.id}
                        checked={selectedSubcategory === sub.id}
                        onChange={() => { setSelectedSubcategory(sub.id); setCurrentPage(1); }}
                      />
                      <span>{sub.name}</span>
                    </label>
                  ))}
                </div>
              </div>
            )}

            <div className="filter-section">
              <h4>Manufacturer</h4>
              <div className="filter-options">
                <label className="filter-option">
                  <input
                    type="radio"
                    name="manufacturer"
                    value=""
                    checked={selectedManufacturer === ''}
                    onChange={() => { setSelectedManufacturer(''); setCurrentPage(1); }}
                  />
                  <span>All Manufacturers</span>
                </label>
                {manufacturers.map(m => (
                  <label key={m} className="filter-option">
                    <input
                      type="radio"
                      name="manufacturer"
                      value={m}
                      checked={selectedManufacturer === m}
                      onChange={() => { setSelectedManufacturer(m); setCurrentPage(1); }}
                    />
                    <span>{m}</span>
                  </label>
                ))}
              </div>
            </div>

            <div className="filter-section">
              <h4>Price Range</h4>
              <div className="price-range">
                <input
                  type="number"
                  className="input-field"
                  placeholder="Min"
                  value={priceRange[0]}
                  onChange={e => setPriceRange([Number(e.target.value), priceRange[1]])}
                  style={{ fontSize: '0.82rem' }}
                />
                <span style={{ color: 'var(--text-tertiary)' }}>—</span>
                <input
                  type="number"
                  className="input-field"
                  placeholder="Max"
                  value={priceRange[1]}
                  onChange={e => setPriceRange([priceRange[0], Number(e.target.value)])}
                  style={{ fontSize: '0.82rem' }}
                />
              </div>
            </div>
          </aside>

          {/* Main Content */}
          <div className="products-main">
            {/* Toolbar */}
            <div className="products-toolbar">
              <div className="products-toolbar-left">
                <select
                  className="input-field"
                  value={sortBy}
                  onChange={e => setSortBy(e.target.value)}
                  style={{ width: 'auto', fontSize: '0.82rem' }}
                >
                  <option value="name">Sort by Name</option>
                  <option value="price-low">Price: Low to High</option>
                  <option value="price-high">Price: High to Low</option>
                  <option value="stock">Stock Availability</option>
                </select>
              </div>
              <div className="products-toolbar-right">
                <button
                  className={`view-toggle-btn ${viewMode === 'grid' ? 'active' : ''}`}
                  onClick={() => setViewMode('grid')}
                >
                  <FiGrid size={16} />
                </button>
                <button
                  className={`view-toggle-btn ${viewMode === 'list' ? 'active' : ''}`}
                  onClick={() => setViewMode('list')}
                >
                  <FiList size={16} />
                </button>
              </div>
            </div>

            {/* Product Grid */}
            {paginatedProducts.length > 0 ? (
              <div className={viewMode === 'grid' ? 'products-grid' : 'products-list'}>
                {paginatedProducts.map(product => (
                  <ProductCard key={product.id} product={product} />
                ))}
              </div>
            ) : (
              <div className="empty-state">
                <FiFilter size={40} />
                <h3>No products found</h3>
                <p>Try adjusting your filters to find what you&apos;re looking for.</p>
                <button className="btn btn-primary" onClick={clearFilters}>Clear Filters</button>
              </div>
            )}

            {/* Pagination */}
            {totalPages > 1 && (
              <div className="pagination">
                <button
                  className="btn btn-secondary btn-sm"
                  disabled={currentPage === 1}
                  onClick={() => setCurrentPage(p => p - 1)}
                >
                  ← Previous
                </button>
                <div className="pagination-pages">
                  {Array.from({ length: totalPages }, (_, i) => i + 1).map(page => (
                    <button
                      key={page}
                      className={`pagination-page ${page === currentPage ? 'active' : ''}`}
                      onClick={() => setCurrentPage(page)}
                    >
                      {page}
                    </button>
                  ))}
                </div>
                <button
                  className="btn btn-secondary btn-sm"
                  disabled={currentPage === totalPages}
                  onClick={() => setCurrentPage(p => p + 1)}
                >
                  Next →
                </button>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
