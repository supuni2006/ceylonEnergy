(function () {
  "use strict";

  // Wires up drag-to-reorder page-image thumbnails for every upload form
  // on the page (the "replace profile" form and the "add new document"
  // form both use the same markup pattern).
  var forms = document.querySelectorAll(".js-page-upload-form");

  forms.forEach(function (form) {
    var input = form.querySelector(".js-page-images-input");
    var thumbsWrap = form.querySelector(".js-page-thumbs");
    var hint = form.querySelector(".js-page-order-hint");
    var submitBtn = form.querySelector(".js-submit-btn");
    if (!input || !thumbsWrap) return;

    var files = []; // ordered array of File objects
    var dragIndex = null;

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

    input.addEventListener("change", function () {
      files = Array.prototype.slice.call(input.files);
      render();
    });

    form.addEventListener("submit", function () {
      if (files.length === 0) return; // let native "required" handle it
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
})();