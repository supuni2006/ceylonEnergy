import { useEffect, useState } from "react";
import { ALBUMS, photoSrc } from "../data/albums.js";
import { revealCards } from "../hooks/useScrollReveal.js";
import Lightbox from "./Lightbox.jsx";

// view: "albums" | "projects" | "photos"
export default function Projects() {
  const [view, setView] = useState("albums");
  const [albumIdx, setAlbumIdx] = useState(null);
  const [projectIdx, setProjectIdx] = useState(null);
  const [lightboxIdx, setLightboxIdx] = useState(null);

  const activeAlbum = albumIdx !== null ? ALBUMS[albumIdx] : null;
  const activeProject =
    activeAlbum && projectIdx !== null ? activeAlbum.projects[projectIdx] : null;
  const activeSrcs = activeProject ? activeProject.photos.map(photoSrc) : [];

  useEffect(() => {
    const selector =
      view === "albums" ? ".album-card" : view === "projects" ? ".album-card" : ".proj-item";
    // Let the DOM paint first, then animate in.
    const id = requestAnimationFrame(() => revealCards(selector));
    return () => cancelAnimationFrame(id);
  }, [view, albumIdx, projectIdx]);

  function openAlbum(i) {
    setAlbumIdx(i);
    setProjectIdx(null);
    setView("projects");
  }
  function openProject(i) {
    setProjectIdx(i);
    setView("photos");
  }
  function backToAlbums() {
    setAlbumIdx(null);
    setProjectIdx(null);
    setView("albums");
  }
  function backToProjects() {
    setProjectIdx(null);
    setView("projects");
  }

  return (
    <section id="our-projects" className="wrap-panel pad">
      <div className="container">
        <div className="section-head center">
          <p className="eyebrow center">Field Work</p>
          <h2 className="section-title reveal">Our Projects</h2>
          <p className="lede reveal">
            Browse by site — pick a location, then a project, to see the photos.
          </p>
        </div>
      </div>
      <div className="container">
        <nav className="gallery-crumbs" aria-label="Gallery breadcrumb">
          <button type="button" onClick={backToAlbums}>
            All Locations
          </button>
          {activeAlbum && (
            <>
              <span className="sep">/</span>
              {view === "photos" ? (
                <button type="button" onClick={backToProjects}>
                  {activeAlbum.name}
                </button>
              ) : (
                <span className="current">{activeAlbum.name}</span>
              )}
              {view === "photos" && activeProject && (
                <>
                  <span className="sep">/</span>
                  <span className="current">{activeProject.name}</span>
                </>
              )}
            </>
          )}
        </nav>

        {/* Level 1: locations */}
        {view === "albums" && (
          <div className="album-grid">
            {ALBUMS.map((loc, i) => {
              const totalPhotos = loc.projects.reduce(
                (sum, p) => sum + p.photos.length,
                0
              );
              const cover = photoSrc(loc.projects[0].photos[0]);
              return (
                <div
                  className="album-card reveal"
                  key={loc.name}
                  onClick={() => openAlbum(i)}
                >
                  <img
                    src={cover}
                    alt={`${loc.name} — completed solar projects`}
                    loading="lazy"
                  />
                  <div className="album-label">
                    <h4>{loc.name}</h4>
                    <span>
                      {loc.projects.length}
                      {loc.projects.length === 1 ? " project" : " projects"} ·{" "}
                      {totalPhotos} photos
                    </span>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Level 2: projects within a location */}
        {view === "projects" && activeAlbum && (
          <div className="album-grid">
            {activeAlbum.projects.map((proj, i) => {
              const cover = photoSrc(proj.photos[0]);
              return (
                <div
                  className="album-card reveal"
                  key={proj.name}
                  onClick={() => openProject(i)}
                >
                  <img
                    src={cover}
                    alt={`${activeAlbum.name} ${proj.name}`}
                    loading="lazy"
                  />
                  <div className="album-label">
                    <h4>{proj.name}</h4>
                    <span>{proj.photos.length} photos</span>
                  </div>
                </div>
              );
            })}
          </div>
        )}

        {/* Level 3: photos within a project */}
        {view === "photos" && activeAlbum && activeProject && (
          <div className="proj-grid">
            {activeSrcs.map((s, i) => (
              <div
                className="proj-item reveal"
                key={s}
                onClick={() => setLightboxIdx(i)}
              >
                <img
                  src={s}
                  alt={`${activeAlbum.name} ${activeProject.name} photo ${i + 1}`}
                  loading="lazy"
                />
                <span className="plus"></span>
              </div>
            ))}
          </div>
        )}
      </div>

      {lightboxIdx !== null && (
        <Lightbox
          srcs={activeSrcs}
          index={lightboxIdx}
          onClose={() => setLightboxIdx(null)}
          onChangeIndex={setLightboxIdx}
        />
      )}
    </section>
  );
}
