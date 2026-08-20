'use client';

import Link from 'next/link';
import './manufacturers.css';

export default function ManufacturersPage() {
  const manufacturers = [
    { name: 'Microchip Technology', products: 12500 },
    { name: 'Texas Instruments', products: 24300 },
    { name: 'Analog Devices', products: 18450 },
    { name: 'NXP Semiconductors', products: 15200 },
    { name: 'STMicroelectronics', products: 21100 },
    { name: 'Infineon Technologies', products: 11800 },
    { name: 'Renesas Electronics', products: 9600 },
    { name: 'ON Semiconductor', products: 13500 },
  ];

  return (
    <div className="manufacturers-page">
      <div className="container">
        <h1 className="manufacturers-title">Manufacturers</h1>
        <p className="manufacturers-subtitle">
        Browse our extensive list of industry-leading electronic component manufacturers.
        </p>

        <div className="manufacturers-grid">
          {manufacturers.map((m, index) => (
            <Link
              href={`/products?manufacturer=${encodeURIComponent(m.name)}`}
              key={index}
              className="manufacturer-card"
            >
              <h3>{m.name}</h3>
              <p>{m.products.toLocaleString()} Products</p>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
