import Link from 'next/link';
import { FiCpu, FiLayers, FiTool, FiCheckCircle, FiTruck, FiList } from 'react-icons/fi';
import './resources.css';

export default function ResourcesPage() {
  const resources = [
    {
      title: 'PCB Design',
      description: 'Expert guidelines, tools, and best practices for creating efficient printed circuit boards.',
      icon: <FiLayers size={32} />,
    },
    {
      title: 'BOM Analysis',
      description: 'Review bill of materials data, identify sourcing risks, and improve part selection before production.',
      icon: <FiList size={32} />,
    },
    {
      title: 'Manufacturer Comparison',
      description: 'Compare leading manufacturers by capability, pricing, lead times, and reliability.',
      icon: <FiCpu size={32} />,
    },
    {
      title: 'Assembly',
      description: 'Learn about SMT, THT, and mixed assembly processes tailored for your project needs.',
      icon: <FiTool size={32} />,
    },
    {
      title: 'Testing & Debugging',
      description: 'Comprehensive tutorials on automated optical inspection, X-Ray, and functional testing.',
      icon: <FiCheckCircle size={32} />,
    },
    {
      title: 'Full Project Delivery',
      description: 'From scratch to board delivery: a complete guide to bringing your electronic product to life.',
      icon: <FiTruck size={32} />,
    },
  ];

  return (
    <div className="resources-page">
      <div className="container">
        <h1 className="resources-title">Engineering Resources</h1>
        <p className="resources-subtitle">
          Access our knowledge base to help you design, source, build, and test your electronic
          projects efficiently.
        </p>

        <div className="resources-grid">
          {resources.map((resource, index) => (
            <Link key={index} href="/quotation" className="resource-card">
              <div className="resource-icon">{resource.icon}</div>
              <h3>{resource.title}</h3>
              <p>{resource.description}</p>
              <span className="resource-link">Request Quotation -&gt;</span>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
