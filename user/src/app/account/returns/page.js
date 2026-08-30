'use client';

const returns = [
  { id: 'ELV-R-1012', orderId: 'ELV-SO-10029', date: '2026-08-16', reason: 'Wrong component received', status: 'open' },
  { id: 'ELV-R-1008', orderId: 'ELV-SO-9987', date: '2026-07-30', reason: 'Damaged during shipping', status: 'resolved' },
  { id: 'ELV-R-1005', orderId: 'ELV-SO-9945', date: '2026-07-18', reason: 'Quantity mismatch', status: 'pending' },
];

export default function ReturnsPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>Returns and Order Issues</h2>
        <p>Track return requests and resolve order-related issues.</p>
      </div>

      <div className="acct-actions" style={{ marginBottom: 24 }}>
        <button className="acct-btn acct-btn-primary">File New Return</button>
      </div>

      <div className="account-box">
        <div className="acct-table-wrap">
          <table className="acct-table">
            <thead>
              <tr>
                <th>Return #</th>
                <th>Order #</th>
                <th>Date Filed</th>
                <th>Reason</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              {returns.map(r => (
                <tr key={r.id}>
                  <td><a href="#">{r.id}</a></td>
                  <td><a href="#">{r.orderId}</a></td>
                  <td>{r.date}</td>
                  <td>{r.reason}</td>
                  <td><span className={`acct-status acct-status--${r.status}`}>{r.status}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </>
  );
}
