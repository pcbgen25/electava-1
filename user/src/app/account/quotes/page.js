'use client';

const quotes = [
  { id: 'ELV-Q-2041', date: '2026-08-14', items: 5, total: '₹24,500.00', status: 'active', expires: '2026-09-14' },
  { id: 'ELV-Q-2038', date: '2026-08-05', items: 12, total: '₹68,200.00', status: 'approved', expires: '2026-09-05' },
  { id: 'ELV-Q-2030', date: '2026-07-20', items: 3, total: '₹9,800.00', status: 'expired', expires: '2026-08-20' },
  { id: 'ELV-Q-2025', date: '2026-07-10', items: 8, total: '₹42,100.00', status: 'pending', expires: '2026-08-10' },
];

export default function QuotesPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Quotes</h2>
        <p>View and manage your quote requests and pricing agreements.</p>
      </div>

      <div className="acct-filter-bar">
        <select defaultValue="all">
          <option value="all">All Statuses</option>
          <option value="active">Active</option>
          <option value="approved">Approved</option>
          <option value="pending">Pending</option>
          <option value="expired">Expired</option>
        </select>
        <button className="acct-btn acct-btn-primary">Request New Quote</button>
      </div>

      <div className="account-box">
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Quote #</th>
                <th>Date Requested</th>
                <th>Items</th>
                <th>Total Value</th>
                <th>Status</th>
                <th>Expires</th>
              </tr>
            </thead>
            <tbody>
              {quotes.map(q => (
                <tr key={q.id}>
                  <td><a href="#">{q.id}</a></td>
                  <td>{q.date}</td>
                  <td>{q.items}</td>
                  <td>{q.total}</td>
                  <td><span className={`acct-status acct-status--${q.status}`}>{q.status}</span></td>
                  <td>{q.expires}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
