const SERVICES = [
  {
    num: "SVC · 01",
    icon: "/images/what-we-do/structural-design.svg",
    title: "Structural Design",
    desc: "Engineering assessments and drawings that hold their ground.",
  },
  {
    num: "SVC · 02",
    icon: "/images/what-we-do/house-wiring.svg",
    title: "House Electrical & Wiring",
    desc: "Safe, code-compliant wiring for homes and commercial spaces.",
  },
  {
    num: "SVC · 03",
    icon: "/images/what-we-do/cctv-security.svg",
    title: "CCTV & Sensor Security",
    desc: "Layered monitoring and sensor systems for round-the-clock protection.",
  },
  {
    num: "SVC · 04",
    icon: "/images/what-we-do/roller-doors.svg",
    title: "Roller Doors",
    desc: "Durable roller door supply and installation, built for daily use.",
  },
  {
    num: "SVC · 05",
    icon: "/images/what-we-do/solar-pv.svg",
    title: "Solar PV Solutions",
    desc: "Installation and maintenance of solar PV systems, sized to fit.",
  },
  {
    num: "SVC · 06",
    icon: "/images/what-we-do/lightning-protection.svg",
    title: "Lightning Protection",
    desc: "Earthing and lightning protection engineered to recognised standards.",
  },
];

export default function WhatWeDo() {
  return (
    <section id="what-we-do" className="wrap-panel pad">
      <div className="container">
        <div className="section-head center">
          <p className="eyebrow center">Capabilities</p>
          <h2 className="section-title reveal">What We Do</h2>
          <p className="lede reveal">
            Six disciplines, one accountable team — from the first drawing to
            the last connection.
          </p>
        </div>
      </div>
      <div className="container">
        <div className="wwd-grid">
          {SERVICES.map((s) => (
            <div className="wwd-card reveal" data-tilt key={s.title}>
              <span className="num">{s.num}</span>
              <img className="ic" src={s.icon} alt="" />
              <h4>{s.title}</h4>
              <p>{s.desc}</p>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
