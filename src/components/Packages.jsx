const PACKAGES = [
  {
    num: "PKG · 01",
    img: "/images/solar-packages/ces-security-solution.jpg",
    title: "GSM Home Security",
  },
  {
    num: "PKG · 02",
    img: "/images/solar-packages/ces-roofing.jpg",
    title: "Roof Structure",
  },
  {
    num: "PKG · 03",
    img: "/images/solar-packages/ces-roller-doors.jpg",
    title: "Roller Door",
  },
  {
    num: "PKG · 04",
    img: "/images/solar-packages/ces-solar.jpg",
    title: "Solar",
  },
];

export default function Packages() {
  return (
    <section id="our-packages" className="wrap-dark pad">
      <div className="container">
        <div className="section-head center">
          <p className="eyebrow gold center">Bundled Solutions</p>
          <h2 className="section-title reveal">Our Packages</h2>
          <p className="lede reveal">
            Pre-scoped packages that bring design, supply and installation into
            one line item.
          </p>
        </div>
        <div className="pkg-rail">
          {PACKAGES.map((p) => (
            <div className="pkg-card reveal" key={p.title}>
              <div className="pkg-media">
                <img src={p.img} alt={`${p.title} Package`} />
              </div>
              <div className="pkg-info">
                <span className="pkg-num">{p.num}</span>
                <h5>{p.title}</h5>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
