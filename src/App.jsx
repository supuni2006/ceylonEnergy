import { useEffect, useRef } from "react";
import gsap from "gsap";
import Navbar from "./components/Navbar.jsx";
import Hero from "./components/Hero.jsx";
import About from "./components/About.jsx";
import WhatWeDo from "./components/WhatWeDo.jsx";
import Packages from "./components/Packages.jsx";
import Projects from "./components/Projects.jsx";
import Contact from "./components/Contact.jsx";
import Footer from "./components/Footer.jsx";
import { useScrollReveal } from "./hooks/useScrollReveal.js";

export default function App() {
  const cursorRef = useRef(null);

  useScrollReveal();

  // Custom cursor + tilt effect (desktop / non-touch only)
  useEffect(() => {
    const isTouch =
      window.matchMedia &&
      window.matchMedia("(hover: none), (pointer: coarse)").matches;
    if (isTouch) return;

    const cursor = cursorRef.current;
    if (!cursor) return;

    let setX, setY;
    if (window.gsap) {
      setX = gsap.quickTo(cursor, "x", { duration: 0.35, ease: "power3" });
      setY = gsap.quickTo(cursor, "y", { duration: 0.35, ease: "power3" });
    }

    function onMouseMove(e) {
      cursor.classList.add("is-visible");
      if (setX) {
        setX(e.clientX);
        setY(e.clientY);
      } else {
        cursor.style.left = e.clientX + "px";
        cursor.style.top = e.clientY + "px";
      }
    }
    window.addEventListener("mousemove", onMouseMove);

    function bindHoverTargets() {
      const targets = document.querySelectorAll("a, button, [data-tilt]");
      const onEnter = () => cursor.classList.add("is-active");
      const onLeave = () => cursor.classList.remove("is-active");
      targets.forEach((el) => {
        el.addEventListener("mouseenter", onEnter);
        el.addEventListener("mouseleave", onLeave);
      });
      return () => {
        targets.forEach((el) => {
          el.removeEventListener("mouseenter", onEnter);
          el.removeEventListener("mouseleave", onLeave);
        });
      };
    }
    const unbind = bindHoverTargets();

    // tilt for [data-tilt] elements
    let cleanupTilt = () => {};
    if (window.gsap) {
      const tiltEls = document.querySelectorAll("[data-tilt]");
      const handlers = [];
      tiltEls.forEach((el) => {
        const rotX = gsap.quickTo(el, "rotateX", { duration: 0.5, ease: "power3" });
        const rotY = gsap.quickTo(el, "rotateY", { duration: 0.5, ease: "power3" });
        el.style.transformStyle = "preserve-3d";
        const onMove = (e) => {
          const r = el.getBoundingClientRect();
          const px = (e.clientX - r.left) / r.width - 0.5;
          const py = (e.clientY - r.top) / r.height - 0.5;
          rotY(px * 10);
          rotX(py * -10);
        };
        const onLeave = () => {
          rotX(0);
          rotY(0);
        };
        el.addEventListener("mousemove", onMove);
        el.addEventListener("mouseleave", onLeave);
        handlers.push({ el, onMove, onLeave });
      });
      cleanupTilt = () => {
        handlers.forEach(({ el, onMove, onLeave }) => {
          el.removeEventListener("mousemove", onMove);
          el.removeEventListener("mouseleave", onLeave);
        });
      };
    }

    return () => {
      window.removeEventListener("mousemove", onMouseMove);
      unbind();
      cleanupTilt();
    };
  }, []);

  return (
    <>
      <div className="grain"></div>
      <div className="energy-cursor" id="cursor" ref={cursorRef}></div>

      <Navbar />
      <Hero />
      <About />
      <WhatWeDo />
      <Packages />
      <Projects />
      <Contact />
      <Footer />
    </>
  );
}
