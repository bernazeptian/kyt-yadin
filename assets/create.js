/* =============================================
   CREATE FORM — create.js
   ============================================= */

document.addEventListener("DOMContentLoaded", () => {

  /* ─────────────────────────────────────────
     RISK MATRIX — Yanmar Risk Assessment
  ───────────────────────────────────────── */
  const MATRIX = {
    A: { 1: "E", 2: "E", 3: "E", 4: "E", 5: "E" },
    B: { 1: "M", 2: "H", 3: "H", 4: "E", 5: "E" },
    C: { 1: "L", 2: "M", 3: "H", 4: "E", 5: "E" },
    D: { 1: "L", 2: "L", 3: "M", 4: "H", 5: "E" },
    E: { 1: "L", 2: "L", 3: "M", 4: "H", 5: "H" },
  };

  const RISK_CONFIG = {
    L: { label: "Low",     class: "risk-low",     badgeClass: "risk-badge--low",     desc: "Acceptable risk. Monitor and review." },
    M: { label: "Medium",  class: "risk-medium",  badgeClass: "risk-badge--medium",  desc: "Moderate risk. Action required within 30 days." },
    H: { label: "High",    class: "risk-high",    badgeClass: "risk-badge--high",    desc: "High risk. Immediate corrective action required." },
    E: { label: "Extreme", class: "risk-extreme", badgeClass: "risk-badge--extreme", desc: "Extreme risk. Stop work immediately!" },
  };

  const riskResult      = document.getElementById("riskResult");
  const riskBadge       = document.getElementById("riskBadge");
  const riskPlaceholder = document.getElementById("riskPlaceholder");
  const riskLevelInput  = document.getElementById("riskLevelInput");
  const likelihoodInput = document.getElementById("likelihoodInput");
  const severityInput   = document.getElementById("severityInput");
  const matrixCells     = document.querySelectorAll(".matrix-cell");

  let selectedLikelihood = null;
  let selectedSeverity   = null;

  /* ─────────────────────────────────────────
     CALCULATE & DISPLAY RISK
  ───────────────────────────────────────── */
  function calculateRisk() {
    if (!selectedLikelihood || !selectedSeverity) return;

    const riskCode = MATRIX[selectedLikelihood][selectedSeverity];
    const config   = RISK_CONFIG[riskCode];

    // Update hidden inputs for form submission
    riskLevelInput.value  = config.label.toLowerCase();
    likelihoodInput.value = selectedLikelihood;
    severityInput.value   = selectedSeverity;

    // Update result box
    riskResult.className = "risk-result " + config.class;
    riskPlaceholder.classList.add("hidden");
    riskBadge.classList.remove("hidden");
    riskBadge.className = "risk-result__badge " + config.badgeClass;
    riskBadge.innerHTML = `
      <div style="font-size:20px;font-weight:700;margin-bottom:4px">${config.label}</div>
      <div style="font-size:12px;font-weight:400;opacity:0.85">${config.desc}</div>
    `;

    // Highlight active cell in reference matrix
    matrixCells.forEach(c => c.classList.remove("matrix-cell--active"));
    const active = document.querySelector(
      `.matrix-cell[data-l="${selectedLikelihood}"][data-s="${selectedSeverity}"]`
    );
    if (active) active.classList.add("matrix-cell--active");
  }

  /* ─────────────────────────────────────────
     LISTEN: Likelihood radio buttons
  ───────────────────────────────────────── */
  document.querySelectorAll('input[name="likelihood"]').forEach(input => {
    input.addEventListener("change", () => {
      selectedLikelihood = input.value;
      calculateRisk();
    });
  });

  /* ─────────────────────────────────────────
     LISTEN: Severity radio buttons
  ───────────────────────────────────────── */
  document.querySelectorAll('input[name="severity"]').forEach(input => {
    input.addEventListener("change", () => {
      selectedSeverity = parseInt(input.value);
      calculateRisk();
    });
  });

  /* ─────────────────────────────────────────
     CLICK MATRIX CELL to quick-select
  ───────────────────────────────────────── */
  matrixCells.forEach(cell => {
    cell.style.cursor = "pointer";
    cell.addEventListener("click", () => {
      const l = cell.dataset.l;
      const s = parseInt(cell.dataset.s);

      const lRadio = document.querySelector(`input[name="likelihood"][value="${l}"]`);
      const sRadio = document.querySelector(`input[name="severity"][value="${s}"]`);

      if (lRadio) { lRadio.checked = true; selectedLikelihood = l; }
      if (sRadio) { sRadio.checked = true; selectedSeverity   = s; }

      calculateRisk();
    });
  });

  /* ─────────────────────────────────────────
     PHOTO UPLOAD & PREVIEW
  ───────────────────────────────────────── */
  const uploadArea   = document.getElementById("uploadArea");
  const photoInput   = document.getElementById("photos");
  const photoPreview = document.getElementById("photoPreview");
  const MAX_PHOTOS   = 5;
  let uploadedFiles  = [];

  function renderPreviews() {
    photoPreview.innerHTML = "";
    uploadedFiles.forEach((file, index) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const thumb = document.createElement("div");
        thumb.className = "photo-thumb";
        thumb.innerHTML = `
          <img src="${e.target.result}" alt="preview"/>
          <button type="button" class="photo-thumb__remove" data-index="${index}" title="Remove">✕</button>
        `;
        photoPreview.appendChild(thumb);
        thumb.querySelector(".photo-thumb__remove").addEventListener("click", (ev) => {
          uploadedFiles.splice(parseInt(ev.target.dataset.index), 1);
          renderPreviews();
        });
      };
      reader.readAsDataURL(file);
    });
  }

  photoInput.addEventListener("change", () => {
    const newFiles = Array.from(photoInput.files);
    uploadedFiles  = [...uploadedFiles, ...newFiles].slice(0, MAX_PHOTOS);
    renderPreviews();
  });

  uploadArea.addEventListener("dragover",  (e) => { e.preventDefault(); uploadArea.classList.add("dragover"); });
  uploadArea.addEventListener("dragleave", ()  => uploadArea.classList.remove("dragover"));
  uploadArea.addEventListener("drop", (e) => {
    e.preventDefault();
    uploadArea.classList.remove("dragover");
    const dropped = Array.from(e.dataTransfer.files).filter(f => f.type.startsWith("image/"));
    uploadedFiles = [...uploadedFiles, ...dropped].slice(0, MAX_PHOTOS);
    renderPreviews();
  });

  /* ─────────────────────────────────────────
     FORM VALIDATION before submit
  ───────────────────────────────────────── */
  document.getElementById("reportForm").addEventListener("submit", (e) => {
    if (!riskLevelInput.value) {
      e.preventDefault();
      riskResult.scrollIntoView({ behavior: "smooth", block: "center" });
      riskResult.style.outline = "2px solid var(--red)";
      setTimeout(() => riskResult.style.outline = "", 2000);
      alert("Please select both Likelihood and Severity to calculate the risk level.");
    }
  });

});