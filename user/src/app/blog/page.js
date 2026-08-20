import { blogPosts, getAllCategories } from '@/data/blogPosts';
import BlogCard from '@/components/BlogCard/BlogCard';
import './blog.css';

export const metadata = {
  title: 'Engineering Blog — Electava',
  description: 'Technical articles, engineering guides, and product announcements from the Electava team.',
};

export default function BlogPage() {
  const categories = getAllCategories();

  return (
    <div className="blog-page">
      <div className="blog-hero">
        <div className="container">
          <h1 className="blog-hero-title animate-slide-up">Engineering Blog</h1>
          <p className="blog-hero-subtitle animate-fade-in">
            Deep dives into hardware design, component selection, and future trends.
          </p>
        </div>
      </div>

      <div className="container section">
        <div className="blog-filters">
          <button className="blog-filter-btn active">All Topics</button>
          {categories.map(category => (
            <button key={category} className="blog-filter-btn">
              {category}
            </button>
          ))}
        </div>

        <div className="blog-grid">
          {blogPosts.map((post, index) => (
            <div 
              key={post.id} 
              className="blog-grid-item"
              style={{ animationDelay: `${index * 0.1}s` }}
            >
              <BlogCard post={post} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}
