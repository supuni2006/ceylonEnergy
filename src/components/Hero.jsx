import { useEffect, useRef, useState } from "react";
import gsap from "gsap";
import { useHeroCanvas } from "../hooks/useHeroCanvas.js";

const SLIDES = ["/images/hero/solar-hero.png", "/images/slider/slider-4.jpg"];

export default function Hero() {
  const canvasRef = useRef(null);
  const headingRef = useRef(null);
  const [activeSlide, setActiveSlide] = useState(0);

  useHeroCanvas(canvasRef);

  useEffect(() => {
    const reduceMotion =
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduceMotion || SLIDES.length < 2) return;
    const id = setInterval(() => {
      setActiveSlide((i) => (i + 1) % SLIDES.length);
    }, 6000);
    return () => clearInterval(id);
  }, []);

  useEffect(() => {
    if (!window.gsap) return;
    const lines = headingRef.current?.querySelectorAll(".line span");
    if (!lines || !lines.length) return;
    gsap.set(lines, { yPercent: 110 });
    gsap
      .timeline({ delay: 0.2 })
      .to(lines, { yPercent: 0, duration: 1, ease: "power4.out", stagger: 0.12 })
      .from(".hero .eyebrow", { opacity: 0, y: -10, duration: 0.6 }, 0)
      .from(
        ".hero-sub, .hero-cta, .hero-stats",
        { opacity: 0, y: 18, duration: 0.8, stagger: 0.08 },
        "-=0.5"
      );
  }, []);

  return (
    <section className="hero" id="home">
      <div className="hero-bg">
        {SLIDES.map((src, i) => (
          <img
            key={src}
            className={`hero-bg-img${i === activeSlide ? " is-active" : ""}`}
            src={src}
            alt={
              i === 0
                ? "Rows of solar panels under a clear blue sky"
                : "Colombo city skyline lit up at dusk"
            }
          />
        ))}
      </div>
      <div className="hero-canvas" id="heroCanvas" ref={canvasRef}></div>
      <div className="hero-scrim"></div>
      <div className="hero-content">
        <div className="hero-panel">
          <p className="eyebrow gold">Solar · Electrical · Security · Structural</p>
          <h1 id="heroHeading" ref={headingRef}>
            <span className="line">
              <span>
                Ceylon <em className="accent">Energy &</em>
              </span>
            </span>
            <span className="line">
              <span>Engineering Services</span>
            </span>
          </h1>
          <p className="hero-sub">
            Powering and protecting Sri Lankan homes and businesses with solar,
            security, lightning protection, and structural solutions.
          </p>
          <div className="hero-cta">
            <a href="#contact" className="btn">
              <span>Get a Quote</span>
            </a>
            <a href="#our-projects" className="btn btn-ghost">
              <span>See Our Work</span>
            </a>
          </div>
          <div className="hero-stats">
            <div className="stat">
              <b>500+</b>
              <span>Completed Projects</span>
            </div>
            <div className="stat">
              <b>06</b>
              <span>Service Disciplines</span>
            </div>
          </div>
        </div>
      </div>
      <div className="scroll-cue">
        <div className="wire">
          <i></i>
        </div>
        Scroll
      </div>
    </section>
  );
}
