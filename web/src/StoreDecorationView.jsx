import { useEffect, useState } from 'react'
import { mediaUrl } from './mediaUrl'
import { storeMoney } from './money'
import './StoreDecoration.css'

// Renders a store decoration (a seller's designed store page) — shared by the
// storefront shop page, the Seller Center editor/preview and admin spot checks.
// `design` is { page, sections } as App\Support\StoreDecorations::resolve()
// returns it (products and categories filled in); the editor passes its own
// working copy plus `lookup` to fill products in on the fly.

const money = (cents) => storeMoney(cents ?? 0)

function Countdown({ endsAt }) {
  const [now, setNow] = useState(() => Date.now())
  useEffect(() => { const t = setInterval(() => setNow(Date.now()), 1000); return () => clearInterval(t) }, [])
  const left = Math.max(0, new Date(endsAt).getTime() - now)
  if (!endsAt || Number.isNaN(left)) return <span className="sd-timer">--:--:--</span>
  const d = Math.floor(left / 864e5)
  const pad = (n) => String(n).padStart(2, '0')
  return <span className="sd-timer">{d > 0 && <b>{d}d</b>}<b>{pad(Math.floor(left / 36e5) % 24)}</b>:<b>{pad(Math.floor(left / 6e4) % 60)}</b>:<b>{pad(Math.floor(left / 1e3) % 60)}</b></span>
}

function Banner({ slides, autoplay, onLink }) {
  const [index, setIndex] = useState(0)
  const count = slides.length
  useEffect(() => {
    if (!autoplay || count < 2) return undefined
    const t = setInterval(() => setIndex((i) => (i + 1) % count), 5000)
    return () => clearInterval(t)
  }, [autoplay, count])
  if (!count) return <div className="sd-empty">Banner — add images</div>
  const slide = slides[Math.min(index, count - 1)]
  return (
    <div className="sd-banner">
      {slide.image_url ? <img src={mediaUrl(slide.image_url)} alt="" onClick={() => onLink(slide.link)} className={slide.link ? 'sd-linked' : ''} /> : <div className="sd-empty">Banner image</div>}
      {count > 1 && <div className="sd-dots">{slides.map((_, i) => <button type="button" key={i} aria-label={`Slide ${i + 1}`} className={i === index ? 'on' : ''} onClick={() => setIndex(i)} />)}</div>}
    </div>
  )
}

function ProductRow({ products, layout, onProduct }) {
  if (!products?.length) return <div className="sd-empty">Products — choose some</div>
  return (
    <div className={layout === 'carousel' ? 'sd-products sd-carousel' : 'sd-products'}>
      {products.map((p) => (
        <button type="button" className="sd-card" key={p.id} onClick={() => onProduct(p.slug)}>
          <span className="sd-card-img">{p.image_url && <img src={mediaUrl(p.image_url)} alt="" loading="lazy" />}</span>
          <span className="sd-card-name">{p.name}</span>
          <span className="sd-card-price"><b>{money(p.price_cents)}</b>{p.compare_at_price_cents > p.price_cents && <s>{money(p.compare_at_price_cents)}</s>}</span>
        </button>
      ))}
    </div>
  )
}

export function SectionView({ section: s, onProduct = () => {}, onCategory = () => {} }) {
  const onLink = (link) => {
    if (!link) return
    if (link.type === 'product' && link.slug) onProduct(link.slug)
    if (link.type === 'category' && link.name) onCategory(link.name)
  }
  const title = s.title ? <h2 className="sd-title">{s.title}</h2> : null
  switch (s.type) {
    case 'announcement':
      return <div className={`sd-announce${s.link ? ' sd-linked' : ''}`} style={{ background: s.bg_color, color: s.text_color }} onClick={() => onLink(s.link)}>{s.text || 'Announcement text'}</div>
    case 'banner':
      return <section className="sd-section">{title}<Banner slides={s.slides ?? []} autoplay={s.autoplay} onLink={onLink} /></section>
    case 'category':
      return (
        <section className="sd-section">{title}
          {(s.items ?? []).length ? <div className="sd-cats">{s.items.map((c, i) => <button type="button" key={c.slug ?? i} className="sd-cat" onClick={() => onCategory(c.name)}><span>{c.image_url && <img src={mediaUrl(c.image_url)} alt="" />}</span>{c.name}</button>)}</div> : <div className="sd-empty">Categories — choose some</div>}
        </section>
      )
    case 'products':
    case 'auto_products':
      return <section className="sd-section">{title}<ProductRow products={s.products} layout={s.layout} onProduct={onProduct} /></section>
    case 'video':
      return (
        <section className="sd-section">{title}
          {s.video_url ? <video className="sd-video" src={mediaUrl(s.video_url)} poster={s.poster_url ? mediaUrl(s.poster_url) : undefined} controls playsInline preload="metadata" /> : <div className="sd-empty">Video — upload one</div>}
          {s.caption && <p className="sd-caption">{s.caption}</p>}
        </section>
      )
    case 'image_grid':
      return (
        <section className="sd-section">{title}
          <div className="sd-grid" style={{ gridTemplateColumns: `repeat(${s.columns ?? 2}, 1fr)` }}>
            {(s.tiles ?? []).map((t, i) => (t.image_url ? <img key={i} src={mediaUrl(t.image_url)} alt="" className={t.link ? 'sd-linked' : ''} onClick={() => onLink(t.link)} /> : <div key={i} className="sd-empty">Image</div>))}
          </div>
        </section>
      )
    case 'countdown':
      return (
        <section className="sd-countdown" style={{ background: s.bg_color }}>
          <div className="sd-countdown-head"><div><h2>{s.title || 'Sale event'}</h2>{s.subtitle && <p>{s.subtitle}</p>}</div><div className="sd-countdown-time"><small>Ends in</small><Countdown endsAt={s.ends_at} /></div></div>
          {(s.products ?? []).length > 0 && <ProductRow products={s.products} layout="carousel" onProduct={onProduct} />}
        </section>
      )
    case 'brand_story':
      return (
        <section className={`sd-section sd-story${s.image_side === 'right' ? ' right' : ''}`}>
          {s.image_url ? <img src={mediaUrl(s.image_url)} alt="" /> : <div className="sd-empty">Image</div>}
          <div>{title}<p>{s.text || 'Tell shoppers your brand story.'}</p></div>
        </section>
      )
    case 'spacer':
      return <div className={`sd-spacer ${s.size ?? 'medium'}`} />
    default:
      return null
  }
}

// The whole designed page: background band + sections.
export function DecorationView({ design, platform = 'desktop', shopName, onProduct, onCategory }) {
  const page = design?.page ?? {}
  return (
    <div className={`sd-page sd-${platform}`} style={{ '--sd-bg': page.background_color, '--sd-accent': page.accent_color }}>
      {page.background_image_url && <div className="sd-bg"><img src={mediaUrl(page.background_image_url)} alt={shopName ?? ''} /></div>}
      {(design?.sections ?? []).map((s) => <SectionView key={s.id} section={s} onProduct={onProduct} onCategory={onCategory} />)}
    </div>
  )
}
