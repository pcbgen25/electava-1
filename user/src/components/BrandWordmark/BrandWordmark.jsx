'use client';

import Link from 'next/link';
import './BrandWordmark.css';

export default function BrandWordmark({
  href = '/',
  size = 'md',
  className = '',
  centered = false,
}) {
  const linkClassName = ['brand-link', centered ? 'brand-link-centered' : '', className]
    .filter(Boolean)
    .join(' ');

  const wordmark = (
    <span className={`brand-wordmark brand-wordmark-${size}`}>
      Elect<span>ava</span>
    </span>
  );

  if (!href) {
    return wordmark;
  }

  return (
    <Link href={href} className={linkClassName} aria-label="Electava">
      {wordmark}
    </Link>
  );
}
