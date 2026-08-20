'use client';

import { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { FiSearch, FiShoppingCart, FiUser, FiPackage } from 'react-icons/fi';
import { useCart } from '@/context/CartContext';
import { useMarketplaceAuth } from '@/context/MarketplaceAuthContext';
import { searchProducts } from '@/data/products';
import BrandWordmark from '@/components/BrandWordmark/BrandWordmark';
import './Header.css';

export default function Header() {
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState([]);
  const [showDropdown, setShowDropdown] = useState(false);
  const searchRef = useRef(null);
  const router = useRouter();
  const { getItemCount } = useCart();
  const { isAuthenticated, user } = useMarketplaceAuth();
  const itemCount = getItemCount();

  useEffect(() => {
    if (searchQuery.trim().length >= 2) {
      const results = searchProducts(searchQuery).slice(0, 6);
      setSearchResults(results);
      setShowDropdown(true);
    } else {
      setSearchResults([]);
      setShowDropdown(false);
    }
  }, [searchQuery]);

  useEffect(() => {
    function handleClickOutside(e) {
      if (searchRef.current && !searchRef.current.contains(e.target)) {
        setShowDropdown(false);
      }
    }
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  function handleSearch(e) {
    e.preventDefault();
    if (searchQuery.trim()) {
      setShowDropdown(false);
      router.push(`/search?q=${encodeURIComponent(searchQuery.trim())}`);
    }
  }

  function handleResultClick(productId) {
    setShowDropdown(false);
    setSearchQuery('');
    router.push(`/products/${productId}`);
  }

  return (
    <header className="header">
      <div className="header-main">
        <div className="container">
          <BrandWordmark size="lg" className="header-brand" />

          <div className="header-search" ref={searchRef}>
            <form onSubmit={handleSearch}>
              <div className="search-wrapper">
                <input
                  type="text"
                  placeholder="Search part numbers, keywords, or manufacturers..."
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  id="header-search-input"
                />
                <button type="submit" className="search-btn" id="header-search-btn">
                  <FiSearch size={18} />
                </button>
              </div>
            </form>

            {showDropdown && searchResults.length > 0 && (
              <div className="search-dropdown">
                {searchResults.map(product => (
                  <div
                    key={product.id}
                    className="search-result-item"
                    onClick={() => handleResultClick(product.id)}
                  >
                    <div className="search-result-icon">
                      <FiPackage />
                    </div>
                    <div className="search-result-info">
                      <h4>{product.name}</h4>
                      <p>{product.manufacturer} · {product.partNumber}</p>
                    </div>
                    <div className="search-result-price">
                      ${product.price.toFixed(2)}
                    </div>
                  </div>
                ))}
                <div
                  className="search-result-item"
                  onClick={() => {
                    setShowDropdown(false);
                    router.push(`/search?q=${encodeURIComponent(searchQuery)}`);
                  }}
                  style={{ justifyContent: 'center', color: 'var(--accent-primary)', fontWeight: 600, fontSize: '0.85rem' }}
                >
                  View all results for &quot;{searchQuery}&quot;
                </div>
              </div>
            )}
          </div>

          <div className="header-actions">
            <Link href={isAuthenticated ? "/account" : "/login"} className="header-action-btn" id="header-account-btn">
              <FiUser size={20} />
              <span>{isAuthenticated ? user.firstName : 'Account'}</span>
            </Link>
            <Link href="/cart" className="header-action-btn" id="header-cart-btn">
              <FiShoppingCart size={20} />
              {itemCount > 0 && <span className="cart-badge">{itemCount}</span>}
              <span>Cart</span>
            </Link>
          </div>
        </div>
      </div>

      <nav className="header-nav">
        <div className="container">
          <Link href="/products" className="nav-item">Products</Link>
          <Link href="/manufacturers" className="nav-item">Manufacturers</Link>
          <Link href="/resources" className="nav-item">Resources</Link>
        </div>
      </nav>
    </header>
  );
}
