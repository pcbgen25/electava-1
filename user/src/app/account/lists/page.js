'use client';

import Link from 'next/link';

const lists = [
  { id: 1, name: 'Power Supply BOM', components: 18, created: '2026-07-12', description: 'Complete BOM for 12V/5A switching power supply module' },
  { id: 2, name: 'Sensor Module Parts', components: 9, created: '2026-06-28', description: 'Temperature and humidity sensor breakout board' },
  { id: 3, name: 'Favorites - Capacitors', components: 32, created: '2026-05-15', description: 'Frequently ordered MLCC and electrolytic capacitors' },
  { id: 4, name: 'Motor Driver Board', components: 14, created: '2026-08-02', description: 'H-bridge driver with current sensing for DC motors' },
];

export default function ListsPage() {
  return (
    <>
      <div className="acct-page-header">
        <h2>My Lists</h2>
        <p>Organize and manage your saved component lists and BOMs.</p>
      </div>

      <div className="acct-actions" style={{ marginBottom: 24 }}>
        <button className="acct-btn acct-btn-primary">+ Create New List</button>
      </div>

      <div className="acct-card-grid">
        {lists.map(list => (
          <div key={list.id} className="acct-card">
            <h3>{list.name}</h3>
            <p>{list.components} components · Created {list.created}</p>
            <p>{list.description}</p>
            <div className="acct-card-actions">
              <Link href="#" className="acct-btn acct-btn-primary">View</Link>
              <button className="acct-btn acct-btn-secondary">Share</button>
              <button className="acct-btn acct-btn-danger">Delete</button>
            </div>
          </div>
        ))}
      </div>
    </>
  );
}
