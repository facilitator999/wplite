/* WPlite — carousel autoplay + endless scroll. No dependencies. */
(function () {
  'use strict';

  /* ---------- carousel ---------- */
  var track = document.querySelector('.carousel-track');
  if (track && track.children.length > 1) {
    var slides = track.children.length;
    var dots = document.querySelectorAll('.carousel-dots .dot');
    var index = 0;
    var timer = null;
    var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    var userPaused = false;

    var go = function (i) {
      index = (i + slides) % slides;
      track.style.transform = 'translateX(-' + index * 100 + '%)';
      dots.forEach(function (d, n) {
        d.classList.toggle('active', n === index);
        if (n === index) { d.setAttribute('aria-current', 'true'); } else { d.removeAttribute('aria-current'); }
      });
    };
    var play = function () {
      if (reduced || userPaused || timer) return;
      timer = setInterval(function () { go(index + 1); }, 5000);
    };
    var pause = function () {
      clearInterval(timer);
      timer = null;
    };

    dots.forEach(function (d) {
      d.addEventListener('click', function () {
        pause();
        go(parseInt(d.dataset.index, 10));
      });
    });

    var carousel = track.closest('.carousel');

    // WCAG 2.2.2: explicit pause/stop control for the auto-advancing slides
    var pauseBtn = carousel.querySelector('.carousel-pause');
    if (pauseBtn) {
      if (reduced) { pauseBtn.hidden = true; } // autoplay never starts
      pauseBtn.addEventListener('click', function () {
        userPaused = !userPaused;
        pauseBtn.setAttribute('aria-pressed', String(userPaused));
        pauseBtn.setAttribute('aria-label', userPaused ? 'Play slideshow' : 'Pause slideshow');
        pauseBtn.querySelector('.icon-pause').style.display = userPaused ? 'none' : '';
        pauseBtn.querySelector('.icon-play').style.display = userPaused ? '' : 'none';
        if (userPaused) { pause(); } else { play(); }
      });
    }

    carousel.addEventListener('pointerenter', pause);
    carousel.addEventListener('pointerleave', play);
    carousel.addEventListener('touchstart', pause, { passive: true });
    carousel.addEventListener('focusin', pause);
    carousel.addEventListener('focusout', play);
    play();
  }

  /* ---------- endless scroll ---------- */
  var sentinel = document.getElementById('sentinel');
  var grid = document.getElementById('grid');
  var template = document.getElementById('tile-template');
  if (sentinel && grid && template && 'IntersectionObserver' in window) {
    var loading = false;

    var loadMore = function () {
      if (loading) return;
      loading = true;
      var offset = parseInt(sentinel.dataset.offset, 10);
      fetch(sentinel.dataset.feed + '?offset=' + offset)
        .then(function (r) { return r.json(); })
        .then(function (data) {
          data.posts.forEach(function (p) {
            var tile = template.content.cloneNode(true);
            var a = tile.querySelector('a');
            a.href = p.url;
            var img = tile.querySelector('img');
            if (p.thumb) { img.src = p.thumb; } else { img.remove(); }
            tile.querySelector('.tile-title').textContent = p.title;
            grid.appendChild(tile);
          });
          sentinel.dataset.offset = offset + data.posts.length;
          if (!data.hasMore) {
            observer.disconnect();
            sentinel.remove();
          }
          loading = false;
        })
        .catch(function () { loading = false; });
    };

    var observer = new IntersectionObserver(function (entries) {
      if (entries.some(function (e) { return e.isIntersecting; })) loadMore();
    }, { rootMargin: '600px' });
    observer.observe(sentinel);
  }
})();
