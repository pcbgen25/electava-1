'use client';

import { useState, useMemo } from 'react';
import { FiTrendingUp, FiTrendingDown, FiMinus, FiClock, FiSearch, FiRefreshCw } from 'react-icons/fi';

const INITIAL_LEAD_TIMES = [
  {
    id: 'lt-1',
    category: 'Capacitors',
    subCategory: 'MLCC & Electrolytic',
    leadTimeWeeks: '8 - 12 weeks',
    avgWeeks: 10,
    trend: 'Stable',
    trendDiff: '0 wks',
    lastUpdated: '2026-08-15',
  },
  {
    id: 'lt-2',
    category: 'Resistors',
    subCategory: 'SMD Thin Film & Wirewound',
    leadTimeWeeks: '4 - 6 weeks',
    avgWeeks: 5,
    trend: 'Down',
    trendDiff: '-2 wks',
    lastUpdated: '2026-08-18',
  },
  {
    id: 'lt-3',
    category: 'Integrated Circuits (ICs)',
    subCategory: 'Power Management & Analog',
    leadTimeWeeks: '18 - 24 weeks',
    avgWeeks: 21,
    trend: 'Up',
    trendDiff: '+3 wks',
    lastUpdated: '2026-08-19',
  },
  {
    id: 'lt-4',
    category: 'Connectors',
    subCategory: 'PCB Headers & Industrial Circular',
    leadTimeWeeks: '6 - 8 weeks',
    avgWeeks: 7,
    trend: 'Stable',
    trendDiff: '0 wks',
    lastUpdated: '2026-08-14',
  },
  {
    id: 'lt-5',
    category: 'Inductors & Coils',
    subCategory: 'Shielded Power Chokes',
    leadTimeWeeks: '10 - 14 weeks',
    avgWeeks: 12,
    trend: 'Up',
    trendDiff: '+1 wk',
    lastUpdated: '2026-08-10',
  },
  {
    id: 'lt-6',
    category: 'Microcontrollers',
    subCategory: '32-Bit ARM & RISC-V MCUs',
    leadTimeWeeks: '16 - 22 weeks',
    avgWeeks: 19,
    trend: 'Down',
    trendDiff: '-4 wks',
    lastUpdated: '2026-08-16',
  },
  {
    id: 'lt-7',
    category: 'Discrete Semiconductors',
    subCategory: 'MOSFETs, Diodes & Rectifiers',
    leadTimeWeeks: '8 - 10 weeks',
    avgWeeks: 9,
    trend: 'Stable',
    trendDiff: '0 wks',
    lastUpdated: '2026-08-17',
  },
];

