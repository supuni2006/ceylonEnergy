import { useEffect } from "react";

export default function Lightbox({ srcs, index, onClose, onChangeIndex }) {
  function step(dir) {
    onChangeIndex((index + dir + srcs.length) % srcs.length);
  }

  useEffect(() => {
    function onKeyDown(e) {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowRight") step(1);
      if (e.key === "ArrowLeft") step(-1);
    }
    window.addEventListener("keydown", onKeyDown);
    return () => window.removeEventListener("keydown", onKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [index, srcs.length]);

  return (
    <div
      className="lightbox is-open"
      id="lightbox"
      onClick={(e) => {
        if (e.target.id === "lightbox") onClose();
      }}
    >
      <button className="lightbox-close" aria-label="Close" onClick={onClose}>
        ✕
      </button>
      <button className="lightbox-nav prev" aria-label="Previous" onClick={() => step(-1)}>
        ‹
      </button>
      <img id="lbImg" src={srcs[index]} alt="Project photo" />
      <button className="lightbox-nav next" aria-label="Next" onClick={() => step(1)}>
        ›
      </button>
    </div>
  );
}
