'use client';

import Link from 'next/link';
import { FiTrash2, FiShoppingCart, FiPackage } from 'react-icons/fi';
import { useCart } from '@/context/CartContext';
import './cart.css';

export default function CartPage() {
  const { items, removeItem, updateQuantity, clearCart, getTotal } = useCart();
  const { subtotal, shipping, tax, total } = getTotal();

  if (items.length === 0) {
    return (
      <div className="cart-page">
        <div className="page-title-bar">
          <div className="container">
            <h1><FiShoppingCart style={{ marginRight: '8px' }} /> Shopping Cart</h1>
          </div>
        </div>
        <div className="container">
          <div className="empty-state" style={{ padding: '80px 0' }}>
            <FiShoppingCart size={48} style={{ marginBottom: '16px', opacity: 0.4 }} />
            <h3>Your cart is empty</h3>
            <p>Browse our catalog and add components to your cart.</p>
            <Link href="/products" className="btn btn-primary" style={{ marginTop: '16px' }}>
              Browse Products
            </Link>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="cart-page">
      <div className="page-title-bar">
        <div className="container">
          <div className="breadcrumb">
            <Link href="/">Home</Link>
            <span className="breadcrumb-sep">/</span>
            <span>Cart</span>
          </div>
          <h1><FiShoppingCart style={{ marginRight: '8px', verticalAlign: 'middle' }} /> Shopping Cart ({items.length} items)</h1>
        </div>
      </div>

      <div className="container">
        <div className="cart-layout">
          {/* Cart Items */}
          <div className="cart-items-section">
            <div className="table-wrapper">
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Unit Price</th>
                    <th>Quantity</th>
                    <th>Subtotal</th>
                    <th></th>
                  </tr>
                </thead>
                <tbody>
                  {items.map(item => {
                    const tier = item.priceTiers
                      ?.slice()
                      .sort((a, b) => b.qty - a.qty)
                      .find(t => item.quantity >= t.qty);
                    const unitPrice = tier ? tier.price : item.price;
                    return (
                      <tr key={item.id}>
                        <td>
                          <div className="cart-product-info">
                            <div className="cart-product-icon">
                              <FiPackage />
                            </div>
                            <div>
                              <Link href={`/products/${item.id}`} className="cart-product-name">
                                {item.name}
                              </Link>
                              <div className="cart-product-part">{item.partNumber}</div>
                              <div className="cart-product-mfr">{item.manufacturer}</div>
                            </div>
                          </div>
                        </td>
                        <td>
                          <span style={{ fontWeight: 600 }}>${unitPrice.toFixed(4)}</span>
                        </td>
                        <td>
                          <div className="cart-qty-control">
                            <button onClick={() => updateQuantity(item.id, item.quantity - 1)}>−</button>
                            <input
                              type="number"
                              value={item.quantity}
                              onChange={e => updateQuantity(item.id, parseInt(e.target.value) || 1)}
                              min="1"
                            />
                            <button onClick={() => updateQuantity(item.id, item.quantity + 1)}>+</button>
                          </div>
                        </td>
                        <td>
                          <strong style={{ color: 'var(--accent-primary)' }}>${(unitPrice * item.quantity).toFixed(2)}</strong>
                        </td>
                        <td>
                          <button
                            className="cart-remove-btn"
                            onClick={() => removeItem(item.id)}
                            title="Remove"
                          >
                            <FiTrash2 size={16} />
                          </button>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '16px' }}>
              <Link href="/products" className="btn btn-secondary">← Continue Shopping</Link>
              <button className="btn btn-danger btn-sm" onClick={clearCart}>Clear Cart</button>
            </div>
          </div>

          {/* Order Summary */}
          <div className="cart-summary">
            <div className="cart-summary-card">
              <h3>Order Summary</h3>
              <div className="cart-summary-row">
                <span>Subtotal</span>
                <span>${subtotal.toFixed(2)}</span>
              </div>
              <div className="cart-summary-row">
                <span>Shipping</span>
                <span>{shipping === 0 ? 'FREE' : `$${shipping.toFixed(2)}`}</span>
              </div>
              <div className="cart-summary-row">
                <span>Estimated Tax</span>
                <span>${tax.toFixed(2)}</span>
              </div>
              <hr className="divider" />
              <div className="cart-summary-row cart-summary-total">
                <span>Total</span>
                <span>${total.toFixed(2)}</span>
              </div>
              {shipping === 0 && (
                <div className="cart-free-shipping">
                  ✓ You qualify for FREE shipping!
                </div>
              )}
              <Link href="/checkout" className="btn btn-primary btn-lg" style={{ width: '100%', marginTop: '16px' }}>
                Proceed to Checkout
              </Link>
              <p style={{ fontSize: '0.75rem', color: 'var(--text-tertiary)', textAlign: 'center', marginTop: '8px' }}>
                Secure checkout powered by Electava
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
