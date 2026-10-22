/** Histogramme SVG minimal (scans par jour). */
export function BarChart({ data, color = "var(--brand)" }: { data: { label: string; value: number }[]; color?: string }) {
  const W = 720, H = 200, padB = 24, padT = 12;
  const max = Math.max(1, ...data.map((d) => d.value));
  const bw = W / data.length;
  return (
    <svg className="bar-chart" viewBox={`0 0 ${W} ${H}`} role="img" aria-label="Scans par jour">
      {[0.5, 1].map((k) => (
        <g key={k}>
          <line x1={0} x2={W} y1={padT + (H - padB - padT) * (1 - k)} y2={padT + (H - padB - padT) * (1 - k)} stroke="var(--line)" />
          <text x={2} y={padT + (H - padB - padT) * (1 - k) - 3} fontSize="11" fill="var(--muted)">{Math.round(max * k)}</text>
        </g>
      ))}
      {data.map((d, i) => {
        const h = ((H - padB - padT) * d.value) / max;
        return (
          <g key={i}>
            <rect x={i * bw + bw * 0.18} y={H - padB - h} width={bw * 0.64} height={Math.max(h, d.value ? 2 : 0)} rx={3} fill={color}>
              <title>{`${d.label} : ${d.value}`}</title>
            </rect>
            {i % Math.ceil(data.length / 8) === 0 && (
              <text x={i * bw + bw / 2} y={H - 6} fontSize="11" textAnchor="middle" fill="var(--muted)">{d.label}</text>
            )}
          </g>
        );
      })}
    </svg>
  );
}
