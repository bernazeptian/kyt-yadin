/* =============================================
   DASHBOARD — dashboard.js
   ============================================= */

document.addEventListener("DOMContentLoaded", () => {

  /* ─────────────────────────────────────────
     TAB SWITCHING
  ───────────────────────────────────────── */
  const tabs   = document.querySelectorAll(".tab");
  const panels = document.querySelectorAll(".tab-panel");

  tabs.forEach(tab => {
    tab.addEventListener("click", () => {
      const target = tab.dataset.tab;

      tabs.forEach(t => t.classList.remove("tab--active"));
      tab.classList.add("tab--active");

      panels.forEach(p => p.classList.remove("tab-panel--active"));
      document.getElementById("tab-" + target).classList.add("tab-panel--active");

      animateCounters(document.getElementById("tab-" + target));
    });
  });

  /* ─────────────────────────────────────────
     ANIMATED COUNTERS
  ───────────────────────────────────────── */
  function animateCounters(container) {
    container.querySelectorAll(".card__value[data-count]").forEach(el => {
      if (el.dataset.animated) return;
      el.dataset.animated = "true";

      const target   = parseInt(el.dataset.count, 10);
      const duration = 1000;
      const start    = performance.now();

      function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const ease     = 1 - Math.pow(1 - progress, 3);
        el.textContent = Math.floor(ease * target);
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = target;
      }

      requestAnimationFrame(tick);
    });
  }

  animateCounters(document.getElementById("tab-hiyari"));

  /* ─────────────────────────────────────────
     CHART DEFAULTS
  ───────────────────────────────────────── */
  const fontDefaults = {
    family: "'Google Sans', sans-serif",
    size: 11,
  };

  // Use data from PHP via global variables
  // These are set in index.php before loading this script
  const hiyariData = window.HIYARI_DATA || {
    near_miss: 0,
    unsafe_act: 0,
    unsafe_condition: 0,
    dept_labels: [],
    dept_values: [],
  };

  const kytData = window.KYT_DATA || {
    completed: 0,
    pending: 0,
    dept_labels: [],
    dept_values: [],
  };

  /* ─────────────────────────────────────────
     HIYARI HATTO — Category Doughnut
  ───────────────────────────────────────── */
  const categoryCtx = document.getElementById("categoryChart");
  if (categoryCtx) {
    // Filter out zero values
    const catAll = [
      { label: "Near Miss",        value: hiyariData.near_miss,        bg: "#fce8e6", border: "#d93025" },
      { label: "Unsafe Act",       value: hiyariData.unsafe_act,       bg: "#fef3e2", border: "#e37400" },
      { label: "Unsafe Condition", value: hiyariData.unsafe_condition, bg: "#e8f0fe", border: "#1a73e8" },
    ].filter(c => c.value > 0);

    const catLabels = catAll.length ? catAll.map(c => c.label) : ["No Data"];
    const catValues = catAll.length ? catAll.map(c => c.value) : [1];
    const catBg     = catAll.length ? catAll.map(c => c.bg)    : ["#f1f3f4"];
    const catBorder = catAll.length ? catAll.map(c => c.border) : ["#e0e0e0"];

    new Chart(categoryCtx, {
      type: "doughnut",
      data: {
        labels: catLabels,
        datasets: [{
          data: catValues,
          backgroundColor: catBg,
          borderColor:     catBorder,
          borderWidth: 2,
          hoverOffset: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: "68%",
        plugins: {
          legend: {
            position: "bottom",
            labels: { font: fontDefaults, color: "#5f6368", padding: 16, usePointStyle: true, pointStyleWidth: 8 }
          },
          tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} reports` } }
        }
      }
    });
  }

  /* ─────────────────────────────────────────
     HIYARI HATTO — Department Bar Chart
  ───────────────────────────────────────── */
  const hiyariDeptCtx = document.getElementById("hiyariDeptChart");
  if (hiyariDeptCtx) {
    new Chart(hiyariDeptCtx, {
      type: "bar",
      data: {
        labels: hiyariData.dept_labels,
        datasets: [{
          label: "Reports",
          data: hiyariData.dept_values,
          backgroundColor: "#e8f0fe",
          borderColor: "#1a73e8",
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: "#1a73e8",
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ` ${ctx.raw} reports` } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: fontDefaults, color: "#5f6368" } },
          y: { grid: { color: "#f1f3f4" }, ticks: { font: fontDefaults, color: "#5f6368", stepSize: 1 }, beginAtZero: true }
        }
      }
    });
  }

  /* ─────────────────────────────────────────
     KYT — Completion Rate Doughnut
  ───────────────────────────────────────── */
  const kytCompletionCtx = document.getElementById("kytCompletionChart");
  if (kytCompletionCtx) {
    new Chart(kytCompletionCtx, {
      type: "doughnut",
      data: {
        labels: ["Completed", "Pending"],
        datasets: [{
          data: [kytData.completed, kytData.pending],
          backgroundColor: ["#e6f4ea", "#fef8e1"],
          borderColor:     ["#1e8e3e", "#f9ab00"],
          borderWidth: 2,
          hoverOffset: 6,
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: "68%",
        plugins: {
          legend: {
            position: "bottom",
            labels: { font: fontDefaults, color: "#5f6368", padding: 16, usePointStyle: true, pointStyleWidth: 8 }
          },
          tooltip: { callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw} activities` } }
        }
      }
    });
  }

  /* ─────────────────────────────────────────
     KYT — Department Bar Chart
  ───────────────────────────────────────── */
  const kytDeptCtx = document.getElementById("kytDeptChart");
  if (kytDeptCtx) {
    new Chart(kytDeptCtx, {
      type: "bar",
      data: {
        labels: kytData.dept_labels,
        datasets: [{
          label: "Activities",
          data: kytData.dept_values,
          backgroundColor: "#e6f4ea",
          borderColor: "#1e8e3e",
          borderWidth: 2,
          borderRadius: 6,
          borderSkipped: false,
          hoverBackgroundColor: "#1e8e3e",
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => ` ${ctx.raw} activities` } }
        },
        scales: {
          x: { grid: { display: false }, ticks: { font: fontDefaults, color: "#5f6368" } },
          y: { grid: { color: "#f1f3f4" }, ticks: { font: fontDefaults, color: "#5f6368", stepSize: 1 }, beginAtZero: true }
        }
      }
    });
  }

});