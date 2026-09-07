(function () {
  "use strict";

  // pdf.js is loaded from a CDN in index.php, right before this file. If it
  // failed to load for any reason (offline, blocked, CDN down), we fall
  // back to the original "choose your own JPGs" flow automatically.
  var HAS_PDFJS = typeof window.pdfjsLib !== "undefined";
  if (HAS_PDFJS) {
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  }

  var MAX_RENDER_EDGE = 2000; // cap the longest edge so JPGs stay well under the 10MB per-page limit
  var JPEG_QUALITY = 0.82;
  var MAX_PAGES = 200; // mirrors MAX_ATTACHMENT_PAGES in inc/config.php

  // Wires up every "add PDF (+ page images)" form on the page (the
  // "replace profile" form and the "add new document" form both use the
  // same markup pattern).
  var forms = document.querySelectorAll(".js-page-upload-form");

  forms.forEach(function (form) {
    var pdfInput = form.querySelector(".js-pdf-input") || form.querySelector('input[name="pdf"]');
    var input = form.querySelector(".js-page-images-input");
    var thumbsWrap = form.querySelector(".js-page-thumbs");
    var hint = form.querySelector(".js-page-order-hint");
    var submitBtn = form.querySelector(".js-submit-btn");
    var manualLabel = form.querySelector(".js-manual-images-label");
    var manualToggle = form.querySelector(".js-manual-toggle");
    var status = form.querySelector(".js-pdf-convert-status");
    if (!input || !thumbsWrap) return;

    var files = []; // ordered array of File objects (the page images)
    var dragIndex = null;
    var autoMode = HAS_PDFJS && !!pdfInput;
    var converting = false;

    function setStatus(text, isError) {
      if (!status) return;
      if (!text) {
        status.hidden = true;
        status.textContent = "";
        status.classList.remove("is-error");
        return;
      }
      status.hidden = false;
      status.textContent = text;
      status.classList.toggle("is-error", !!isError);
    }

    function showManualField(show) {
      if (manualLabel) manualLabel.hidden = !show;
    }

    // Drop back to the original manual-upload experience — used if pdf.js
    // isn't available at all, or if converting a particular PDF fails
    // (e.g. a scanned/encrypted/corrupt file the browser can't render).
    function fallBackToManual(message) {
      autoMode = false;
      converting = false;
      showManualField(true);
      if (manualToggle) manualToggle.hidden = true;
      input.required = true;
      if (submitBtn) submitBtn.disabled = false;
      setStatus(message || "", !!message);
    }

    // ---- thumbnail rendering + drag-to-reorder (unchanged behaviour) ----
    function render() {
      thumbsWrap.innerHTML = "";
      if (hint) hint.hidden = files.length < 2;

      files.forEach(function (file, i) {
        var url = URL.createObjectURL(file);
        var el = document.createElement("div");
        el.className = "page-thumb";
        el.draggable = true;
        el.dataset.index = i;

        var img = document.createElement("img");
        img.src = url;
        img.alt = "Page " + (i + 1);
        el.appendChild(img);

        var label = document.createElement("span");
        label.className = "page-thumb-num";
        label.textContent = i + 1;
        el.appendChild(label);

        var remove = document.createElement("button");
        remove.type = "button";
        remove.className = "page-thumb-remove";
        remove.setAttribute("aria-label", "Remove page " + (i + 1));
        remove.textContent = "\u2715";
        remove.addEventListener("click", function () {
          files.splice(i, 1);
          syncInput();
          render();
          if (autoMode) {
            setStatus(files.length
              ? files.length + " page image" + (files.length === 1 ? "" : "s") + " ready."
              : "No page images yet — choose a PDF above.");
          }
        });
        el.appendChild(remove);

        el.addEventListener("dragstart", function () {
          dragIndex = i;
          el.classList.add("is-dragging");
        });
        el.addEventListener("dragend", function () {
          el.classList.remove("is-dragging");
        });
        el.addEventListener("dragover", function (e) {
          e.preventDefault();
        });
        el.addEventListener("drop", function (e) {
          e.preventDefault();
          var targetIndex = parseInt(el.dataset.index, 10);
          if (dragIndex === null || dragIndex === targetIndex) return;
          var moved = files.splice(dragIndex, 1)[0];
          files.splice(targetIndex, 0, moved);
          dragIndex = null;
          syncInput();
          render();
        });

        thumbsWrap.appendChild(el);
      });
    }

    function syncInput() {
      var dt = new DataTransfer();
      files.forEach(function (f) { dt.items.add(f); });
      input.files = dt.files;
    }

    // ---- auto mode: render every PDF page to a JPG in the browser ----
    function convertPdfToImages(file) {
      converting = true;
      if (submitBtn) submitBtn.disabled = true;
      setStatus("Reading PDF\u2026");

      var reader = new FileReader();
      reader.onerror = function () {
        fallBackToManual("Could not read that PDF in this browser — please add page images manually.");
      };
      reader.onload = function () {
        var typedArray = new Uint8Array(reader.result);
        pdfjsLib.getDocument({ data: typedArray }).promise.then(function (pdf) {
          var pageCount = pdf.numPages;
          if (pageCount > MAX_PAGES) {
            fallBackToManual("That PDF has more than " + MAX_PAGES + " pages — please add page images manually.");
            return;
          }

          var newFiles = [];

          function renderPage(pageNum) {
            if (pageNum > pageCount) {
              files = newFiles;
              syncInput();
              render();
              converting = false;
              if (submitBtn) submitBtn.disabled = false;
              setStatus(pageCount + " page image" + (pageCount === 1 ? "" : "s") + " generated from the PDF.");
              return;
            }

            setStatus("Generating page " + pageNum + " of " + pageCount + "\u2026");

            pdf.getPage(pageNum).then(function (page) {
              var base = page.getViewport({ scale: 1 });
              var scale = MAX_RENDER_EDGE / Math.max(base.width, base.height);
              scale = Math.max(0.1, Math.min(scale, 2));
              var viewport = page.getViewport({ scale: scale });

              var canvas = document.createElement("canvas");
              canvas.width = Math.ceil(viewport.width);
              canvas.height = Math.ceil(viewport.height);
              var ctx = canvas.getContext("2d");

              page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function () {
                canvas.toBlob(function (blob) {
                  if (!blob) {
                    fallBackToManual("Couldn't convert page " + pageNum + " — please add page images manually.");
                    return;
                  }
                  var pageName = "page-" + String(pageNum).padStart(2, "0") + ".jpg";
                  newFiles.push(new File([blob], pageName, { type: "image/jpeg" }));
                  renderPage(pageNum + 1);
                }, "image/jpeg", JPEG_QUALITY);
              }, function () {
                fallBackToManual("Couldn't render page " + pageNum + " — please add page images manually.");
              });
            }, function () {
              fallBackToManual("Couldn't open page " + pageNum + " — please add page images manually.");
            });
          }

          renderPage(1);
        }, function () {
          fallBackToManual("Could not process that PDF in this browser — please add page images manually.");
        });
      };
      reader.readAsArrayBuffer(file);
    }

    if (autoMode) {
      showManualField(false);
      input.required = false; // auto-populated once conversion finishes; syncInput() sets input.files
      setStatus("Choose a PDF above — page images will be generated automatically.");

      if (manualToggle) {
        manualToggle.hidden = false;
        manualToggle.addEventListener("click", function () {
          fallBackToManual();
        });
      }

      pdfInput.addEventListener("change", function () {
        var file = pdfInput.files && pdfInput.files[0];
        if (file) convertPdfToImages(file);
      });
    }

    input.addEventListener("change", function () {
      files = Array.prototype.slice.call(input.files);
      render();
    });

    form.addEventListener("submit", function (e) {
      if (autoMode) {
        if (converting) {
          e.preventDefault();
          setStatus("Still generating page images — please wait a moment and try again.", true);
          return;
        }
        if (files.length === 0) {
          e.preventDefault();
          setStatus("Please choose a PDF first.", true);
          return;
        }
      } else if (files.length === 0) {
        return; // let native "required" handle it
      }

      if (submitBtn) {
        // Deferred via setTimeout: disabling the button *synchronously*
        // inside the submit handler can, in some browsers, race with the
        // browser's own submission and silently cancel it — the page just
        // sits there with no navigation and no error. Pushing this to the
        // next tick lets the actual form submission start first.
        setTimeout(function () {
          submitBtn.disabled = true;
          submitBtn.textContent = "Uploading\u2026";
        }, 0);
      }
    });
  });

  // ---- gallery: "upload photos" form — project dropdown follows location ----
  (function () {
    var locationSelect = document.querySelector(".js-photo-location-select");
    var projectSelect  = document.querySelector(".js-photo-project-select");
    if (!locationSelect || !projectSelect) return;

    var GALLERY_DATA = window.CE_GALLERY_DATA || [];

    function populateProjects() {
      var loc = GALLERY_DATA.filter(function (l) { return l.id === locationSelect.value; })[0];
      projectSelect.innerHTML = "";

      var placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.disabled = true;
      placeholder.selected = true;

      if (!loc || !loc.projects || !loc.projects.length) {
        placeholder.textContent = loc ? "No projects yet \u2014 add one above first" : "Choose a location first\u2026";
        projectSelect.appendChild(placeholder);
        return;
      }

      placeholder.textContent = "Choose a project\u2026";
      projectSelect.appendChild(placeholder);

      loc.projects.forEach(function (p) {
        var opt = document.createElement("option");
        opt.value = p.id;
        opt.textContent = p.name;
        projectSelect.appendChild(opt);
      });
    }

    locationSelect.addEventListener("change", populateProjects);
    populateProjects();
  })();
})();