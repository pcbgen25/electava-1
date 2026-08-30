'use client';

const orders = [
  { id: 'ELV-SO-10042', date: '2026-08-18', items: 5, subtotal: '₹12,400', tax: '₹2,232', total: '₹14,632', status: 'processing' },
  { id: 'ELV-SO-10029', date: '2026-08-12', items: 3, subtotal: '₹8,900', tax: '₹1,602', total: '₹10,502', status: 'shipped' },
  { id: 'ELV-SO-9987', date: '2026-07-28', items: 12, subtotal: '₹34,200', tax: '₹6,156', total: '₹40,356', status: 'delivered' },
  { id: 'ELV-SO-9945', date: '2026-07-15', items: 2, subtotal: '₹4,600', tax: '₹828', total: '₹5,428', status: 'delivered' },
  { id: 'ELV-SO-9901', date: '2026-06-30', items: 8, subtotal: '₹22,100', tax: '₹3,978', total: '₹26,078', status: 'delivered' },
  { id: 'ELV-SO-9856', date: '2026-06-12', items: 1, subtotal: '₹1,800', tax: '₹324', total: '₹2,124', status: 'cancelled' },
  { id: 'ELV-SO-9810', date: '2026-05-20', items: 6, subtotal: '₹15,600', tax: '₹2,808', total: '₹18,408', status: 'delivered' },
  { id: 'ELV-SO-9775', date: '2026-05-05', items: 4, subtotal: '₹9,200', tax: '₹1,656', total: '₹10,856', status: 'delivered' },
];

export default function OrderHistoryPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Order History</h2>
        <p>Complete log of all your past orders.</p>
      </div>

      <div className="acct-filter-bar">
        <select defaultValue="all">
          <option value="all">All Statuses</option>
          <option value="processing">Processing</option>
          <option value="shipped">Shipped</option>
          <option value="delivered">Delivered</option>
          <option value="cancelled">Cancelled</option>
        </select>
        <input type="date" defaultValue="2026-01-01" />
        <input type="date" defaultValue="2026-08-21" />
        <button className="acct-btn acct-btn-secondary">Export CSV</button>
      </div>

      <div className="account-box">
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Date</th>
                <th>Items</th>
                <th>Subtotal</th>
                <th>Tax</th>
                <th>Total</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {orders.map(o => (
                <tr key={o.id}>
                  <td><a href="#">{o.id}</a></td>
                  <td>{o.date}</td>
                  <td>{o.items}</td>
                  <td>{o.subtotal}</td>
                  <td>{o.tax}</td>
                  <td style={{ fontWeight: 600 }}>{o.total}</td>
                  <td><span className={`acct-status acct-status--${o.status}`}>{o.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
