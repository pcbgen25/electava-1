'use client';

import { useState } from 'react';

const initialAlerts = [
  { id: 1, component: '100µF 25V Electrolytic', partNo: 'CAP-EL-100U-25V', type: 'Stock', current: 'In Stock (2,450)', threshold: 'Below 500', enabled: true },
  { id: 2, component: 'STM32F103C8T6', partNo: 'IC-STM32F103C8T6', type: 'Price', current: '₹185.00', threshold: 'Below ₹170.00', enabled: true },
  { id: 3, component: 'USB-C Connector 16-Pin', partNo: 'CON-USBC-16P', type: 'Stock', current: 'Out of Stock', threshold: 'Back in Stock', enabled: true },
  { id: 4, component: '10kΩ 0805 Resistor', partNo: 'RES-10K-0805', type: 'Price', current: '₹0.45', threshold: 'Below ₹0.35', enabled: false },
  { id: 5, component: 'LM7805 Voltage Regulator', partNo: 'IC-LM7805', type: 'Stock', current: 'In Stock (890)', threshold: 'Below 200', enabled: true },
];

export default function NotificationsPage() {
  const [alerts, setAlerts] = useState(initialAlerts);

  function toggleAlert(id) {
    setAlerts(prev => prev.map(a => a.id === id ? { ...a, enabled: !a.enabled } : a));
  }

  return (
    <>
      <div className="acct-page-header">
        <h2>Product Notifications</h2>
        <p>Manage stock and price alerts for components you are watching.</p>
      </div>

      <div className="acct-actions" style={{ marginBottom: 24 }}>
        <button className="acct-btn acct-btn-primary">+ Add Notification</button>
      </div>

      <div className="account-box">
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Component</th>
                <th>Part Number</th>
                <th>Alert Type</th>
                <th>Current Value</th>
                <th>Threshold</th>
                <th>Enabled</th>
              </tr>
            </thead>
            <tbody>
              {alerts.map(a => (
                <tr key={a.id}>
                  <td>{a.component}</td>
                  <td style={{ fontFamily: 'var(--font-mono)', fontSize: '0.85rem' }}>{a.partNo}</td>
                  <td><span className={`acct-status ${a.type === 'Stock' ? 'acct-status--active' : 'acct-status--processing'}`}>{a.type}</span></td>
                  <td>{a.current}</td>
                  <td>{a.threshold}</td>
                  <td>
                    <label className="acct-toggle">
                      <input type="checkbox" checked={a.enabled} onChange={() => toggleAlert(a.id)} />
                      <span className="acct-toggle-track" />
                    </label>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
