/**
 * gustaine-jobs-widget.js
 * Upload to: www.accelonconsulting.com/jobportal/gustaine-jobs-widget.js
 *
 * ── EMBED ON ANY WEBSITE ────────────────────────────────────────────────────
 *   <div id="gustaine-jobs"></div>
 *   <script src="https://accelonconsulting.com/jobportal/gustaine-jobs-widget.js"></script>
 */

(function () {
  'use strict';

  const cfg          = window.GustaineJobsConfig || {};
  const CONTAINER_ID = cfg.containerId || 'gustaine-jobs';
  const API_URL      = 'https://accelonconsulting.com/jobportal/jobs-embed.php';

  const FLAG_MAP = {
    'india':         'https://www.accelonconsulting.com/jobportal/flags/india.jpg',
    'united states': 'https://www.accelonconsulting.com/jobportal/flags/us.jpg',
    'usa':           'https://www.accelonconsulting.com/jobportal/flags/us.jpg',
    'canada':        'https://www.accelonconsulting.com/jobportal/flags/canada.jpg',
  };

  /* ── CSS ─────────────────────────────────────────────────────────────────── */
  const CSS = `
  @import url('https://fonts.googleapis.com/css2?family=Sora:wght@400;500;600;700&display=swap');
  .gjw-root*,.gjw-root*::before,.gjw-root*::after{box-sizing:border-box;margin:0;padding:0}
  .gjw-root{
    font-family:'Sora','Helvetica Neue',Arial,sans-serif;
    background:#eef0f5;border-radius:14px;padding:32px 24px 40px;color:#000;
    --accent:#1A4C8F;--border:#dde1ea;--surface:#fff;--muted:#5b6a87;--hover:#f4f6fb;
  }
  .gjw-title{font-size:clamp(26px,4vw,38px);font-weight:700;letter-spacing:-.02em;margin-bottom:26px;color:#000}
  .gjw-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px;justify-content:center}
  .gjw-fw{position:relative;display:flex;align-items:center}
  .gjw-fw svg{position:absolute;left:12px;width:15px;height:15px;color:#555;pointer-events:none;flex-shrink:0}
  .gjw-input,.gjw-select{
    font-family:lato;font-size:14px;color:#000;background:var(--surface);
    border:1px solid var(--border);border-radius:8px;padding:4px 12px 10px 36px;
    outline:none;transition:border-color .2s,box-shadow .2s;-webkit-appearance:none;appearance:none;
  }
  .gjw-input{min-width:230px}
  .gjw-select{
    padding-bottom: 4px;min-width:155px;padding-right:28px;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%235b6a87' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 9px center;
  }
  .gjw-input:focus,.gjw-select:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(26,76,143,.12)}
  .gjw-input::placeholder{color:#888}
  .gjw-btn{
    font-family:inherit;font-size:13px;font-weight:600;padding:10px 18px;
    border-radius:8px;border:1px solid var(--border);background:var(--surface);
    cursor:pointer;color:#000;transition:background .2s;
  }
  .gjw-btn:hover{background:var(--hover)}
  .gjw-info{font-size:13px;color:var(--muted);margin-bottom:18px;text-align:center}
  .gjw-card{background:var(--surface);border-radius:12px;border:1px solid var(--border);overflow:hidden;overflow-x:auto}
  .gjw-table{width:100%;border-collapse:collapse;min-width:560px}
  .gjw-table thead th{
     font-size: 17px;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: #fff;
  padding: 16px 24px;
  text-align: left;
  border-bottom: 1px solid var(--border);
  background: #1a4c8f;  }
  .gjw-table tbody tr{border-bottom:1px solid var(--border);transition:background .15s;cursor:pointer}
  .gjw-table tbody tr:last-child{border-bottom:none}
  .gjw-table tbody tr:hover{background:var(--hover)}
  .gjw-table td{padding:15px 18px;font-size:16px;vertical-align:middle}
  .gjw-link{font-size:14px;font-weight:600;color:var(--accent);text-decoration:none;transition:color .15s}
  .gjw-link:hover{color:#000;text-decoration:underline}
  .gjw-badge{display:inline-block;font-size:16px;font-weight:500;padding:3px 9px;border-radius:20px;background:#eef0f9;color:#222}
  .gjw-badge.remote{color:#1a5fa8;background:#e6f0fb}
  .gjw-badge.hybrid{color:#7a4f00;background:#fdf3dc}
  .gjw-badge.onsite{color:#1a8a5a;background:#e6f7f1}
  .gjw-loc{display:flex;align-items:center;gap:6px;color:#333}
  .gjw-flag{width:22px;height:15px;object-fit:cover;border-radius:2px;flex-shrink:0}
  .gjw-empty{text-align:center;padding:36px 20px;color:var(--muted);font-size:14px}
  .gjw-spinner{display:flex;align-items:center;justify-content:center;gap:10px;padding:36px 20px;color:var(--muted);font-size:13px}
  .gjw-spin{width:18px;height:18px;border:2px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:gjwSpin .7s linear infinite}
  @keyframes gjwSpin{to{transform:rotate(360deg)}}
  .gjw-sugg{
    display:none;position:absolute;top:calc(100% + 5px);left:0;
    min-width:100%;width:max-content;max-width:320px;
    background:var(--surface);border:1px solid var(--border);
    border-radius:10px;box-shadow:0 8px 24px rgba(10,37,64,.12);z-index:99999;overflow:hidden;
  }
  .gjw-sugg.open{display:block}
  .gjw-si{display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:13px;cursor:pointer;transition:background .15s;color:#000}
  .gjw-si:hover,.gjw-si.active{background:var(--hover);color:var(--accent)}
  .gjw-si mark{background:none;color:var(--accent);font-weight:600}
  @media(max-width:640px){.gjw-input{min-width:100%}.gjw-select{min-width:calc(50% - 5px)}.gjw-filters{flex-direction:column}}
  `;

  /* ── HELPERS ─────────────────────────────────────────────────────────────── */
  function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
  function fmtDate(d){if(!d)return'';return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'long',year:'numeric'})}
  function escRe(s){return s.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')}
  function flagHtml(country){
    const src=FLAG_MAP[(country||'').trim().toLowerCase()];
    return src?`<img class="gjw-flag" src="${src}" alt="${esc(country)}">`:`<span style="font-size:16px">🌐</span>`;
  }

  /* ── INIT ────────────────────────────────────────────────────────────────── */
  function init() {
    const root = document.getElementById(CONTAINER_ID);
    if (!root) { console.warn('[GustaineJobs] #' + CONTAINER_ID + ' not found'); return; }

    if (!document.getElementById('gjw-css')) {
      const s = document.createElement('style');
      s.id = 'gjw-css'; s.textContent = CSS;
      document.head.appendChild(s);
    }

    root.innerHTML = `
      <div class="gjw-root">
        <h2 class="gjw-title">Job Openings</h2>
        <div class="gjw-filters">
          <div class="gjw-fw" style="position:relative" id="gjw-swrap">
            <svg viewBox="0 0 20 20" fill="none"><circle cx="8.5" cy="8.5" r="5.5" stroke="currentColor" stroke-width="1.6"/><path d="M13 13l3.5 3.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input class="gjw-input" id="gjw-q" type="text" placeholder="Search for a job" autocomplete="off" style="padding-left: 28px;">
            <div class="gjw-sugg" id="gjw-sugg"></div>
          </div>
          <div class="gjw-fw">
            <svg viewBox="0 0 20 20" fill="none"><circle cx="10" cy="10" r="8" stroke="currentColor" stroke-width="1.6"/><path d="M2 10h16M10 2c-2 2-3 5-3 8s1 6 3 8M10 2c2 2 3 5 3 8s-1 6-3 8" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
            <select class="gjw-select" id="gjw-country">
              <option value="">Country</option>
              <option value="India">India</option>
              <option value="United States">United States</option>
              <option value="Canada">Canada</option>
            </select>
          </div>
          <div class="gjw-fw">
            <svg viewBox="0 0 20 20" fill="none"><rect x="3" y="5" width="14" height="11" rx="2" stroke="currentColor" stroke-width="1.6"/><path d="M7 5V4a1 1 0 011-1h4a1 1 0 011 1v1" stroke="currentColor" stroke-width="1.6"/></svg>
            <select class="gjw-select" id="gjw-type"><option value="">Job Type</option></select>
          </div>
          <div class="gjw-fw">
            <svg viewBox="0 0 20 20" fill="none"><path d="M10 2C6.686 2 4 4.686 4 8c0 4.418 6 10 6 10s6-5.582 6-10c0-3.314-2.686-6-6-6z" stroke="currentColor" stroke-width="1.6"/><circle cx="10" cy="8" r="2" stroke="currentColor" stroke-width="1.5"/></svg>
            <select class="gjw-select" id="gjw-wp"><option value="">Workplace</option></select>
          </div>
          <button class="gjw-btn" id="gjw-reset">Reset</button>
        </div>
        <p class="gjw-info" id="gjw-info">Loading jobs…</p>
        <div class="gjw-card">
          <table class="gjw-table">
            <thead>
              <tr>
                <th>Job Code</th><th>Role</th><th>Job Type</th>
                <th>Workplace</th><th>Location</th><th>Post Date</th>
              </tr>
            </thead>
            <tbody id="gjw-body">
              <tr><td colspan="6"><div class="gjw-spinner"><div class="gjw-spin"></div> Loading jobs…</div></td></tr>
            </tbody>
          </table>
        </div>
      </div>`;

    const qEl       = root.querySelector('#gjw-q');
    const suggEl    = root.querySelector('#gjw-sugg');
    const countryEl = root.querySelector('#gjw-country');
    const typeEl    = root.querySelector('#gjw-type');
    const wpEl      = root.querySelector('#gjw-wp');
    const resetBtn  = root.querySelector('#gjw-reset');
    const infoEl    = root.querySelector('#gjw-info');
    const tbody     = root.querySelector('#gjw-body');

    let allJobs = [], allTitles = [], sugIdx = -1;

    /* ── FETCH ── */
    fetch(API_URL)
      .then(r => r.json())
      .then(data => {
        if (!data.success) throw new Error('API error');

        allJobs   = data.jobs;
        allTitles = [...new Set(allJobs.map(j => j.job_title))];

        // Populate Job Type from real data
        [...new Set(allJobs.map(j => j.job_type).filter(Boolean))].forEach(t => {
          typeEl.innerHTML += `<option value="${esc(t)}">${esc(t)}</option>`;
        });
        // Populate Workplace from real data
        [...new Set(allJobs.map(j => j.workplace_type).filter(Boolean))].forEach(w => {
          wpEl.innerHTML += `<option value="${esc(w)}">${esc(w)}</option>`;
        });

        render(allJobs);
        updateInfo(allJobs.length);
      })
      .catch(() => {
        tbody.innerHTML = `<tr><td colspan="6" class="gjw-empty">
          Unable to load jobs. Please visit
          <a href="https://accelonconsulting.com/jobportal/"  style="color:#1A4C8F;font-weight:600">www.accelonconsulting.com/jobportal</a> directly.
        </td></tr>`;
        infoEl.textContent = '';
      });

    /* ── RENDER ── */
    function render(jobs) {
      if (!jobs.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="gjw-empty">No roles match your filters.</td></tr>';
        return;
      }
      tbody.innerHTML = jobs.map(j => {
        const wp  = (j.workplace_type || '').toLowerCase();
        const wpc = ['remote','hybrid','onsite'].includes(wp) ? wp : '';
        const loc = [j.city, j.state_province, wp === 'remote' ? j.country : ''].filter(Boolean).join(', ');

        return `<tr onclick="window.open('${esc(j.detail_url)}','_self')">
          <td>${esc(j.job_code)}</td>
          <td><a class="gjw-link" href="${esc(j.detail_url)}"  rel="noopener" onclick="event.stopPropagation()">${esc(j.job_title)}</a></td>
          <td>${esc(j.job_type)}</td>
          <td><span class="gjw-badge ${wpc}">${esc(j.workplace_type)}</span></td>
          <td><div class="gjw-loc">${flagHtml(j.country||'')} <span>${esc(loc)}</span></div></td>
          <td>${fmtDate(j.open_date)}</td>
        </tr>`;
      }).join('');
    }

    function updateInfo(count) {
      const hasFilter = countryEl.value || typeEl.value || wpEl.value || qEl.value.trim();
      infoEl.textContent = hasFilter
        ? `Showing ${count} role${count!==1?'s':''} matching your filters.`
        : 'Showing roles across all locations and all teams.';
    }

    /* ── FILTER ── */
    function applyFilters() {
      const s  = qEl.value.toLowerCase();
      const c  = countryEl.value;           // exact match, e.g. "India"
      const jt = typeEl.value;
      const wp = wpEl.value;

      const filtered = allJobs.filter(j =>
           (!s  || (j.job_title||'').toLowerCase().includes(s) || (j.job_code||'').includes(s))
        && (!c  || j.country === c)
        && (!jt || j.job_type === jt)
        && (!wp || j.workplace_type === wp)
      );
      render(filtered);
      updateInfo(filtered.length);
    }

    /* ── AUTOCOMPLETE ── */
   function showSugg() {
  const q = qEl.value.trim().toLowerCase();
  if (!q) { suggEl.classList.remove('open'); return; }
  const hits = allTitles.filter(t => t.toLowerCase().includes(q)).slice(0, 7);
  if (!hits.length) { suggEl.classList.remove('open'); return; }
  suggEl.innerHTML = hits.map((t,i) => {
    const hi = t.replace(new RegExp(`(${escRe(q)})`,'gi'),'$1');
    return `<div class="gjw-si" data-t="${esc(t)}">${hi}</div>`;
  }).join('');
      suggEl.classList.add('open'); sugIdx = -1;
      suggEl.querySelectorAll('.gjw-si').forEach(el => {
        el.addEventListener('mousedown', e => {
          e.preventDefault(); qEl.value = el.dataset.t;
          suggEl.classList.remove('open'); applyFilters();
        });
      });
    }

    function moveSug(dir) {
      const items = suggEl.querySelectorAll('.gjw-si'); if (!items.length) return;
      sugIdx = Math.max(-1, Math.min(sugIdx + dir, items.length - 1));
      items.forEach((el,i) => el.classList.toggle('active', i === sugIdx));
    }

    /* ── EVENTS ── */
    qEl.addEventListener('input',   () => { applyFilters(); showSugg(); });
    qEl.addEventListener('focus',   showSugg);
    qEl.addEventListener('keydown', e => {
      if (!suggEl.classList.contains('open')) return;
      if      (e.key === 'ArrowDown')  { e.preventDefault(); moveSug(1); }
      else if (e.key === 'ArrowUp')    { e.preventDefault(); moveSug(-1); }
      else if (e.key === 'Enter') {
        const a = suggEl.querySelector('.gjw-si.active');
        if (a) { qEl.value = a.dataset.t; suggEl.classList.remove('open'); applyFilters(); }
        else suggEl.classList.remove('open');
      }
      else if (e.key === 'Escape') suggEl.classList.remove('open');
    });
    countryEl.addEventListener('change', applyFilters);
    typeEl.addEventListener('change',    applyFilters);
    wpEl.addEventListener('change',      applyFilters);
    resetBtn.addEventListener('click', () => {
      qEl.value = ''; countryEl.value = ''; typeEl.value = ''; wpEl.value = '';
      suggEl.classList.remove('open'); applyFilters();
    });
    document.addEventListener('click', e => {
      if (!e.target.closest('#gjw-swrap')) suggEl.classList.remove('open');
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();

})();