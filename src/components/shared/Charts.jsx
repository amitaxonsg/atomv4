import React from "react";

export function AlignmentGauge({ score }) {
  const percentage = Math.max(0, Math.min(100, (score - 50) / 2));
  return <div className="gauge" aria-label={`Alignment score ${score} out of 250`}>
    <div className="gauge__arc" style={{ "--score": `${percentage * 1.8}deg` }} />
    <strong>{score}</strong><span>out of 250</span>
    <div className="gauge__labels"><span>Heart</span><span>Head</span></div>
  </div>;
}

export function RadarChart({ values, labels }) {
  const points = values.map((value, index) => {
    const angle = (Math.PI * 2 * index) / values.length - Math.PI / 2;
    const normalized = Math.max(0, Math.min(1, (Number(value) - 5) / 20));
    const radius = normalized * 92;
    return `${120 + Math.cos(angle) * radius},${120 + Math.sin(angle) * radius}`;
  }).join(" ");
  return <svg className="radar" viewBox="0 0 240 240" role="img" aria-label="Subscale radar chart, scores range from 5 to 25">
    {[1, .75, .5, .25].map(scale => <polygon key={scale} points={values.map((_, index) => { const angle = Math.PI * 2 * index / values.length - Math.PI / 2; return `${120 + Math.cos(angle) * 92 * scale},${120 + Math.sin(angle) * 92 * scale}`; }).join(" ")} />)}
    <polygon className="radar__value" points={points} />
    {labels.map((label, index) => { const angle = Math.PI * 2 * index / labels.length - Math.PI / 2; return <text key={label} x={120 + Math.cos(angle) * 108} y={123 + Math.sin(angle) * 108}>{label}</text>; })}
  </svg>;
}