export default function LeadTimeTrendsPage() {
  const [categoryFilter, setCategoryFilter] = useState('all');
  const [trendFilter, setTrendFilter] = useState('all');
  const [searchTerm, setSearchTerm] = useState('');
  const [refreshing, setRefreshing] = useState(false);

  const filteredData = useMemo(() => {
    return INITIAL_LEAD_TIMES.filter((item) => {
      const matchCategory =
        categoryFilter === 'all' || item.category.toLowerCase().includes(categoryFilter.toLowerCase());
      const matchTrend = trendFilter === 'all' || item.trend.toLowerCase() === trendFilter.toLowerCase();
      const matchSearch =
        searchTerm.trim() === '' ||
        item.category.toLowerCase().includes(searchTerm.toLowerCase()) ||
        item.subCategory.toLowerCase().includes(searchTerm.toLowerCase());
      return matchCategory && matchTrend && matchSearch;
    });
  }, [categoryFilter, trendFilter, searchTerm]);

  const handleRefresh = () => {
    setRefreshing(true);
    setTimeout(() => {
      setRefreshing(false);
    }, 500);
  };

  const getTrendBadge = (trend, trendDiff) => {
    if (trend === 'Up') {
      return (
        <span className="acct-status acct-status--rejected" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <FiTrendingUp /> Up ({trendDiff})
        </span>
      );
    }
    if (trend === 'Down') {
      return (
        <span className="acct-status acct-status--approved" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
          <FiTrendingDown /> Down ({trendDiff})
        </span>
      );
    }
    return (
      <span className="acct-status acct-status--open" style={{ display: 'inline-flex', alignItems: 'center', gap: '4px' }}>
        <FiMinus /> Stable
      </span>
    );
  };

  return (
    <>
      <div className="acct-page-header">
        <h2>Lead Time Trends</h2>
        <p>Monitor real-time component lead times, industry supply shifts, and factory fulfillment estimates across marketplace product categories.</p>
      </div>

      <section className="account-box">
        <div className="acct-section">
          <h3>Lead Time Trend Overview</h3>
          <div className="acct-chart-placeholder">
            <div style={{ textAlign: 'center', padding: '16px' }}>
              <p style={{ margin: 0, fontWeight: 600, fontSize: '1rem', color: 'var(--text-secondary)' }}>
                Lead time trend chart — coming soon
              </p>
              <p style={{ margin: '8px 0 0', fontSize: '0.85rem', color: 'var(--text-tertiary)' }}>
                Interactive historical lead time tracking will be available in the upcoming analytics release.
              </p>
            </div>
          </div>
        </div>

        <div className="acct-section" style={{ marginBottom: 0 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '12px', marginBottom: '16px' }}>
            <h3 style={{ margin: 0, borderBottom: 'none', paddingBottom: 0 }}>Component Categories</h3>
            <button
              type="button"
              className="acct-btn acct-btn-secondary"
              style={{ padding: '6px 14px', fontSize: '0.82rem' }}
              onClick={handleRefresh}
            >
              <FiRefreshCw /> {refreshing ? 'Updating...' : 'Refresh Intel'}
            </button>
          </div>

          <div className="acct-filter-bar">
            <select
              value={categoryFilter}
              onChange={(e) => setCategoryFilter(e.target.value)}
              aria-label="Filter by category"
            >
              <option value="all">All Categories</option>
              <option value="Capacitors">Capacitors</option>
              <option value="Resistors">Resistors</option>
              <option value="Integrated Circuits">Integrated Circuits (ICs)</option>
              <option value="Connectors">Connectors</option>
              <option value="Inductors">Inductors & Coils</option>
              <option value="Microcontrollers">Microcontrollers</option>
              <option value="Discrete Semiconductors">Discrete Semiconductors</option>
            </select>

            <select
              value={trendFilter}
              onChange={(e) => setTrendFilter(e.target.value)}
              aria-label="Filter by trend"
            >
              <option value="all">All Trends</option>
              <option value="Up">Increasing (Up)</option>
              <option value="Down">Decreasing (Down)</option>
              <option value="Stable">Stable</option>
            </select>

            <div style={{ position: 'relative', flex: '1', minWidth: '200px' }}>
              <input
                type="text"
                placeholder="Search category or component..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                style={{ width: '100%', paddingLeft: '32px' }}
              />
              <FiSearch style={{ position: 'absolute', left: '10px', top: '50%', transform: 'translateY(-50%)', color: 'var(--text-tertiary)', pointerEvents: 'none' }} />
            </div>
          </div>

          {filteredData.length === 0 ? (
            <div className="acct-empty">
              <div className="acct-empty-icon">
                <FiClock />
              </div>
              <h3>No Category Lead Times Found</h3>
              <p>No component categories matched your current filter criteria. Try resetting filters.</p>
              <button
                type="button"
                className="acct-btn acct-btn-secondary"
                onClick={() => {
                  setCategoryFilter('all');
                  setTrendFilter('all');
                  setSearchTerm('');
                }}
              >
                Reset Filters
              </button>
            </div>
          ) : (
            <div className="acct-table-wrap">
              <table className="acct-table">
                <thead>
                  <tr>
                    <th>Category</th>
                    <th>Avg Lead Time (weeks)</th>
                    <th>Trend</th>
                    <th>Last Updated</th>
                  </tr>
                </thead>
                <tbody>
                  {filteredData.map((row) => (
                    <tr key={row.id}>
                      <td>
                        <div style={{ fontWeight: 600, color: 'var(--text-primary)' }}>{row.category}</div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text-secondary)' }}>{row.subCategory}</div>
                      </td>
                      <td>
                        <span style={{ fontWeight: 600, color: 'var(--text-primary)' }}>
                          {row.leadTimeWeeks}
                        </span>
                      </td>
                      <td>{getTrendBadge(row.trend, row.trendDiff)}</td>
                      <td style={{ color: 'var(--text-secondary)', fontSize: '0.85rem' }}>{row.lastUpdated}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </section>
    </>
  );
}
