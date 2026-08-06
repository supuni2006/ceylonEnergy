export default function Footer() {
  return (
    <footer className="site-footer">
      <div className="container footer-grid">
        <div className="widget">
          <h3 className="widget-title">Meet Us</h3>
          <ul className="contact-info">
            <li>
              <i className="budicon-pin"></i>
              <p>
                World Trade Center,
                <br />
                Level 26 &amp; 34 East Tower Echelon Square,
                <br />
                Colombo 00100.
              </p>
            </li>
          </ul>
        </div>
        <div className="widget">
          <h3 className="widget-title">Call Us</h3>
          <ul className="contact-info">
            <li>
              <i className="budicon-telephone"></i>
              <p>
                <a href="tel:0771609688">0771 60 96 88</a>
              </p>
            </li>
            <li>
              <i className="budicon-telephone"></i>
              <p>
                <a href="tel:0113654565">0113 65 45 65</a>
              </p>
            </li>
          </ul>
        </div>
        <div className="widget">
          <h3 className="widget-title">Write to Us</h3>
          <ul className="contact-info">
            <li>
              <i className="budicon-mail"></i>
              <p>
                <a href="mailto:info@ceylonenergyservices.com">
                  info@ceylonenergyservices.com
                </a>
              </p>
            </li>
            <li>
              <i className="budicon-mail"></i>
              <p>
                <a href="mailto:sales@ceylonenergyservices.com">
                  sales@ceylonenergyservices.com
                </a>
              </p>
            </li>
          </ul>
        </div>
      </div>
      <div className="container sub-footer">
        <p>© 2026 Ceylon Energy Services · All rights reserved.</p>
        <ul className="social">
          <li>
            <a href="https://www.facebook.com/?checkpoint_src=any" aria-label="Facebook">
              <i className="fa fa-facebook"></i>
            </a>
          </li>
          <li>
            <a href="https://www.instagram.com/ceylon_energy/" aria-label="Instagram">
              <i className="fa fa-instagram"></i>
            </a>
          </li>
        </ul>
      </div>
    </footer>
  );
}
