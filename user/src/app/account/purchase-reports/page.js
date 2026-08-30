'use client';

const summaryCards = [
  { label: 'Total Spend (2026)', value: '₹1,28,384' },
  { label: 'Total Orders', value: '14' },
  { label: 'Avg Order Value', value: '₹9,170' },
  { label: 'Most Ordered', value: 'Capacitors' },
];

const monthlyData = [
  { month: 'August 2026', orders: 2, spend: '₹25,134', topCategory: 'ICs & Microcontrollers' },
  { month: 'July 2026', orders: 3, spend: '₹45,784', topCategory: 'Capacitors' },
  { month: 'June 2026', orders: 2, spend: '₹28,202', topCategory: 'Connectors' },
  { month: 'May 2026', orders: 3, spend: '₹29,264', topCategory: 'Resistors' },
  { month: 'April 2026', orders: 2, spend: '₹18,450', topCategory: 'ICs & Microcontrollers' },
  { month: 'March 2026', orders: 2, spend: '₹12,550', topCategory: 'Capacitors' },
];

export default function PurchaseReportsPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Purchase Reports</h2>
        <p>Spending summaries, trends, and exportable reports.</p>
      </div>

      <div className="acct-card-grid" style={{ marginBottom: 32 }}>
        {summaryCards.map(card => (
          <div key={card.label} className="acct-card" style={{ textAlign: 'center' }}>
            <p style={{ marginBottom: 8 }}>{card.label}</p>
            <h3 style={{ fontSize: '1.5rem', color: 'var(--accent-primary)' }}>{card.value}</h3>
          </div>
        ))}
      </div>

      <div className="account-box" style={{ marginBottom: 24 }}>
        <h3 style={{ color: 'var(--text-primary)', fontWeight: 700, marginBottom: 16 }}>Spending Trend</h3>
        <div className="acct-chart-placeholder">
          Spending trend chart — coming soon
        </div>
      </div>

      <div className="account-box">
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <h3 style={{ color: 'var(--text-primary)', fontWeight: 700, margin: 0 }}>Monthly Breakdown</h3>
          <button className="acct-btn acct-btn-secondary">Export Report</button>
        </div>
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Month</th>
                <th>Orders</th>
                <th>Spend</th>
                <th>Top Category</th>
              </tr>
            </thead>
            <tbody>
              {monthlyData.map(m => (
                <tr key={m.month}>
                  <td>{m.month}</td>
                  <td>{m.orders}</td>
                  <td style={{ fontWeight: 600 }}>{m.spend}</td>
                  <td>{m.topCategory}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
