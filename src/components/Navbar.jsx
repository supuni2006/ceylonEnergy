import { useEffect, useState } from "react";

const LINKS = [
  { href: "#home", label: "Home" },
  { href: "#about", label: "About" },
  { href: "#what-we-do", label: "Services" },
  { href: "#our-packages", label: "Packages" },
  { href: "#our-projects", label: "Projects" },
  { href: "#contact", label: "Contact" },
];

export default function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState("home");

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 12);
    window.addEventListener("scroll", onScroll, { passive: true });
    onScroll();
    return () => window.removeEventListener("scroll", onScroll);
  }, []);

  useEffect(() => {
    const sections = Array.from(document.querySelectorAll("section[id]"));
    const setActiveLink = () => {
      const pos = window.scrollY + window.innerHeight * 0.35;
      let current = sections[0] && sections[0].id;
      sections.forEach((s) => {
        if (pos >= s.offsetTop) current = s.id;
      });
      if (current) setActive(current);
    };
    window.addEventListener("scroll", setActiveLink, { passive: true });
    setActiveLink();
    return () => window.removeEventListener("scroll", setActiveLink);
  }, []);

  return (
    <nav className={`nav${scrolled ? " is-scrolled" : ""}`} id="siteNav">
      <div className="nav-inner">
        <a href="#home" className="brand">
          <img
            src="/images/logo@2x.ico"
            alt="Ceylon Energy & Engineering Services"
            className="brand-full-logo"
          />
        </a>
        <div className="nav-right">
          <ul className={`nav-links${open ? " is-open" : ""}`} id="navLinks">
            {LINKS.map((l) => (
              <li key={l.href}>
                <a
                  href={l.href}
                  className={active === l.href.slice(1) ? "active" : ""}
                  onClick={() => setOpen(false)}
                >
                  {l.label}
                </a>
              </li>
            ))}
          </ul>
          <button
            className={`nav-toggle${open ? " is-open" : ""}`}
            id="navToggle"
            aria-label="Toggle menu"
            aria-expanded={open}
            onClick={() => setOpen((o) => !o)}
          >
            <span></span>
            <span></span>
            <span></span>
          </button>
        </div>
      </div>
    </nav>
  );
}
