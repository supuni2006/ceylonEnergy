import { useEffect } from "react";
import gsap from "gsap";
import { ScrollTrigger } from "gsap/ScrollTrigger";

let registered = false;

/**
 * Registers GSAP ScrollTrigger reveal animations for .reveal, .wwd-card
 * and .pkg-card elements currently in the DOM. Call this after content
 * that contains those classes has mounted (deps controls re-run timing).
 */
export function useScrollReveal(deps = []) {
  useEffect(() => {
    if (!registered) {
      gsap.registerPlugin(ScrollTrigger);
      registered = true;
    }

    const ctx = gsap.context(() => {
      gsap.utils.toArray(".reveal").forEach((el) => {
        gsap.fromTo(
          el,
          { opacity: 0, y: 28 },
          {
            opacity: 1,
            y: 0,
            duration: 0.9,
            ease: "power3.out",
            scrollTrigger: { trigger: el, start: "top 88%" },
          }
        );
      });

      gsap.utils.toArray(".wwd-card").forEach((el, i) => {
        gsap.fromTo(
          el,
          { opacity: 0, y: 36 },
          {
            opacity: 1,
            y: 0,
            duration: 0.7,
            ease: "power3.out",
            delay: (i % 3) * 0.08,
            scrollTrigger: { trigger: el, start: "top 92%" },
          }
        );
      });

      gsap.utils.toArray(".pkg-card").forEach((el, i) => {
        gsap.fromTo(
          el,
          { opacity: 0, y: 36 },
          {
            opacity: 1,
            y: 0,
            duration: 0.7,
            ease: "power3.out",
            delay: i * 0.08,
            scrollTrigger: { trigger: el, start: "top 92%" },
          }
        );
      });
    });

    // Recalculate positions after layout settles (images loading, etc).
    const refresh = () => ScrollTrigger.refresh();
    window.addEventListener("load", refresh);

    return () => {
      window.removeEventListener("load", refresh);
      ctx.revert();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, deps);
}

/**
 * Reveal animation for dynamically-swapped gallery cards
 * (albums / sub-albums / photo grid).
 */
export function revealCards(selector) {
  const cards = document.querySelectorAll(selector);
  if (window.gsap || true) {
    gsap.utils.toArray(selector).forEach((el, i) => {
      gsap.fromTo(
        el,
        { opacity: 0, scale: 0.94 },
        {
          opacity: 1,
          scale: 1,
          duration: 0.55,
          ease: "power2.out",
          delay: (i % 4) * 0.05,
          scrollTrigger: { trigger: el, start: "top 95%" },
        }
      );
    });
  } else {
    cards.forEach((el) => (el.style.opacity = 1));
  }
}
