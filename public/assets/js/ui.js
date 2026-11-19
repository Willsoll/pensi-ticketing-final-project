(function () {
  'use strict';

  var doc = document;
  var win = window;
  var html = doc.documentElement;
  var body = doc.body;
  var CSS_ID = 'pensi-ui-styles';
  var RM_QUERY = '(prefers-reduced-motion: reduce)';
  var reduceMotion = win.matchMedia(RM_QUERY);

  /* ---------- CSS injection (uses app CSS variables) ---------- */
  function injectCSS() {
    if (doc.getElementById(CSS_ID)) return;
    var style = doc.createElement('style');
    style.id = CSS_ID;
    style.textContent =
      '.pensi-loader{position:fixed;inset:0;z-index:99999;display:flex;flex-direction:column;align-items:center;justify-content:center;background:var(--color-bg,#1E1B4B);color:var(--color-text-primary,#fff);font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;transition:opacity .4s ease,visibility .4s ease;opacity:1;visibility:visible;}' +
      '.pensi-loader .spinner{width:40px;height:40px;border:3px solid rgba(255,255,255,.18);border-top-color:var(--color-cta,#F97316);border-radius:50%;animation:pensiSpin .8s linear infinite;}' +
      '.pensi-loader h1{margin:14px 0 0;font-size:1.25rem;letter-spacing:.14em;text-transform:uppercase;opacity:.9;}' +
      '.pensi-loader.fade-out{opacity:0;visibility:hidden;pointer-events:none;}' +
      '.reveal{opacity:0;transform:translateY(24px);transition:opacity .7s ease,transform .7s ease;}' +
      '.reveal.is-visible{opacity:1;transform:none;}' +
      '#pensi-theme-toggle{position:fixed;right:20px;bottom:20px;z-index:1000;width:48px;height:48px;border-radius:50%;border:1px solid var(--color-accent,#6D28D9);background:var(--color-bg,#1E1B4B);color:var(--color-text-primary,#fff);cursor:pointer;box-shadow:0 4px 14px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;font-size:1rem;transition:transform .2s ease,box-shadow .2s ease;}' +
      '#pensi-theme-toggle:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.4);}' +
      '#pensi-theme-panel{position:fixed;right:20px;bottom:82px;width:240px;max-height:440px;overflow-y:auto;border:1px solid rgba(255,255,255,.14);border-radius:var(--radius-md,8px);background:var(--color-bg,#1E1B4B);color:var(--color-text-primary,#fff);z-index:1001;padding:12px;box-shadow:0 8px 28px rgba(0,0,0,.35);display:none;}' +
      '#pensi-theme-panel.open{display:block;}' +
      '#pensi-theme-panel h3{margin:0 0 10px;font-size:.8rem;text-transform:uppercase;letter-spacing:.08em;opacity:.8;}' +
      '#pensi-theme-panel .theme-item{display:flex;align-items:center;gap:10px;padding:8px 6px;border-radius:6px;cursor:pointer;font-size:.84rem;line-height:1.35;}' +
      '#pensi-theme-panel .theme-item:hover{background:rgba(255,255,255,.07);}' +
      '#pensi-theme-panel .theme-item.active{background:rgba(109,40,217,.16);border:1px solid var(--color-accent,#6D28D9);}' +
      '#pensi-theme-panel .theme-item .theme-name{flex:1;}' +
      '#pensi-theme-panel .theme-tag{font-size:.68rem;padding:2px 7px;border-radius:3px;border:1px solid currentColor;opacity:.8;}' +
      '@media (prefers-reduced-motion: reduce) {' +
      '.pensi-loader .spinner{animation:none;}' +
      '.reveal{transition:none;opacity:1;transform:none;}' +
      '[data-tilt]{transition:none !important;transform:none !important;}' +
      '}';
    doc.head.appendChild(style);
  }

  /* ---------- helpers ---------- */
  function setCookie(name, value, maxAge, path) {
    var d = new Date();
    d.setTime(d.getTime() + maxAge * 1000);
    doc.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=' + (path || '/') + '; SameSite=Lax';
  }
  function rAF(fn) { return win.requestAnimationFrame(fn); }

  /* ---------- 1. Loading screen ---------- */
  function initLoader() {
    var loader = doc.createElement('div');
    loader.id = 'pensi-loader';
    loader.className = 'pensi-loader';
    loader.innerHTML = '<div class="spinner"></div><h1>PENSI SCHOOL</h1>';
    body.appendChild(loader);

    function remove() {
      if (!loader) return;
      loader.classList.add('fade-out');
      win.setTimeout(function () {
        if (loader && loader.parentNode) loader.parentNode.removeChild(loader);
        loader = null;
      }, 400);
    }

    var fired = false;
    function maybe() {
      if (fired) return;
      fired = true;
      remove();
    }
    win.addEventListener('load', maybe, { once: true });
    win.setTimeout(maybe, 1200);
  }

  /* ---------- 2. Parallax (data-parallax) ---------- */
  function initParallax() {
    var els = doc.querySelectorAll('[data-parallax]');
    if (!els.length) return;

    function update() {
      if (reduceMotion.matches) return;
      var y = win.scrollY || win.pageYOffset || 0;
      els.forEach(function (el) {
        var speed = parseFloat(el.getAttribute('data-parallax')) || 0;
        el.style.transform = 'translateY(' + (y * speed * 0.4) + 'px)';
      });
    }

    var ticking = false;
    win.addEventListener('scroll', function () {
      if (reduceMotion.matches) return;
      if (!ticking) {
        rAF(function () { update(); ticking = false; });
        ticking = true;
      }
    }, { passive: true });
    reduceMotion.addEventListener('change', function () { if (reduceMotion.matches) els.forEach(function (el) { el.style.transform = ''; }); });
  }

  /* ---------- 3. Scroll reveal (.reveal) ---------- */
  function initReveal() {
    var els = doc.querySelectorAll('.reveal');
    if (!els.length) return;
    var observer = new win.IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) { observer.observe(el); });
  }

  /* ---------- 4. Tilt cards (data-tilt) ---------- */
  function initTilt() {
    if (reduceMotion.matches || win.matchMedia('(pointer: coarse)').matches || 'ontouchstart' in win) return;
    var els = doc.querySelectorAll('[data-tilt]');
    els.forEach(function (el) {
      el.addEventListener('mousemove', function (e) {
        var rect = el.getBoundingClientRect();
        var cx = rect.left + rect.width / 2;
        var cy = rect.top + rect.height / 2;
        var dx = (e.clientX - cx) / (rect.width / 2);
        var dy = (e.clientY - cy) / (rect.height / 2);
        var rx = Math.max(-6, Math.min(6, -dy * 6));
        var ry = Math.max(-6, Math.min(6, dx * 6));
        el.style.transform = 'perspective(700px) rotateX(' + rx + 'deg) rotateY(' + ry + 'deg)';
        el.style.transition = 'none';
      });
      el.addEventListener('mouseleave', function () {
        el.style.transition = 'transform .35s cubic-bezier(.2,1,.3,1)';
        el.style.transform = '';
      });
    });
  }

  /* ---------- 5. Animated counters (data-count-to) ---------- */
  function initCounters() {
    var els = doc.querySelectorAll('[data-count-to]');
    if (!els.length) return;
    var observer = new win.IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          animateCounter(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    els.forEach(function (el) { observer.observe(el); });
  }
  function animateCounter(el) {
    var target = parseFloat(el.getAttribute('data-count-to')) || 0;
    var duration = 900;
    var startTime = null;
    function step(ts) {
      if (!startTime) startTime = ts;
      var p = Math.min(1, (ts - startTime) / duration);
      el.textContent = Math.round(target * p).toLocaleString();
      if (p < 1) rAF(step);
    }
    rAF(step);
  }

  /* ---------- 6. Theme switcher ---------- */
  function initTheme() {
    var themes = [
      { n: '01', name: 'Midnight Aurora', mode: 'dark' },
      { n: '02', name: 'Obsidian Split', mode: 'dark' },
      { n: '03', name: 'Cyber Grid', mode: 'dark' },
      { n: '04', name: 'Deep Space', mode: 'dark' },
      { n: '05', name: 'Carbon Neon', mode: 'dark' },
      { n: '06', name: 'Paper Minimal', mode: 'light' },
      { n: '07', name: 'Aurora Glass', mode: 'light' },
      { n: '08', name: 'Neo Brutal', mode: 'light' },
      { n: '09', name: 'Soft Pastel', mode: 'light' },
      { n: '10', name: 'Editorial', mode: 'light' }
    ];
    var active = html.dataset.theme || '';

    var toggle = doc.createElement('button');
    toggle.id = 'pensi-theme-toggle';
    toggle.setAttribute('aria-label', 'Choose theme');
    toggle.type = 'button';
    toggle.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36 l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>';
    body.appendChild(toggle);

    var panel = doc.createElement('div');
    panel.id = 'pensi-theme-panel';
    panel.innerHTML = '<h3>Themes</h3>' + themes.map(function (t) {
      var sel = (active === t.n) ? ' active' : '';
      return '<div class="theme-item' + sel + '" data-theme="' + t.n + '"><span class="theme-name">' + t.n + ' ' + t.name + '</span><span class="theme-tag">' + t.mode + '</span></div>';
    }).join('');
    body.appendChild(panel);

    var open = false;
    toggle.addEventListener('click', function (e) {
      e.stopPropagation();
      open = !open;
      panel.classList.toggle('open', open);
    });
    doc.addEventListener('click', function () {
      if (open) { open = false; panel.classList.remove('open'); }
    });
    panel.addEventListener('click', function (e) {
      e.stopPropagation();
      var item = e.target.closest('[data-theme]');
      if (!item) return;
      setCookie('pensi_theme', item.getAttribute('data-theme'), 31536000, '/');
      win.location.reload();
    });
  }

  /* ---------- boot ---------- */
  function boot() {
    injectCSS();
    initLoader();
    initReveal();
    initCounters();
    initParallax();
    initTilt();
    initTheme();
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot);
  else boot();
})();

