export default function About() {
  return (
    <section id="about" className="wrap-dark pad">
      <div className="container about-grid">
        <div className="about-media reveal">
          <div className="frame" data-tilt>
            <img src="/images/about.jpg" alt="Ceylon Energy Services team at work" />
          </div>
          <span className="about-tag">Since incorporation · PVT LTD</span>
        </div>
        <div className="about-copy">
          <p className="eyebrow">Who We Are</p>
          <h3 className="reveal">Sustainable energy, engineered with care</h3>
          <p className="reveal">
            <span className="dropcap">C</span>eylon Energy &amp; Engineer Services
            PVT Ltd serves the gap between Sri Lanka&apos;s growing appetite for
            sustainable energy and the reliable, expert delivery it deserves. Our
            work spans lightning protection, security systems, roller doors and
            structural engineering — carried out by a team built on knowledge,
            experience and attitude.
          </p>
          <p className="reveal">
            Incorporated under the Companies Act and guided by the Sustainable
            Energy Authority, our mandate is simple: build a green Ceylon in
            complete harmony with the environment and nature.
          </p>
          <p className="reveal">
            In solar PV, we&apos;re recognised for reliability, flexibility and
            credibility — a team that treats customer satisfaction as the true
            measure of success, not just the bottom line.
          </p>
          <div className="about-facts reveal">
            <div>
              <b>Solar PV</b>Design, install &amp; maintain
            </div>
            <div>
              <b>Lightning Protection</b>Certified systems
            </div>
            <div>
              <b>Security</b>CCTV &amp; sensor networks
            </div>
            <div>
              <b>Structural</b>Roofing &amp; roller doors
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}
