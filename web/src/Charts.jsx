// Tiny dependency-free SVG charts for the admin dashboard.

const PALETTE = [
  '#3f7d43', '#6aa84f', '#b6d7a8', '#e69138', '#cc7a29',
  '#8a6d2f', '#7c857a', '#c27ba0', '#5b9aa0', '#a64d79',
]
const color = (index) => PALETTE[index % PALETTE.length]

const TAU = Math.PI * 2
const START = -Math.PI / 2

// data: [{ label, value }]; format: fn(value) -> string for the legend figure
export function PieChart({ data, size = 168, format = (v) => v }) {
  const slices = (data ?? []).filter((d) => d.value > 0)
  const total = slices.reduce((sum, d) => sum + d.value, 0)
  const r = size / 2

  if (!total) return <p className="chart-empty">No orders in this range.</p>

  // Cumulative value before each slice — built immutably so nothing is
  // reassigned inside the render callbacks.
  const before = slices.reduce((acc, slice) => [...acc, acc[acc.length - 1] + slice.value], [0])

  const paths = slices.map((slice, index) => {
    if (slices.length === 1) {
      return <circle key={slice.label} cx={r} cy={r} r={r} fill={color(index)} />
    }
    const a0 = START + (before[index] / total) * TAU
    const a1 = START + (before[index + 1] / total) * TAU
    const large = a1 - a0 > Math.PI ? 1 : 0
    const d = [
      `M ${r} ${r}`,
      `L ${r + r * Math.cos(a0)} ${r + r * Math.sin(a0)}`,
      `A ${r} ${r} 0 ${large} 1 ${r + r * Math.cos(a1)} ${r + r * Math.sin(a1)}`,
      'Z',
    ].join(' ')
    return <path key={slice.label} d={d} fill={color(index)} />
  })

  return (
    <div className="chart-pie">
      <svg viewBox={`0 0 ${size} ${size}`} width={size} height={size} role="img" aria-label="Breakdown">
        {paths}
      </svg>
      <ul className="chart-legend">
        {slices.map((slice, index) => (
          <li key={slice.label}>
            <i style={{ background: color(index) }} />
            {slice.label} <b>{format(slice.value)}</b>
            <span>{Math.round((slice.value / total) * 100)}%</span>
          </li>
        ))}
      </ul>
    </div>
  )
}

// Period-over-period change badge. "Up" is treated as good (green).
export function Delta({ current, previous }) {
  if (!previous) {
    if (!current) return <span className="delta flat">no change</span>
    return <span className="delta up">▲ new</span>
  }
  const pct = Math.round(((current - previous) / previous) * 100)
  if (pct === 0) return <span className="delta flat">0%</span>
  const dir = pct > 0 ? 'up' : 'down'
  return <span className={`delta ${dir}`}>{pct > 0 ? '▲' : '▼'} {Math.abs(pct)}%</span>
}

// Trend graph over evenly-spaced buckets, one or more lines at once. Each
// line is scaled to its own max (independent axes) so very different
// magnitudes — an order count next to a dollar amount — both stay readable.
// lines: [{ key, label, color, format?: fn(value)->string, points: [{ label, value }] }]
export function LineChart({ lines }) {
  const active = (lines ?? []).filter((line) => (line.points ?? []).length >= 2)
  if (!active.length) return <p className="chart-empty">Not enough data yet.</p>

  const W = 300
  const H = 120
  const padY = 8
  const rows = active[0].points
  const stepX = W / (rows.length - 1)

  const built = active.map((line) => {
    const format = line.format ?? ((v) => v)
    const max = Math.max(1, ...line.points.map((p) => p.value))
    const coords = line.points.map((p, i) => [i * stepX, padY + (H - padY * 2) * (1 - p.value / max)])
    const d = coords.map(([x, y], i) => `${i ? 'L' : 'M'}${x.toFixed(1)} ${y.toFixed(1)}`).join(' ')

    return { ...line, format, max, coords, d }
  })

  const showEvery = rows.length > 12 ? Math.ceil(rows.length / 8) : 1

  return (
    <div className="chart-line">
      <div className="chart-line-legend">
        {built.map((line) => (
          <span className="chart-line-legend-item" key={line.key}>
            <i style={{ background: line.color }} /> {line.label} <b>{line.format(line.max)}</b>
          </span>
        ))}
      </div>
      <svg viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Trend">
        {built.length === 1 && <path className="line-area" d={`${built[0].d} L${W} ${H} L0 ${H} Z`} />}
        {built.map((line) => <path key={line.key} d={line.d} fill="none" stroke={line.color} strokeWidth="2" />)}
        {built.map((line) => line.coords.map(([x, y], i) => (
          <circle key={`${line.key}-${i}`} cx={x} cy={y} r="2" fill={line.color}>
            <title>{`${line.label} · ${line.points[i].label}: ${line.format(line.points[i].value)}`}</title>
          </circle>
        )))}
      </svg>
      <div className="chart-line-x">
        {rows.map((r, i) => <span key={r.label + i}>{i % showEvery === 0 ? r.label : ''}</span>)}
      </div>
    </div>
  )
}

// Grid of cells shaded by value. rows: string[]; matrix: number[rows][cols];
// peak: optional pre-computed max; cols: optional [firstLabel, lastLabel] axis.
export function Heatmap({ rows, matrix, peak, cols, format = (v) => v, cellTitle }) {
  const grid = matrix ?? []
  const width = grid[0]?.length ?? 0
  const max = Math.max(1, peak || 0, ...grid.flat())

  if (!grid.length || !width) return <p className="chart-empty">No activity yet.</p>

  const shade = (v) => (v <= 0 ? '#f1f3ef' : `rgba(63,125,67,${(0.15 + (v / max) * 0.85).toFixed(3)})`)

  return (
    <div className="chart-heatmap" style={{ '--hm-cols': width }}>
      {grid.map((row, r) => (
        <div className="hm-row" key={(rows?.[r] ?? r) + '-' + r}>
          <span className="hm-rlabel">{rows?.[r] ?? ''}</span>
          <div className="hm-cells">
            {row.map((value, c) => (
              <i
                key={c}
                className="hm-cell"
                style={{ background: shade(value) }}
                title={cellTitle ? cellTitle(r, c, value) : `${rows?.[r] ?? ''} ${c}: ${format(value)}`}
              />
            ))}
          </div>
        </div>
      ))}
      {cols && (
        <div className="hm-xaxis">
          <span>{cols[0]}</span>
          <span>{cols[cols.length - 1]}</span>
        </div>
      )}
    </div>
  )
}

// series: [{ label, value }]; format: fn(value) -> string
export function BarChart({ series, format = (v) => v, height = 190 }) {
  const rows = series ?? []
  const max = Math.max(1, ...rows.map((row) => row.value))
  const showEvery = rows.length > 16 ? Math.ceil(rows.length / 8) : 1

  if (!rows.length) return <p className="chart-empty">No data.</p>

  return (
    <div className="chart-bars" style={{ height }}>
      <div className="chart-bars-max">{format(max)}</div>
      <div className="chart-bars-plot">
        {rows.map((row, index) => (
          <div className="chart-bar-col" key={row.label + index} title={`${row.label}: ${format(row.value)}`}>
            <div className="chart-bar" style={{ height: `${(row.value / max) * 100}%` }}>
              <span className="chart-bar-val">{row.value ? format(row.value) : ''}</span>
            </div>
            <span className="chart-bar-label">{index % showEvery === 0 ? row.label : ''}</span>
          </div>
        ))}
      </div>
    </div>
  )
}
