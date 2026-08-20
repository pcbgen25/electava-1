import Link from 'next/link';
import Image from 'next/image';
import './BlogCard.css';

export default function BlogCard({ post }) {
  return (
    <div className="blog-card card">
      <div className="blog-card-image-wrapper">
        <Link href={`/blog/${post.slug}`}>
          <div className="blog-image-overlay"></div>
          <Image 
            src={post.image} 
            alt={post.title} 
            fill 
            className="blog-card-image"
            sizes="(max-width: 768px) 100vw, (max-width: 1200px) 50vw, 33vw"
          />
        </Link>
        <span className="blog-card-category badge badge-info">{post.category}</span>
      </div>
      
      <div className="blog-card-content">
        <div className="blog-card-meta">
          <span className="blog-card-date">{post.date}</span>
          <span className="meta-separator">•</span>
          <span className="blog-card-readtime">{post.readTime}</span>
        </div>
        
        <Link href={`/blog/${post.slug}`} className="blog-card-title-link">
          <h3 className="blog-card-title">{post.title}</h3>
        </Link>
        
        <p className="blog-card-excerpt">{post.excerpt}</p>
        
        <div className="blog-card-footer">
          <div className="blog-card-author">
            <div className="author-avatar">
              {post.author.charAt(0)}
            </div>
            <div className="author-info">
              <span className="author-name">{post.author}</span>
            </div>
          </div>
          <Link href={`/blog/${post.slug}`} className="blog-card-read-more">
            Read More
          </Link>
        </div>
      </div>
    </div>
  );
}
