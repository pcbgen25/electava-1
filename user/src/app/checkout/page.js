'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { FiCheck, FiPackage } from 'react-icons/fi';
import { useCart } from '@/context/CartContext';
import './checkout.css';

export default function CheckoutPage() {
  const { items, getTotal, clearCart } = useCart();
  const { subtotal, shipping, tax, total } = getTotal();
  const router = useRouter();
  const [step, setStep] = useState(1);
  const [orderPlaced, setOrderPlaced] = useState(false);
  const [orderNumber] = useState(`ELV-${Date.now().toString(36).toUpperCase()}`);

  const [shippingInfo, setShippingInfo] = useState({
    firstName: '', lastName: '', email: '', phone: '',
    address: '', city: '', state: '', zip: '', country: 'US',
  });

  const [paymentInfo, setPaymentInfo] = useState({
    cardNumber: '', cardName: '', expiry: '', cvv: '',
  });

  if (items.length === 0 && !orderPlaced) {
    router.push('/cart');
    return null;
  }

  function handlePlaceOrder() {
    setOrderPlaced(true);
    setStep(4);
    clearCart();
  }

  if (orderPlaced) {
    return (
      <div className="checkout-page">
        <div className="container">
          <div className="order-confirmation">
            <div className="order-success-icon">
              <FiCheck size={48} />
            </div>
            <h1>Order Confirmed!</h1>
            <p className="order-number">Order #{orderNumber}</p>
            <p style={{ color: 'var(--text-secondary)', maxWidth: '500px', margin: '16px auto' }}>
              Thank you for your order! You&apos;ll receive a confirmation email shortly with tracking details.
            </p>
            <div style={{ display: 'flex', gap: '12px', justifyContent: 'center', marginTop: '24px' }}>
              <Link href="/products" className="btn btn-primary">Continue Shopping</Link>
              <Link href="/account" className="btn btn-secondary">View Orders</Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="checkout-page">
      <div className="page-title-bar">
        <div className="container">
          <h1>Checkout</h1>
        </div>
      </div>

      <div className="container">
        {/* Steps */}
        <div className="steps" style={{ justifyContent: 'center', padding: '24px 0' }}>
          <div className={`step ${step >= 1 ? (step > 1 ? 'completed' : 'active') : ''}`}>1. Shipping</div>
          <div className={`step-line ${step > 1 ? 'completed' : ''}`}></div>
          <div className={`step ${step >= 2 ? (step > 2 ? 'completed' : 'active') : ''}`}>2. Payment</div>
          <div className={`step-line ${step > 2 ? 'completed' : ''}`}></div>
          <div className={`step ${step >= 3 ? 'active' : ''}`}>3. Review</div>
        </div>

        <div className="checkout-layout">
          <div className="checkout-form-section">
            {/* Step 1: Shipping */}
            {step === 1 && (
              <div className="checkout-step animate-fade-in">
                <h2>Shipping Information</h2>
                <div className="checkout-form-grid">
                  <div className="form-group">
                    <label>First Name</label>
                    <input className="input-field" value={shippingInfo.firstName}
                      onChange={e => setShippingInfo({...shippingInfo, firstName: e.target.value})} placeholder="John" />
                  </div>
                  <div className="form-group">
                    <label>Last Name</label>
                    <input className="input-field" value={shippingInfo.lastName}
                      onChange={e => setShippingInfo({...shippingInfo, lastName: e.target.value})} placeholder="Doe" />
                  </div>
                  <div className="form-group">
                    <label>Email</label>
                    <input className="input-field" type="email" value={shippingInfo.email}
                      onChange={e => setShippingInfo({...shippingInfo, email: e.target.value})} placeholder="john@example.com" />
                  </div>
                  <div className="form-group">
                    <label>Phone</label>
                    <input className="input-field" value={shippingInfo.phone}
                      onChange={e => setShippingInfo({...shippingInfo, phone: e.target.value})} placeholder="+1 (555) 000-0000" />
                  </div>
                  <div className="form-group" style={{ gridColumn: 'span 2' }}>
                    <label>Address</label>
                    <input className="input-field" value={shippingInfo.address}
                      onChange={e => setShippingInfo({...shippingInfo, address: e.target.value})} placeholder="123 Main St" />
                  </div>
                  <div className="form-group">
                    <label>City</label>
                    <input className="input-field" value={shippingInfo.city}
                      onChange={e => setShippingInfo({...shippingInfo, city: e.target.value})} placeholder="San Francisco" />
                  </div>
                  <div className="form-group">
                    <label>State</label>
                    <input className="input-field" value={shippingInfo.state}
                      onChange={e => setShippingInfo({...shippingInfo, state: e.target.value})} placeholder="CA" />
                  </div>
                  <div className="form-group">
                    <label>ZIP Code</label>
                    <input className="input-field" value={shippingInfo.zip}
                      onChange={e => setShippingInfo({...shippingInfo, zip: e.target.value})} placeholder="94105" />
                  </div>
                  <div className="form-group">
                    <label>Country</label>
                    <select className="input-field" value={shippingInfo.country}
                      onChange={e => setShippingInfo({...shippingInfo, country: e.target.value})}>
                      <option value="US">United States</option>
                      <option value="CA">Canada</option>
                      <option value="UK">United Kingdom</option>
                      <option value="DE">Germany</option>
                      <option value="IN">India</option>
                    </select>
                  </div>
                </div>
                <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: '24px' }}>
                  <button className="btn btn-primary btn-lg" onClick={() => setStep(2)}>Continue to Payment →</button>
                </div>
              </div>
            )}

            {/* Step 2: Payment */}
            {step === 2 && (
              <div className="checkout-step animate-fade-in">
                <h2>Payment Information</h2>
                <div className="checkout-form-grid">
                  <div className="form-group" style={{ gridColumn: 'span 2' }}>
                    <label>Card Number</label>
                    <input className="input-field" value={paymentInfo.cardNumber}
                      onChange={e => setPaymentInfo({...paymentInfo, cardNumber: e.target.value})} placeholder="4242 4242 4242 4242" />
                  </div>
                  <div className="form-group" style={{ gridColumn: 'span 2' }}>
                    <label>Cardholder Name</label>
                    <input className="input-field" value={paymentInfo.cardName}
                      onChange={e => setPaymentInfo({...paymentInfo, cardName: e.target.value})} placeholder="John Doe" />
                  </div>
                  <div className="form-group">
                    <label>Expiry Date</label>
                    <input className="input-field" value={paymentInfo.expiry}
                      onChange={e => setPaymentInfo({...paymentInfo, expiry: e.target.value})} placeholder="MM/YY" />
                  </div>
                  <div className="form-group">
                    <label>CVV</label>
                    <input className="input-field" type="password" value={paymentInfo.cvv}
                      onChange={e => setPaymentInfo({...paymentInfo, cvv: e.target.value})} placeholder="•••" />
                  </div>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '24px' }}>
                  <button className="btn btn-secondary" onClick={() => setStep(1)}>← Back</button>
                  <button className="btn btn-primary btn-lg" onClick={() => setStep(3)}>Review Order →</button>
                </div>
              </div>
            )}

            {/* Step 3: Review */}
            {step === 3 && (
              <div className="checkout-step animate-fade-in">
                <h2>Review Your Order</h2>
                <div className="review-section">
                  <h4>Shipping Address</h4>
                  <p>{shippingInfo.firstName} {shippingInfo.lastName}</p>
                  <p>{shippingInfo.address}</p>
                  <p>{shippingInfo.city}, {shippingInfo.state} {shippingInfo.zip}</p>
                </div>
                <div className="review-section">
                  <h4>Payment Method</h4>
                  <p>Card ending in {paymentInfo.cardNumber.slice(-4) || '••••'}</p>
                </div>
                <div className="review-section">
                  <h4>Items ({items.length})</h4>
                  {items.map(item => (
                    <div key={item.id} className="review-item">
                      <FiPackage style={{ color: 'var(--accent-primary)' }} />
                      <span className="review-item-name">{item.name}</span>
                      <span className="review-item-qty">×{item.quantity}</span>
                      <span className="review-item-price">${(item.price * item.quantity).toFixed(2)}</span>
                    </div>
                  ))}
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', marginTop: '24px' }}>
                  <button className="btn btn-secondary" onClick={() => setStep(2)}>← Back</button>
                  <button className="btn btn-primary btn-lg" onClick={handlePlaceOrder}>Place Order — ${total.toFixed(2)}</button>
                </div>
              </div>
            )}
          </div>

          {/* Order Summary Sidebar */}
          <div className="checkout-summary">
            <div className="cart-summary-card">
              <h3>Order Summary</h3>
              <div className="cart-summary-row"><span>Subtotal ({items.length} items)</span><span>${subtotal.toFixed(2)}</span></div>
              <div className="cart-summary-row"><span>Shipping</span><span>{shipping === 0 ? 'FREE' : `$${shipping.toFixed(2)}`}</span></div>
              <div className="cart-summary-row"><span>Tax</span><span>${tax.toFixed(2)}</span></div>
              <hr className="divider" />
              <div className="cart-summary-row cart-summary-total"><span>Total</span><span>${total.toFixed(2)}</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
