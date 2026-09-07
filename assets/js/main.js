/* Ceylon Energy Services — interaction layer
   Three.js energy-grid hero + GSAP scroll choreography */
(function(){
  "use strict";

  var reduceMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
  var isTouch = window.matchMedia && window.matchMedia("(hover: none), (pointer: coarse)").matches;

  /* ============================================================
     HERO BACKGROUND SLIDESHOW
     ============================================================ */
  var heroSlides = document.querySelectorAll(".hero-bg-img");
  if (heroSlides.length > 1 && !reduceMotion){
    var heroSlideIdx = 0;
    setInterval(function(){
      heroSlides[heroSlideIdx].classList.remove("is-active");
      heroSlideIdx = (heroSlideIdx + 1) % heroSlides.length;
      heroSlides[heroSlideIdx].classList.add("is-active");
    }, 6000);
  }

  /* ============================================================
     NAV
     ============================================================ */
  var nav = document.getElementById("siteNav");
  var navToggle = document.getElementById("navToggle");
  var navLinks = document.getElementById("navLinks");

  window.addEventListener("scroll", function(){
    nav.classList.toggle("is-scrolled", window.scrollY > 12);
  }, { passive:true });

  navToggle.addEventListener("click", function(){
    var open = navLinks.classList.toggle("is-open");
    navToggle.classList.toggle("is-open", open);
    navToggle.setAttribute("aria-expanded", open);
  });
  navLinks.querySelectorAll("a").forEach(function(a){
    a.addEventListener("click", function(){
      navLinks.classList.remove("is-open");
      navToggle.classList.remove("is-open");
      navToggle.setAttribute("aria-expanded", "false");
    });
  });

  var sections = document.querySelectorAll("section[id]");
  var navA = navLinks.querySelectorAll("a");
  function setActiveLink(){
    var pos = window.scrollY + window.innerHeight * 0.35;
    var current = sections[0] && sections[0].id;
    sections.forEach(function(s){
      if (pos >= s.offsetTop) current = s.id;
    });
    navA.forEach(function(a){
      a.classList.toggle("active", a.getAttribute("href") === "#" + current);
    });
  }
  window.addEventListener("scroll", setActiveLink, { passive:true });
  setActiveLink();

  /* ============================================================
     CUSTOM CURSOR
     ============================================================ */
  if (!isTouch) {
    var cursor = document.getElementById("cursor");
    var cx = window.innerWidth/2, cy = window.innerHeight/2;
    var setX, setY;
    if (window.gsap) {
      setX = gsap.quickTo(cursor, "x", { duration: 0.35, ease: "power3" });
      setY = gsap.quickTo(cursor, "y", { duration: 0.35, ease: "power3" });
    }
    window.addEventListener("mousemove", function(e){
      cx = e.clientX; cy = e.clientY;
      cursor.classList.add("is-visible");
      if (setX) { setX(cx); setY(cy); } else { cursor.style.left = cx+"px"; cursor.style.top = cy+"px"; }
    });
    document.querySelectorAll("a, button, [data-tilt]").forEach(function(el){
      el.addEventListener("mouseenter", function(){ cursor.classList.add("is-active"); });
      el.addEventListener("mouseleave", function(){ cursor.classList.remove("is-active"); });
    });
  }

  /* ============================================================
     TILT (data-tilt elements react to mouse)
     ============================================================ */
  if (!isTouch && window.gsap) {
    document.querySelectorAll("[data-tilt]").forEach(function(el){
      var rotX = gsap.quickTo(el, "rotateX", { duration: 0.5, ease: "power3" });
      var rotY = gsap.quickTo(el, "rotateY", { duration: 0.5, ease: "power3" });
      el.style.transformStyle = "preserve-3d";
      el.addEventListener("mousemove", function(e){
        var r = el.getBoundingClientRect();
        var px = (e.clientX - r.left) / r.width - 0.5;
        var py = (e.clientY - r.top) / r.height - 0.5;
        rotY(px * 10);
        rotX(py * -10);
      });
      el.addEventListener("mouseleave", function(){ rotX(0); rotY(0); });
    });
  }

  /* ============================================================
     GSAP SCROLL REVEALS
     ============================================================
     If gsap/ScrollTrigger fail to load (CDN outage, ad blocker,
     offline network, etc.), every ".reveal"/".wwd-card"/".pkg-card"
     element would otherwise stay permanently invisible, since the
     CSS default is opacity:0 and only the GSAP tween ever sets it
     back to 1. The "else" branch below is the fallback that was
     missing here (the gallery's revealCards() below already had
     the equivalent fallback) — it just shows everything immediately,
     without the scroll animation, so content is never lost.
     ============================================================ */
  if (window.gsap && window.ScrollTrigger) {
    gsap.registerPlugin(ScrollTrigger);

    gsap.utils.toArray(".reveal").forEach(function(el, i){
      gsap.fromTo(el, { opacity: 0, y: 28 }, {
        opacity: 1, y: 0, duration: 0.9, ease: "power3.out",
        scrollTrigger: { trigger: el, start: "top 88%" }
      });
    });

    gsap.utils.toArray(".wwd-card").forEach(function(el, i){
      gsap.fromTo(el, { opacity: 0, y: 36 }, {
        opacity: 1, y: 0, duration: 0.7, ease: "power3.out", delay: (i % 3) * 0.08,
        scrollTrigger: { trigger: el, start: "top 92%" }
      });
    });

    gsap.utils.toArray(".pkg-card").forEach(function(el, i){
      gsap.fromTo(el, { opacity: 0, y: 36 }, {
        opacity: 1, y: 0, duration: 0.7, ease: "power3.out", delay: i * 0.08,
        scrollTrigger: { trigger: el, start: "top 92%" }
      });
    });

    /* hero entrance */
    var lines = document.querySelectorAll("#heroHeading .line span");
    gsap.set(lines, { yPercent: 110 });
    gsap.timeline({ delay: 0.2 })
      .to(lines, { yPercent: 0, duration: 1, ease: "power4.out", stagger: 0.12 })
      .from(".hero .eyebrow", { opacity: 0, y: -10, duration: 0.6 }, 0)
      .from(".hero-sub, .hero-cta, .hero-stats", { opacity: 0, y: 18, duration: 0.8, stagger: 0.08 }, "-=0.5");
  } else {
    document.querySelectorAll(".reveal, .wwd-card, .pkg-card").forEach(function(el){
      el.style.opacity = 1;
    });
  }

  /* ============================================================
     PROJECT GALLERY — LOCATIONS -> PROJECTS (SUB-ALBUMS) -> PHOTOS
     ============================================================
     EDIT THIS DATA to match your real sites/projects/photos.
     Each location = one album (e.g. "Belihuloya").
     Each location has one or more projects (sub-albums, e.g. "Project 01").
     Each project has a list of photo files from
     assets/images/completed-projects/
     The FIRST photo in each project is used as its cover image,
     and the first project's first photo is used as the location cover.
     ============================================================ */
  var CP = "assets/images/completed-projects/";
  var PROJECTS_JSON_PATH = "assets/data/projects.json";

  // Fallback only — used if assets/data/projects.json can't be fetched
  // (e.g. opened as a local file). The admin "Projects" page is the real,
  // editable source of truth once the site is served normally.
  var DEFAULT_ALBUMS = [
    {
      name: "Rathnapura",
      projects: [
        { name: "Belihuloya project 01", photos: [1,3,4,5] },
        { name: "Belihuloya project 02", photos: [8,7,6,9] },
        { name: "Sabaragamuwa University", photos: [39,37,38,36] },
        { name: "Udawalawa project", photos: [42,41,40] }

      ]
    },
    {
      name: "Colombo",
      projects: [
        { name: "Project 01", photos: [10,11,12,13] },
        { name: "Project 02", photos: [14,16,17] },
        { name: "Project 03", photos: [46,47,48] },
        { name: "Wellampitiya", photos: [43,44,45] },
        { name: "Moratuwa", photos: [49,50,51] },
        { name: "Microchip Solution - Moratuwa", photos: [58,56,57,55,59] },
        
        
      ]
    },
    {
      name: "Gampaha",
      projects: [
        { name: "Wattala (I C M Perera 's site) ", photos: [63,64,65,68] }
      ]
    },
    {
      name: "piliyandala",
      projects: [
        { name: "B C D Mendis 's site", photos: [67,69,70] }
      ]
    },
    {
      name: "Dehiwala",
      projects: [
        { name: "Project 01", photos: [30,31] },
        { name: "G S Indika Perera's site", photos: [60,61,62] }
      ]
    },
    {
      name: "Kaluthara",
      projects: [
        { name: "Project 01", photos: [33,34] }
      ]
    },
    {
      name: "Bandaragama",
      projects: [
        { name: "Project 01", photos: [52,53,54] }
      ]
    }
  ];

  function initGallery(ALBUMS){
    var crumbs      = document.getElementById("galleryCrumbs");
    var albumGrid    = document.getElementById("albumGrid");
    var subAlbumGrid = document.getElementById("subAlbumGrid");
    var photoGrid    = document.getElementById("projGrid");
    var lightbox = document.getElementById("lightbox");
    var lbImg    = document.getElementById("lbImg");
    var lbIndex  = 0;
    var activeSrcs = [];
    var activeAlbum = null;

    function src(n){ return CP + "project-" + n + ".jpg"; }

    function revealCards(selector){
      var cards = document.querySelectorAll(selector);
      if (window.gsap && window.ScrollTrigger) {
        gsap.utils.toArray(selector).forEach(function(el, i){
          gsap.fromTo(el, { opacity: 0, scale: 0.94 }, {
            opacity: 1, scale: 1, duration: 0.55, ease: "power2.out", delay: (i % 4) * 0.05,
            scrollTrigger: { trigger: el, start: "top 95%" }
          });
        });
      } else {
        cards.forEach(function(el){ el.style.opacity = 1; });
      }
    }

    function showView(view){
      albumGrid.hidden    = view !== "albums";
      subAlbumGrid.hidden = view !== "projects";
      photoGrid.hidden    = view !== "photos";
    }

    function renderCrumbs(){
      var parts = [];
      parts.push('<button type="button" data-nav="albums">All Locations</button>');
      if (activeAlbum){
        parts.push('<span class="sep">/</span>');
        if (photoGrid.hidden){
          parts.push('<span class="current">'+activeAlbum.name+'</span>');
        } else {
          parts.push('<button type="button" data-nav="projects">'+activeAlbum.name+'</button>');
          parts.push('<span class="sep">/</span>');
          parts.push('<span class="current">'+activeAlbum.activeProjectName+'</span>');
        }
      }
      crumbs.innerHTML = parts.join(" ");
    }

    function renderAlbums(){
      albumGrid.innerHTML = "";
      ALBUMS.forEach(function(loc, i){
        var totalPhotos = loc.projects.reduce(function(sum, p){ return sum + p.photos.length; }, 0);
        var cover = src(loc.projects[0].photos[0]);
        var card = document.createElement("div");
        card.className = "album-card reveal";
        card.setAttribute("data-album", i);
        card.innerHTML =
          '<img src="'+cover+'" alt="'+loc.name+' — completed solar projects" loading="lazy">' +
          '<div class="album-label"><h4>'+loc.name+'</h4><span>' +
          loc.projects.length + (loc.projects.length === 1 ? ' project' : ' projects') +
          ' · ' + totalPhotos + ' photos</span></div>';
        albumGrid.appendChild(card);
      });
      revealCards(".album-card");
    }

    function renderProjects(loc){
      activeAlbum = loc;
      subAlbumGrid.innerHTML = "";
      loc.projects.forEach(function(proj, i){
        var cover = src(proj.photos[0]);
        var card = document.createElement("div");
        card.className = "album-card reveal";
        card.setAttribute("data-project", i);
        card.innerHTML =
          '<img src="'+cover+'" alt="'+loc.name+' '+proj.name+'" loading="lazy">' +
          '<div class="album-label"><h4>'+proj.name+'</h4><span>' +
          proj.photos.length + ' photos</span></div>';
        subAlbumGrid.appendChild(card);
      });
      showView("projects");
      renderCrumbs();
      revealCards(".album-card");
    }

    function renderPhotos(loc, proj){
      activeAlbum = loc;
      loc.activeProjectName = proj.name;
      activeSrcs = proj.photos.map(src);
      photoGrid.innerHTML = "";
      activeSrcs.forEach(function(s, i){
        var item = document.createElement("div");
        item.className = "proj-item reveal";
        item.setAttribute("data-index", i);
        item.innerHTML = '<img src="'+s+'" alt="'+loc.name+' '+proj.name+' photo '+(i+1)+'" loading="lazy"><span class="plus"></span>';
        photoGrid.appendChild(item);
      });
      showView("photos");
      renderCrumbs();
      revealCards(".proj-item");
    }

    albumGrid.addEventListener("click", function(e){
      var card = e.target.closest(".album-card");
      if (!card) return;
      var loc = ALBUMS[parseInt(card.getAttribute("data-album"), 10)];
      renderProjects(loc);
    });

    subAlbumGrid.addEventListener("click", function(e){
      var card = e.target.closest(".album-card");
      if (!card || !activeAlbum) return;
      var proj = activeAlbum.projects[parseInt(card.getAttribute("data-project"), 10)];
      renderPhotos(activeAlbum, proj);
    });

    crumbs.addEventListener("click", function(e){
      var btn = e.target.closest("button[data-nav]");
      if (!btn) return;
      var nav = btn.getAttribute("data-nav");
      if (nav === "albums"){
        activeAlbum = null;
        showView("albums");
        renderCrumbs();
      } else if (nav === "projects" && activeAlbum){
        renderProjects(activeAlbum);
      }
    });

    function openLightbox(i){
      lbIndex = i;
      lbImg.src = activeSrcs[lbIndex];
      lightbox.classList.add("is-open");
    }
    function closeLightbox(){ lightbox.classList.remove("is-open"); }
    function stepLightbox(dir){
      lbIndex = (lbIndex + dir + activeSrcs.length) % activeSrcs.length;
      lbImg.src = activeSrcs[lbIndex];
    }
    photoGrid.addEventListener("click", function(e){
      var item = e.target.closest(".proj-item");
      if (!item) return;
      openLightbox(parseInt(item.getAttribute("data-index"), 10));
    });
    document.getElementById("lbClose").addEventListener("click", closeLightbox);
    document.getElementById("lbPrev").addEventListener("click", function(){ stepLightbox(-1); });
    document.getElementById("lbNext").addEventListener("click", function(){ stepLightbox(1); });
    lightbox.addEventListener("click", function(e){ if (e.target === lightbox) closeLightbox(); });
    window.addEventListener("keydown", function(e){
      if (!lightbox.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowRight") stepLightbox(1);
      if (e.key === "ArrowLeft") stepLightbox(-1);
    });

    showView("albums");
    renderCrumbs();
    renderAlbums();
  }

  if (window.fetch){
    fetch(PROJECTS_JSON_PATH, { cache: "no-store" })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){ initGallery(Array.isArray(data) && data.length ? data : DEFAULT_ALBUMS); })
      .catch(function(){ initGallery(DEFAULT_ALBUMS); });
  } else {
    initGallery(DEFAULT_ALBUMS);
  }

  /* ============================================================
     CONTACT FORM
     ============================================================ */
  var form = document.getElementById("contactForm");
  var note = document.getElementById("formNote");
  var submitBtn = document.getElementById("submitBtn");
  form.addEventListener("submit", function(e){
    e.preventDefault();
    note.className = "form-note";
    note.textContent = "Sending...";
    submitBtn.setAttribute("disabled", "disabled");
    var data = new FormData(form);
    fetch(form.getAttribute("action"), { method: "POST", body: data })
      .then(function(res){
        note.className = "form-note ok";
        note.textContent = "Thank you — we'll be in touch shortly.";
        form.reset();
      })
      .catch(function(){
        note.className = "form-note err";
        note.textContent = "Couldn't send automatically — please email info@ceylonenergyservices.com directly.";
      })
      .finally(function(){ submitBtn.removeAttribute("disabled"); });
  });

  /* ============================================================
     THREE.JS — INTERACTIVE ENERGY GRID
     ============================================================ */
  (function initHero(){
    var mount = document.getElementById("heroCanvas");
    if (!window.THREE || !mount) return;

    var W = mount.clientWidth, H = mount.clientHeight;
    var scene = new THREE.Scene();
    var camera = new THREE.PerspectiveCamera(52, W / H, 0.1, 100);
    camera.position.set(0, 0, 14);

    var renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 1.8));
    renderer.setSize(W, H);
    mount.appendChild(renderer.domElement);

    /* ---- node field ---- */
    var isMobile = W < 720;
    var COUNT = reduceMotion ? 0 : (isMobile ? 46 : 100);
    var BOUND_X = 11, BOUND_Y = 6, BOUND_Z = 4;

    var rest = [];
    var pos = [];
    for (var i = 0; i < COUNT; i++) {
      var p = {
        x: (Math.random() * 2 - 1) * BOUND_X,
        y: (Math.random() * 2 - 1) * BOUND_Y,
        z: (Math.random() * 2 - 1) * BOUND_Z
      };
      rest.push(p);
      pos.push(p.x, p.y, p.z);
      rest[i].phase = Math.random() * Math.PI * 2;
    }
    var positions = new Float32Array(pos);
    var current = positions.slice();

    /* point sprite texture */
    function makeDot(color){
      var c = document.createElement("canvas"); c.width = c.height = 64;
      var ctx = c.getContext("2d");
      var g = ctx.createRadialGradient(32,32,0,32,32,32);
      g.addColorStop(0, color); g.addColorStop(1, "rgba(0,0,0,0)");
      ctx.fillStyle = g; ctx.fillRect(0,0,64,64);
      return new THREE.CanvasTexture(c);
    }

    var pointsGeo = new THREE.BufferGeometry();
    pointsGeo.setAttribute("position", new THREE.BufferAttribute(current, 3));
    var pointsMat = new THREE.PointsMaterial({
      size: isMobile ? 0.22 : 0.26,
      map: makeDot("rgba(255,255,255,1)"),
      transparent: true, depthWrite: false,
      blending: THREE.AdditiveBlending,
      sizeAttenuation: true
    });
    var pointCloud = new THREE.Points(pointsGeo, pointsMat);
    scene.add(pointCloud);

    /* ---- edges: connect nearby nodes (computed once on rest layout) ---- */
    var edgeIdx = [];
    var maxDist = isMobile ? 3.4 : 3.1;
    for (var a = 0; a < COUNT; a++) {
      for (var b = a + 1; b < COUNT; b++) {
        var dx = rest[a].x - rest[b].x, dy = rest[a].y - rest[b].y, dz = rest[a].z - rest[b].z;
        var d = Math.sqrt(dx*dx + dy*dy + dz*dz);
        if (d < maxDist) edgeIdx.push([a, b, d]);
      }
    }
    var linePositions = new Float32Array(edgeIdx.length * 6);
    var lineGeo = new THREE.BufferGeometry();
    lineGeo.setAttribute("position", new THREE.BufferAttribute(linePositions, 3));
    var lineMat = new THREE.LineBasicMaterial({ color: 0xdff2fc, transparent: true, opacity: 0.28 });
    var lineSegs = new THREE.LineSegments(lineGeo, lineMat);
    scene.add(lineSegs);

    /* ---- traveling pulses along a subset of shortest edges ---- */
    var pulseEdges = edgeIdx.slice().sort(function(x,y){ return x[2]-y[2]; }).slice(0, isMobile ? 8 : 16);
    var pulseGeo = new THREE.BufferGeometry();
    var pulsePos = new Float32Array(pulseEdges.length * 3);
    pulseGeo.setAttribute("position", new THREE.BufferAttribute(pulsePos, 3));
    var pulseMat = new THREE.PointsMaterial({
      size: isMobile ? 0.34 : 0.4,
      map: makeDot("rgba(120,207,255,1)"),
      transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, sizeAttenuation: true
    });
    var pulsePoints = new THREE.Points(pulseGeo, pulseMat);
    scene.add(pulsePoints);
    var pulseT = pulseEdges.map(function(){ return Math.random(); });

    /* ---- mouse tracking ---- */
    var mouse = new THREE.Vector2(0, 0);
    var mouseWorld = new THREE.Vector3(9999, 9999, 0);
    var raycaster = new THREE.Raycaster();
    var plane = new THREE.Plane(new THREE.Vector3(0,0,1), 0);
    var targetCamX = 0, targetCamY = 0;

    function onMove(clientX, clientY){
      var r = mount.getBoundingClientRect();
      mouse.x = ((clientX - r.left) / r.width) * 2 - 1;
      mouse.y = -((clientY - r.top) / r.height) * 2 + 1;
      raycaster.setFromCamera(mouse, camera);
      var hit = new THREE.Vector3();
      raycaster.ray.intersectPlane(plane, hit);
      if (hit) mouseWorld.copy(hit);
      targetCamX = mouse.x * 0.6;
      targetCamY = mouse.y * 0.35;
    }
    window.addEventListener("mousemove", function(e){ onMove(e.clientX, e.clientY); });
    window.addEventListener("touchmove", function(e){
      if (e.touches && e.touches[0]) onMove(e.touches[0].clientX, e.touches[0].clientY);
    }, { passive:true });

    window.addEventListener("resize", function(){
      W = mount.clientWidth; H = mount.clientHeight;
      camera.aspect = W / H; camera.updateProjectionMatrix();
      renderer.setSize(W, H);
    });

    var REPEL_RADIUS = 3.2, REPEL_STRENGTH = 1.6;
    var clock = new THREE.Clock();

    function animate(){
      requestAnimationFrame(animate);
      var t = clock.getElapsedTime();

      camera.position.x += (targetCamX - camera.position.x) * 0.03;
      camera.position.y += (targetCamY - camera.position.y) * 0.03;
      camera.lookAt(0,0,0);

      var arr = pointsGeo.attributes.position.array;
      for (var i = 0; i < COUNT; i++) {
        var ix = i*3;
        var rx = rest[i].x + Math.sin(t*0.3 + rest[i].phase) * 0.35;
        var ry = rest[i].y + Math.cos(t*0.25 + rest[i].phase) * 0.35;
        var rz = rest[i].z;

        var dx = rx - mouseWorld.x, dy = ry - mouseWorld.y;
        var dist = Math.sqrt(dx*dx + dy*dy);
        if (dist < REPEL_RADIUS) {
          var force = (1 - dist / REPEL_RADIUS) * REPEL_STRENGTH;
          var nx = dx / (dist || 1), ny = dy / (dist || 1);
          rx += nx * force;
          ry += ny * force;
        }
        arr[ix] = rx; arr[ix+1] = ry; arr[ix+2] = rz;
      }
      pointsGeo.attributes.position.needsUpdate = true;

      var larr = lineGeo.attributes.position.array;
      for (var e = 0; e < edgeIdx.length; e++) {
        var ai = edgeIdx[e][0]*3, bi = edgeIdx[e][1]*3;
        var li = e*6;
        larr[li] = arr[ai]; larr[li+1] = arr[ai+1]; larr[li+2] = arr[ai+2];
        larr[li+3] = arr[bi]; larr[li+4] = arr[bi+1]; larr[li+5] = arr[bi+2];
      }
      lineGeo.attributes.position.needsUpdate = true;

      var parr = pulseGeo.attributes.position.array;
      for (var p = 0; p < pulseEdges.length; p++) {
        pulseT[p] += 0.0035;
        if (pulseT[p] > 1) pulseT[p] = 0;
        var ai2 = pulseEdges[p][0]*3, bi2 = pulseEdges[p][1]*3;
        var pi = p*3;
        parr[pi]   = arr[ai2]   + (arr[bi2]   - arr[ai2])   * pulseT[p];
        parr[pi+1] = arr[ai2+1] + (arr[bi2+1] - arr[ai2+1]) * pulseT[p];
        parr[pi+2] = arr[ai2+2] + (arr[bi2+2] - arr[ai2+2]) * pulseT[p];
      }
      pulseGeo.attributes.position.needsUpdate = true;

      renderer.render(scene, camera);
    }
    animate();
  })();

})();

