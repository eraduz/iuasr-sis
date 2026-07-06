/* IUASR Design System — minimale interactiviteit
   - Scrollspy voor de zijbalk
   - Trigger voor motion-demo's
   - Print-vriendelijke kleur-copy bij klik op een swatch
*/

(function () {
  'use strict';

  /* ------------------------------------------------------------
     SCROLLSPY
     ------------------------------------------------------------ */
  const navLinks = Array.from(document.querySelectorAll('.ds-nav a[href^="#"]'));
  const sections = navLinks
    .map(a => document.getElementById(a.getAttribute('href').slice(1)))
    .filter(Boolean);

  function setActive(id) {
    navLinks.forEach(a => {
      a.classList.toggle('is-active', a.getAttribute('href') === '#' + id);
    });
  }

  if ('IntersectionObserver' in window && sections.length) {
    const io = new IntersectionObserver((entries) => {
      const visible = entries
        .filter(e => e.isIntersecting)
        .sort((a, b) => b.intersectionRatio - a.intersectionRatio);
      if (visible[0]) setActive(visible[0].target.id);
    }, {
      rootMargin: '-30% 0px -55% 0px',
      threshold: [0, 0.1, 0.4, 0.8]
    });
    sections.forEach(s => io.observe(s));
  }

  /* ------------------------------------------------------------
     KOPIEER HEX BIJ KLIK
     ------------------------------------------------------------ */
  document.querySelectorAll('[data-copy]').forEach(el => {
    el.style.cursor = 'copy';
    el.addEventListener('click', () => {
      const val = el.getAttribute('data-copy');
      if (!val) return;
      navigator.clipboard?.writeText(val).then(() => {
        const orig = el.getAttribute('data-original') || el.textContent;
        el.setAttribute('data-original', orig);
        const old = el.style.transition;
        el.style.transition = 'background 200ms';
        const prevBg = el.style.background;
        el.style.background = 'rgba(21,106,66,0.18)';
        setTimeout(() => { el.style.background = prevBg; el.style.transition = old; }, 600);
      }).catch(() => {});
    });
  });

  /* ------------------------------------------------------------
     MOTION-DEMO TRIGGERS
     ------------------------------------------------------------ */
  document.querySelectorAll('[data-motion]').forEach(stage => {
    stage.addEventListener('click', () => {
      const kind = stage.getAttribute('data-motion');
      if (kind === 'pulse') {
        stage.classList.remove('do-pulse');
        void stage.offsetWidth;
        stage.classList.add('do-pulse');
        setTimeout(() => stage.classList.remove('do-pulse'), 400);
      }
      if (kind === 'rise') {
        const card = stage.querySelector('.motion-card');
        if (card) {
          card.style.animation = 'none';
          void card.offsetWidth;
          card.style.animation = 'm-rise 420ms cubic-bezier(0.2, 0, 0, 1)';
        }
      }
      if (kind === 'reveal') {
        const items = stage.querySelectorAll('.reveal-item');
        items.forEach((it, i) => {
          it.style.animation = 'none';
          void it.offsetWidth;
          it.style.animation = `m-rise 420ms cubic-bezier(0.2,0,0,1) ${i * 80}ms backwards`;
        });
      }
      if (kind === 'progress') {
        const bar = stage.querySelector('.progress > i');
        if (bar) {
          bar.style.width = '0%';
          void bar.offsetWidth;
          bar.style.width = (40 + Math.floor(Math.random() * 50)) + '%';
        }
      }
      if (kind === 'page') {
        stage.classList.remove('do-page');
        void stage.offsetWidth;
        stage.classList.add('do-page');
        setTimeout(() => stage.classList.remove('do-page'), 700);
      }
    });
  });

  /* ------------------------------------------------------------
     PROGRESS DEMO — initial fill
     ------------------------------------------------------------ */
  document.querySelectorAll('[data-motion="progress"] .progress > i').forEach(bar => {
    setTimeout(() => { bar.style.width = '64%'; }, 250);
  });

  /* ------------------------------------------------------------
     TAB DEMO
     ------------------------------------------------------------ */
  document.querySelectorAll('[data-tabs]').forEach(group => {
    group.querySelectorAll('.tab').forEach(tab => {
      tab.addEventListener('click', () => {
        group.querySelectorAll('.tab').forEach(t => t.classList.remove('is-active'));
        tab.classList.add('is-active');
      });
    });
  });
})();
