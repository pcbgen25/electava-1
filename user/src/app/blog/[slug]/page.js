import { notFound } from 'next/navigation';
import Image from 'next/image';
import Link from 'next/link';
import { getPostBySlug } from '@/data/blogPosts';
import './post.css';

export async function generateMetadata({ params }) {
  const post = getPostBySlug(params.slug);
  
  if (!post) {
    return {
      title: 'Post Not Found — Electava',
    };
  }

  return {
    title: `${post.title} — Electava Engineering`,
    description: post.excerpt,
  };
}

export default function BlogPostPage({ params }) {
  const post = getPostBySlug(params.slug);

  if (!post) {
    notFound();
  }

  return (
    <div className="blog-post-page">
      <div className="container">
        <div className="breadcrumb">
          <Link href="/">Home</Link>
          <span className="breadcrumb-sep">/</span>
          <Link href="/blog">Blog</Link>
          <span className="breadcrumb-sep">/</span>
          <span className="breadcrumb-current">{post.title}</span>
        </div>
      </div>

      <article className="blog-post-article">
        <header className="blog-post-header">
          <div className="container">
            <span className="badge badge-info">{post.category}</span>
            <h1 className="blog-post-title">{post.title}</h1>
            
            <div className="blog-post-meta">
              <div className="blog-post-author">
                <div className="author-avatar-large">
                  {post.author.charAt(0)}
                </div>
                <div className="author-info">
                  <span className="author-name-large">{post.author}</span>
                  <span className="author-role">{post.role}</span>
                </div>
              </div>
              <div className="blog-post-stats">
                <span className="stat-date">{post.date}</span>
                <span className="stat-readtime">{post.readTime}</span>
              </div>
            </div>
          </div>
        </header>

        <div className="container">
          <div className="blog-post-hero-image">
            <Image 
              src={post.image} 
              alt={post.title} 
              fill
              priority
              className="post-hero-img"
              sizes="100vw"
            />
          </div>
        </div>

        <div className="container">
          <div className="blog-post-content-wrapper">
            <div 
              className="blog-post-content"
              dangerouslySetInnerHTML={{ __html: post.content }}
            />
            
            <div className="blog-post-footer">
              <Link href="/blog" className="btn btn-secondary">
                ← Back to all articles
              </Link>
            </div>
          </div>
        </div>
      </article>
      
      <section className="blog-post-newsletter section bg-tertiary">
        <div className="container flex-col flex-center">
          <h2 className="section-title">Subscribe to Engineering Updates</h2>
          <p className="section-subtitle">Get the latest technical articles straight to your inbox.</p>
          <form className="newsletter-form flex" onSubmit={(e) => e.preventDefault()}>
            <input type="email" placeholder="Email address" className="input-field" />
            <button type="submit" className="btn btn-primary">Subscribe</button>
          </form>
        </div>
      </section>
    </div>
  );
}