(function(){
  "use strict";

  // ---- the main hero profile (unchanged files/paths) ----------------------
  var MAIN_DOC = {
    id: "main",
    title: "Attatchments",
    imgPath: "assets/images/company-profile/",
    pdfPath: "assets/docs/Ceylon-Energy-Company-Profile.pdf",
    pageCount: 23 // fallback until profile-meta.json loads
  };
  var META_PATH = "assets/docs/profile-meta.json";
  var ATTACHMENTS_JSON_PATH = "assets/docs/attachments.json";

  var pad = function(n){ return n < 10 ? "0" + n : "" + n; };
  var pageSrc = function(doc, n){ return doc.imgPath + "page-" + pad(n) + ".jpg"; };

  var overlay = document.getElementById("pvOverlay");
  if (!overlay) return;

  var closeBtn = document.getElementById("pvClose");
  var prevBtn = document.getElementById("pvPrev");
  var nextBtn = document.getElementById("pvNext");
  var img = document.getElementById("pvImage");
  var count = document.getElementById("pvCount");
  var thumbsWrap = document.getElementById("pvThumbs");
  var downloadPageLink = document.getElementById("pvDownloadPage");
  var downloadFullLink = document.getElementById("pvDownloadFull");
  var docTitleEl = document.getElementById("pvDocTitle");
  var pageBadge = document.getElementById("cpPageBadge");
  var grid = document.getElementById("cpAttachmentsGrid");
  var moreToolbar = document.getElementById("cpMoreToolbar");

  var activeDoc = MAIN_DOC;
  var current = 1;
  var thumbEls = [];

  function applyMainPageCount(n){
    if (!n || n < 1) return;
    MAIN_DOC.pageCount = n;
    if (pageBadge) pageBadge.textContent = n + (n === 1 ? " page" : " pages");
  }

  if (window.fetch){
    fetch(META_PATH, { cache: "no-store" })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){ if (data && data.pageCount) applyMainPageCount(data.pageCount); })
      .catch(function(){ /* keep fallback page count */ });
  }

  function buildThumbs(doc){
    thumbsWrap.innerHTML = "";
    thumbEls = [];
    for (var i = 1; i <= doc.pageCount; i++){
      (function(n){
        var t = document.createElement("div");
        t.className = "pv-thumb";
        t.setAttribute("role", "button");
        t.setAttribute("aria-label", "Go to page " + n);
        var ti = document.createElement("img");
        ti.src = pageSrc(doc, n);
        ti.loading = "lazy";
        ti.alt = "Page " + n + " thumbnail";
        t.appendChild(ti);
        t.addEventListener("click", function(){ goTo(n); });
        thumbsWrap.appendChild(t);
        thumbEls.push(t);
      })(i);
    }
  }

  function goTo(n){
    if (n < 1) n = 1;
    if (n > activeDoc.pageCount) n = activeDoc.pageCount;
    current = n;
    img.src = pageSrc(activeDoc, current);
    img.alt = activeDoc.title + " — page " + current;
    count.textContent = "Page " + current + " / " + activeDoc.pageCount;
    downloadPageLink.href = pageSrc(activeDoc, current);
    downloadPageLink.setAttribute("download", activeDoc.title.replace(/[^a-z0-9]+/gi, "-") + "-Page-" + pad(current) + ".jpg");
    prevBtn.disabled = current === 1;
    nextBtn.disabled = current === activeDoc.pageCount;
    for (var i = 0; i < thumbEls.length; i++){
      thumbEls[i].classList.toggle("is-active", i + 1 === current);
    }
    var active = thumbEls[current - 1];
    if (active && active.scrollIntoView){
      active.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
  }

  function openViewer(doc, startPage){
    activeDoc = doc;
    if (docTitleEl) docTitleEl.textContent = doc.title;
    if (downloadFullLink) downloadFullLink.href = doc.pdfPath;
    buildThumbs(doc);
    goTo(startPage || 1);
    overlay.classList.add("is-open");
    overlay.setAttribute("aria-hidden", "false");
    document.body.style.overflow = "hidden";
  }

  function closeViewer(){
    overlay.classList.remove("is-open");
    overlay.setAttribute("aria-hidden", "true");
    document.body.style.overflow = "";
  }

  [document.getElementById("cpOpenBtn"), document.getElementById("cpOpenBtn2")].forEach(function(btn){
    if (!btn) return;
    btn.addEventListener("click", function(){ openViewer(MAIN_DOC, 1); });
    btn.addEventListener("keydown", function(e){
      if (e.key === "Enter" || e.key === " "){ e.preventDefault(); openViewer(MAIN_DOC, 1); }
    });
  });
  closeBtn.addEventListener("click", closeViewer);
  overlay.addEventListener("click", function(e){
    if (e.target === overlay) closeViewer();
  });
  prevBtn.addEventListener("click", function(){ goTo(current - 1); });
  nextBtn.addEventListener("click", function(){ goTo(current + 1); });

  document.addEventListener("keydown", function(e){
    if (!overlay.classList.contains("is-open")) return;
    if (e.key === "Escape") closeViewer();
    if (e.key === "ArrowLeft") goTo(current - 1);
    if (e.key === "ArrowRight") goTo(current + 1);
  });

  var touchStartX = null;
  overlay.addEventListener("touchstart", function(e){
    touchStartX = e.touches[0].clientX;
  }, { passive: true });
  overlay.addEventListener("touchend", function(e){
    if (touchStartX === null) return;
    var dx = e.changedTouches[0].clientX - touchStartX;
    if (Math.abs(dx) > 40) goTo(current + (dx < 0 ? 1 : -1));
    touchStartX = null;
  }, { passive: true });

  // ---- additional documents, added via the admin panel --------------------
  function renderAdditionalDocs(items){
    if (!grid || !items || !items.length) return;

    var renderedCount = 0;

    items.forEach(function(item){
      if (!item.pageCount || !item.imagesUrl) {
        // This is exactly why a saved entry can be invisible on the site
        // even though it's sitting in attachments.json: a malformed entry
        // (missing pageCount or imagesUrl) is skipped here silently. Log
        // it so it shows up in the browser console instead of just
        // vanishing.
        console.warn("Skipping attachment with missing pageCount/imagesUrl:", item);
        return;
      }

      var doc = {
        id: item.id,
        title: item.title || "Document",
        imgPath: item.imagesUrl,
        pdfPath: item.url,
        pageCount: item.pageCount
      };

      var cell = document.createElement("div");
      cell.className = "cp-item";

      var media = document.createElement("div");
      media.className = "cp-item-media";
      media.setAttribute("role", "button");
      media.setAttribute("tabindex", "0");
      media.setAttribute("aria-label", "Open " + doc.title);

      var thumb = document.createElement("img");
      thumb.src = pageSrc(doc, 1);
      thumb.loading = "lazy";
      thumb.alt = doc.title + " cover";
      media.appendChild(thumb);

      var dl = document.createElement("a");
      dl.className = "cp-dl-btn";
      dl.href = doc.pdfPath;
      dl.setAttribute("download", "");
      dl.setAttribute("aria-label", "Download " + doc.title + " PDF");
      dl.innerHTML = '<i class="budicon-cloud-download"></i>';
      dl.addEventListener("click", function(e){ e.stopPropagation(); });
      media.appendChild(dl);

      var openThis = function(){ openViewer(doc, 1); };
      media.addEventListener("click", openThis);
      media.addEventListener("keydown", function(e){
        if (e.key === "Enter" || e.key === " "){ e.preventDefault(); openThis(); }
      });
      cell.appendChild(media);

      var label = document.createElement("span");
      label.className = "cp-item-label";
      label.textContent = doc.title + " · " + doc.pageCount + (doc.pageCount === 1 ? " page" : " pages");
      cell.appendChild(label);

      grid.appendChild(cell);
      renderedCount++;
    });

    if (moreToolbar) moreToolbar.hidden = renderedCount === 0;
  }

  if (window.fetch){
    fetch(ATTACHMENTS_JSON_PATH, { cache: "no-store" })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){ if (Array.isArray(data)) renderAdditionalDocs(data); })
      .catch(function(){ /* no additional documents yet */ });
  }
})();