/* ===== v5: upload zone (click, drag & drop, preview) ===== */
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-upload-zone]').forEach(function (zone) {
        var input = zone.querySelector('input[type="file"]');
        var nameEl = zone.querySelector('[data-upload-name]');
        if (!input) return;

        function render() {
            var file = input.files && input.files[0];
            if (!file) {
                if (nameEl) nameEl.textContent = 'Belum ada file dipilih';
                return;
            }
            var kb = Math.round(file.size / 1024);
            if (nameEl) nameEl.textContent = file.name + ' · ' + kb + ' KB';
            if (!file.type || file.type.indexOf('image/') !== 0) return;
            var img = zone.querySelector('img.upload-preview');
            if (!img) {
                img = document.createElement('img');
                img.className = 'upload-preview';
                img.alt = 'Pratinjau bukti transfer';
                zone.appendChild(img);
            }
            img.src = URL.createObjectURL(file);
            img.hidden = false;
        }

        input.addEventListener('change', render);

        ['dragenter', 'dragover'].forEach(function (ev) {
            zone.addEventListener(ev, function (e) {
                e.preventDefault();
                zone.classList.add('is-dragover');
            });
        });
        ['dragleave', 'dragend'].forEach(function (ev) {
            zone.addEventListener(ev, function () { zone.classList.remove('is-dragover'); });
        });
        zone.addEventListener('drop', function (e) {
            e.preventDefault();
            zone.classList.remove('is-dragover');
            if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                render();
            }
        });
    });
});
