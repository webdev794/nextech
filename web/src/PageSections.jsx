import { renderMarkdown } from './markdown'
import { mediaUrl } from './mediaUrl'

// Only allow links the storefront would follow — never javascript:/data: URLs.
const safeUrl = (url) => (typeof url === 'string' && /^(https?:\/\/|\/|#|mailto:)/.test(url.trim()) ? url.trim() : null)
// Open in a new tab only for absolute http(s) links; in-app (#/… or /…) stay here.
const opensNewTab = (href) => /^https?:\/\//i.test(href)

function SectionButton({ label, url }) {
  const href = safeUrl(url)
  if (!href || !label) return null
  return <a className="psec-btn" href={href} {...(opensNewTab(href) ? { target: '_blank', rel: 'noopener noreferrer' } : {})}>{label}</a>
}

export function PageSection({ section }) {
  const s = section ?? {}

  switch (s.type) {
    case 'rich_text':
      return <div className="psec page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(s.markdown || '') }} />

    case 'hero':
      return (
        <section className="psec psec-hero">
          {s.image_url && <img className="psec-hero-img" src={mediaUrl(s.image_url)} alt="" loading="lazy" />}
          <div className="psec-hero-body">
            {s.heading && <h2>{s.heading}</h2>}
            {s.text && <p>{s.text}</p>}
            <SectionButton label={s.button_label} url={s.button_url} />
          </div>
        </section>
      )

    case 'media_text':
      return (
        <section className={`psec psec-media${s.image_side === 'right' ? ' right' : ''}`}>
          {s.image_url && <img className="psec-media-img" src={mediaUrl(s.image_url)} alt="" loading="lazy" />}
          <div className="psec-media-body page-content">
            {s.heading && <h2>{s.heading}</h2>}
            <div dangerouslySetInnerHTML={{ __html: renderMarkdown(s.markdown || '') }} />
          </div>
        </section>
      )

    case 'feature_grid':
      return (
        <section className="psec psec-features">
          {s.heading && <h2>{s.heading}</h2>}
          <div className="psec-features-grid">
            {(s.items ?? []).map((item, index) => {
              const href = safeUrl(item.link_url)
              const Tag = href ? 'a' : 'article'
              const linkProps = href
                ? { href, ...(opensNewTab(href) ? { target: '_blank', rel: 'noopener noreferrer' } : {}) }
                : {}
              return (
                <Tag className={`psec-feature${href ? ' is-link' : ''}`} key={index} {...linkProps}>
                  {item.image_url && <img src={mediaUrl(item.image_url)} alt="" loading="lazy" />}
                  {item.title && <h3>{item.title}</h3>}
                  {item.text && <p>{item.text}</p>}
                  {href && <span className="psec-feature-more">{item.image_url ? 'Read more' : 'Open'} &rarr;</span>}
                </Tag>
              )
            })}
          </div>
        </section>
      )

    case 'stats':
      return (
        <section className="psec psec-stats">
          {s.heading && <h2>{s.heading}</h2>}
          <div className="psec-stats-row">
            {(s.items ?? []).map((item, index) => (
              <div className="psec-stat" key={index}>
                {item.title && <strong>{item.title}</strong>}
                {item.text && <span>{item.text}</span>}
              </div>
            ))}
          </div>
        </section>
      )

    case 'steps':
      return (
        <section className="psec psec-steps">
          {s.heading && <h2>{s.heading}</h2>}
          <ol className="psec-steps-list">
            {(s.items ?? []).map((item, index) => (
              <li className="psec-step" key={index}>
                <span className="psec-step-num">{index + 1}</span>
                <div>
                  {item.title && <h3>{item.title}</h3>}
                  {item.text && <p>{item.text}</p>}
                </div>
              </li>
            ))}
          </ol>
        </section>
      )

    case 'faq':
      return (
        <section className="psec psec-faq">
          {s.heading && <h2>{s.heading}</h2>}
          <div className="psec-faq-list">
            {(s.items ?? []).map((item, index) => (
              <details className="psec-faq-item" key={index}>
                <summary>
                  <span>{item.title}</span>
                  <span className="psec-faq-caret" aria-hidden>&#9662;</span>
                </summary>
                <div className="psec-faq-answer page-content" dangerouslySetInnerHTML={{ __html: renderMarkdown(item.text || '') }} />
              </details>
            ))}
          </div>
        </section>
      )

    case 'quote':
      return (
        <section className="psec psec-quote">
          {s.text && <blockquote>{s.text}</blockquote>}
          {s.author && <cite>{s.author}</cite>}
        </section>
      )

    case 'cta':
      return (
        <section className="psec psec-cta">
          {s.heading && <h2>{s.heading}</h2>}
          {s.text && <p>{s.text}</p>}
          <SectionButton label={s.button_label} url={s.button_url} />
        </section>
      )

    default:
      return null
  }
}