/* ============================================================
   AWARDS — populated from assets/data/awards.json, editable
   via the admin "Awards" page.
   ============================================================ */
(function(){
  "use strict";

  var AWARDS_JSON_PATH = "assets/data/awards.json";
  var section = document.getElementById("awards");
  var grid    = document.getElementById("awardsGrid");
  var empty   = document.getElementById("awardsEmpty");
  if (!grid) return;

  var lightbox = document.getElementById("awardLightbox");
  var lbImg    = document.getElementById("awardLbImg");
  var lbCap    = document.getElementById("awardLbCaption");
  var lbIndex  = 0;
  var items    = [];

  function openLightbox(i){
    lbIndex = i;
    var a = items[lbIndex];
    lbImg.src = a.imageUrl;
    lbImg.alt = a.title || "Award";
    if (lbCap) lbCap.textContent = a.title + (a.year ? " — " + a.year : "");
    lightbox.classList.add("is-open");
  }
  function closeLightbox(){ lightbox.classList.remove("is-open"); }
  function stepLightbox(dir){
    openLightbox((lbIndex + dir + items.length) % items.length);
  }

  if (lightbox){
    var closeBtn = document.getElementById("awardLbClose");
    var prevBtn  = document.getElementById("awardLbPrev");
    var nextBtn  = document.getElementById("awardLbNext");
    if (closeBtn) closeBtn.addEventListener("click", closeLightbox);
    if (prevBtn) prevBtn.addEventListener("click", function(){ stepLightbox(-1); });
    if (nextBtn) nextBtn.addEventListener("click", function(){ stepLightbox(1); });
    lightbox.addEventListener("click", function(e){ if (e.target === lightbox) closeLightbox(); });
    window.addEventListener("keydown", function(e){
      if (!lightbox.classList.contains("is-open")) return;
      if (e.key === "Escape") closeLightbox();
      if (e.key === "ArrowRight") stepLightbox(1);
      if (e.key === "ArrowLeft") stepLightbox(-1);
    });
  }

  function renderAwards(data){
    items = Array.isArray(data) ? data : [];
    grid.innerHTML = "";

    if (!items.length){
      if (empty) empty.hidden = false;
      if (section) section.hidden = true; // hide the whole section when there's nothing to show yet
      return;
    }
    if (empty) empty.hidden = true;
    if (section) section.hidden = false;

    items.forEach(function(a, i){
      var cell = document.createElement("div");
      cell.className = "cp-item";

      var media = document.createElement("div");
      media.className = "cp-item-media";
      media.setAttribute("role", "button");
      media.setAttribute("tabindex", "0");
      media.setAttribute("aria-label", "View " + (a.title || "award") + " full-size");

      var img = document.createElement("img");
      img.src = a.imageUrl;
      img.loading = "lazy";
      img.alt = a.title || "Award";
      media.appendChild(img);

      var openThis = function(){ openLightbox(i); };
      media.addEventListener("click", openThis);
      media.addEventListener("keydown", function(e){
        if (e.key === "Enter" || e.key === " "){ e.preventDefault(); openThis(); }
      });
      cell.appendChild(media);

      var label = document.createElement("span");
      label.className = "cp-item-label";
      label.textContent = a.title + (a.year ? " · " + a.year : "");
      cell.appendChild(label);

      grid.appendChild(cell);
    });
  }

  if (window.fetch){
    fetch(AWARDS_JSON_PATH, { cache: "no-store" })
      .then(function(r){ return r.ok ? r.json() : null; })
      .then(function(data){ renderAwards(data); })
      .catch(function(){ renderAwards([]); });
  } else {
    renderAwards([]);
  }
})();