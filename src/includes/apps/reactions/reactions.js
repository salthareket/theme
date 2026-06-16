/**
 * SaltReactions — Frontend JS
 *
 * Handles reaction button clicks, optimistic UI updates,
 * guest cookie storage, and login redirect.
 *
 * @version 1.1.0
 *
 * @changelog
 *   1.1.0 - 2026-05-18
 *     - Fix: flushHydrate() — items 100'er chunk'a bölünüyor, sırayla gönderiliyor
 *       Büyük sayfalarda (500+ ürün) tek AJAX'ta 1500 item gitmesi önlendi
 *       Her chunk tamamlanınca sonraki gönderilir, hata olsa bile devam eder
 *   1.0.0 - 2026-05-06
 *     - Add: click handler, optimistic toggle, count update, login redirect
 *     - Add: guest cookie read for initial state on page load
 *
 * ─── HOW TO USE ───────────────────────────────────────────
 *
 * // Button rendered by PHP/Twig:
 * // <button class="salt-reaction-btn" data-reaction-object="42"
 * //         data-reaction-type="like" data-reaction-object-type="post"
 * //         data-reaction-style="icon-count" data-reaction-color="#e11d48">
 * //   <i class="far fa-heart"></i> <span class="salt-reaction-count">17</span>
 * // </button>
 *
 * // Guest reactions are read from cookie sh_guest_reactions on page load
 * // and applied as is-active class to matching buttons.
 *
 * ──────────────────────────────────────────────────────────
 */

(function () {
  'use strict';

  const COOKIE_KEY = 'sh_guest_reactions';

  // ─── SITE CONFIG ───────────────────────────────────────
  // site-config JSON element'inden bir kez oku, her yerde kullan

  var _siteConfig = (function() {
    try {
      var el = document.getElementById('site-config');
      return el ? JSON.parse(el.textContent) : {};
    } catch(e) { return {}; }
  })();

  // ─── INIT ──────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    applyGuestStates();
    bindButtons();
    // WP Rocket cache uyumu: sadece cache'den gelen sayfalarda count'ları tazele.
    // site-config JSON'unda cached === true ise sayfa cache'den gelmiş demektir.
    // AJAX ile yüklenen içerikler için çalışmaz — onlar zaten fresh PHP render.
    if ( _siteConfig.cached === true && ! document.body.dataset.reactionsHydrated ) {
      document.body.dataset.reactionsHydrated = '1';
      hydrateCounts();
    }

    // AjaxInitManager'a kaydet
    if (window.AjaxInitManager) {
      // initMap'e ekle: selector → fonksiyon adi
      window.AjaxInitManager.initMap['.salt-reaction-btn'] = 'salt_reactions_init';
      // containerAwareFunctions'a ekle: $container ile cagrilsin
      if (Array.isArray(window.AjaxInitManager.containerAwareFunctions)) {
        window.AjaxInitManager.containerAwareFunctions.push('salt_reactions_init');
      }
    }
  });

  // AjaxInitManager $container ile cagirir
  window.salt_reactions_init = function ($container) {
    var root = ($container && $container[0]) ? $container[0] : document;
    // AJAX ile yüklenen butonlar fresh PHP render — hydration gerekmez
    root.querySelectorAll('.salt-reaction-btn[data-reaction-object]').forEach(function(btn) {
      btn.dataset.reactionFresh = '1';
    });
    bindButtons(root);
    applyGuestStates(root);
  };

  // Fallback: salt:content:loaded custom event
  document.addEventListener('salt:content:loaded', function () { bindButtons(); });

  // ─── BIND ──────────────────────────────────────────────

  // Cumulative debounce state: key = objectId+type, value = {timer, pendingValue, currentCount}
  var _cumulativeState = {};

  function bindButtons(container) {
    var root = container || document;
    root.querySelectorAll('.salt-reaction-btn:not([data-reaction-bound])').forEach(function (btn) {
      btn.setAttribute('data-reaction-bound', '1');
      btn.addEventListener('click', handleClick);
    });
  }

  // ─── CLICK HANDLER ─────────────────────────────────────

  function handleClick(e) {
    e.preventDefault();
    e.stopPropagation();

    const btn        = e.currentTarget;
    const objectId   = parseInt(btn.dataset.reactionObject, 10);
    const objectType = btn.dataset.reactionObjectType || 'post';
    const type       = btn.dataset.reactionType || 'like';
    const loginUrl   = btn.dataset.reactionLogin || '';
    const mode       = btn.dataset.reactionMode || 'toggle';
    // require_login: data-reaction-login varsa true, yoksa false
    const requireLogin = !!loginUrl;

    if (!objectId || !type) return;

    // Login redirect for guests
    if (loginUrl && !isLoggedIn()) {
      window.location.href = loginUrl;
      return;
    }

    // Cumulative: limit kontrolu
    if (mode === 'cumulative') {
      handleCumulativeClick(btn, objectId, objectType, type, requireLogin);
      return;
    }

    // Toggle / Additive: normal flow
    const wasActive = btn.classList.contains('is-active');
    applyOptimistic(btn, !wasActive);
    btn.disabled = true;

    fetchToggle(objectId, objectType, type, 1, requireLogin)
      .then(function (data) {
        if (data.error) {
          applyOptimistic(btn, wasActive);
          if (data.message === 'login_required' && data.login_url) {
            window.location.href = data.login_url;
          }
          return;
        }
        const isActive = data.has_reaction !== undefined ? data.has_reaction : !wasActive;
        applyOptimistic(btn, isActive);
        if (data.count !== undefined) {
          updateCount(btn, data.count);
          syncSiblings(objectId, objectType, type, isActive, data.count);
        }
        btn.dispatchEvent(new CustomEvent('salt:reaction:toggled', {
          bubbles: true,
          detail: { objectId, objectType, type, mode, action: data.action, count: data.count, has: isActive },
        }));
      })
      .catch(function () { applyOptimistic(btn, wasActive); })
      .finally(function () { btn.disabled = false; });
  }

  // ─── CUMULATIVE HANDLER (debounce) ────────────────────

  function handleCumulativeClick(btn, objectId, objectType, type, requireLogin) {
    const limit = parseInt(btn.dataset.reactionLimit || '0', 10);
    const stateKey = objectId + '_' + objectType + '_' + type;

    // State yoksa olustur
    if (!_cumulativeState[stateKey]) {
      _cumulativeState[stateKey] = {
        timer:        null,
        pendingTicks: 0,
        serverValue:  parseInt(btn.dataset.reactionValue || '0', 10),
      };
    }

    const state = _cumulativeState[stateKey];

    // Limit kontrolu — server value + pending ticks
    const totalValue = state.serverValue + state.pendingTicks;
    if (limit > 0 && totalValue >= limit) {
      showLimitReached(btn);
      return;
    }

    // Optimistic: count +1, animasyon
    state.pendingTicks++;
    applyOptimisticCumulative(btn);

    // Debounce: 1 sn tiklama yoksa gonder
    clearTimeout(state.timer);
    state.timer = setTimeout(function () {
      flushCumulative(btn, objectId, objectType, type, stateKey, requireLogin);
    }, 1000);
  }

  function flushCumulative(btn, objectId, objectType, type, stateKey, requireLogin) {
    const state = _cumulativeState[stateKey];
    if (!state || state.pendingTicks <= 0) return;

    const ticks = state.pendingTicks;
    state.pendingTicks = 0;
    state.timer = null;

    // TEK AJAX — amount = ticks
    fetchToggle(objectId, objectType, type, ticks, requireLogin)
      .then(function (data) {
        if (data.error) {
          if (data.message === 'login_required' && data.login_url) {
            window.location.href = data.login_url;
          }
          // Revert
          revertOptimisticCumulative(btn, ticks);
          return;
        }
        // Server value ile guncelle
        if (data.value !== undefined) {
          state.serverValue = data.value;
          btn.dataset.reactionValue = data.value;
        }
        // Server count ile count'u duzelt
        if (data.count !== undefined) {
          updateCount(btn, data.count);
        }
        if (data.action === 'limit_reached') {
          showLimitReached(btn);
        }
        btn.dispatchEvent(new CustomEvent('salt:reaction:toggled', {
          bubbles: true,
          detail: { objectId, objectType, type, mode: 'cumulative', action: data.action, count: data.count, value: data.value },
        }));
      })
      .catch(function () {
        revertOptimisticCumulative(btn, ticks);
      });
  }

  // ─── OPTIMISTIC UI ─────────────────────────────────────

  // Cumulative: her tikta count +1 goster, is-active ekle
  function applyOptimisticCumulative(btn) {
    btn.classList.add('is-active');
    btn.setAttribute('aria-pressed', 'true');
    const countEl = btn.querySelector('.salt-reaction-count');
    if (countEl) {
      const current = parseInt(countEl.textContent, 10) || 0;
      countEl.textContent = current + 1;
      // Kisa animasyon
      countEl.style.transform = 'scale(1.3)';
      countEl.style.transition = 'transform .15s ease';
      setTimeout(function () { countEl.style.transform = ''; }, 150);
    }
    // Clap animasyonu — icon'u yukari ziplat
    const icon = btn.querySelector('i, img, .salt-reaction-icon');
    if (icon) {
      icon.style.transform = 'scale(1.4) translateY(-3px)';
      icon.style.transition = 'transform .15s ease';
      setTimeout(function () { icon.style.transform = ''; }, 200);
    }
  }

  function revertOptimisticCumulative(btn, amount) {
    amount = amount || 1;
    const countEl = btn.querySelector('.salt-reaction-count');
    if (countEl) {
      const current = parseInt(countEl.textContent, 10) || amount;
      countEl.textContent = Math.max(0, current - amount);
    }
  }

  function showLimitReached(btn) {
    btn.classList.add('salt-reaction--limit');
    btn.title = 'Limit reached';
    // Kisa titreme animasyonu
    btn.style.animation = 'shReactionShake .3s ease';
    setTimeout(function () {
      btn.style.animation = '';
      btn.classList.remove('salt-reaction--limit');
    }, 400);
  }

  function applyOptimistic(btn, active) {
    const color    = btn.dataset.reactionColor || '';
    const style    = btn.dataset.reactionStyle || 'icon-count';

    btn.classList.toggle('is-active', active);
    btn.setAttribute('aria-pressed', active ? 'true' : 'false');

    // Icon swap — data-icon-off/on base64 encoded HTML
    const iconOffB64 = btn.dataset.iconOff || '';
    const iconOnB64  = btn.dataset.iconOn  || '';

    function decodeIcon(b64) {
      try { return atob(b64); } catch(e) { return ''; }
    }

    const iconHtml = active ? decodeIcon(iconOnB64) : decodeIcon(iconOffB64);

    if (iconHtml) {
      // Mevcut icon element'ini bul ve replace et
      const existingIcon = btn.querySelector('i, img, .salt-reaction-icon');
      if (existingIcon) {
        const tmp = document.createElement('span');
        tmp.innerHTML = iconHtml;
        const newIcon = tmp.firstElementChild;
        if (newIcon) existingIcon.replaceWith(newIcon);
      }
    }

    // Pill style color
    if (style === 'pill') {
      const inner = btn.querySelector('.salt-reaction-pill-inner');
      if (inner) {
        inner.style.backgroundColor = active ? color : '';
        inner.style.color           = active ? '#fff' : '';
      }
    }
  }

  function updateCount(btn, count) {
    const countEl = btn.querySelector('.salt-reaction-count');
    if (countEl) {
      countEl.textContent = count;
    }
  }

  function syncSiblings(objectId, objectType, type, isActive, count) {
    document.querySelectorAll(
      `.salt-reaction-btn[data-reaction-object="${objectId}"][data-reaction-object-type="${objectType}"][data-reaction-type="${type}"]`
    ).forEach(function (sibling) {
      applyOptimistic(sibling, isActive);
      if (count !== undefined) updateCount(sibling, count);
    });
  }

  // ─── HYDRATION (WP Rocket cache uyumu) ───────────────────
  // Sadece viewport'ta görünen butonlar için count + has state çek.
  // IntersectionObserver ile lazy — scroll ettikçe yenileri çekilir.
  // Tek batch AJAX — görünür butonlar toplu sorgulanır.

  var _hydrateQueue   = [];   // bekleyen butonlar
  var _hydrateTimer   = null; // debounce timer
  var _hydratedKeys   = {};   // zaten çekilenler — tekrar çekme

  function hydrateCounts() {
    var buttons = document.querySelectorAll('.salt-reaction-btn[data-reaction-object]');
    if (!buttons.length) return;

    if (!('IntersectionObserver' in window)) {
      // Fallback: IntersectionObserver yoksa hepsini çek
      buttons.forEach(function(btn) { queueHydrate(btn); });
      flushHydrate();
      return;
    }

    var observer = new IntersectionObserver(function(entries) {
      entries.forEach(function(entry) {
        if (entry.isIntersecting) {
          queueHydrate(entry.target);
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '200px' }); // 200px önceden yükle

    buttons.forEach(function(btn) { observer.observe(btn); });
  }

  function queueHydrate(btn) {
    // AJAX ile yüklenen fresh butonlar — hydration gerekmez
    if (btn.dataset.reactionFresh) return;

    var key = btn.dataset.reactionObject + '|'
            + btn.dataset.reactionObjectType + '|'
            + btn.dataset.reactionType;
    if (_hydratedKeys[key]) return; // zaten çekildi
    _hydratedKeys[key] = true;
    _hydrateQueue.push({
      object_id:   parseInt(btn.dataset.reactionObject, 10),
      object_type: btn.dataset.reactionObjectType || 'post',
      type:        btn.dataset.reactionType || 'like',
    });
    // Debounce: 50ms içinde gelen butonları tek batch'e topla
    clearTimeout(_hydrateTimer);
    _hydrateTimer = setTimeout(flushHydrate, 50);
  }

  function flushHydrate() {
    if (!_hydrateQueue.length) return;
    var items = _hydrateQueue.splice(0); // queue'yu boşalt

    var nonce = _siteConfig.nonce
      || ( _siteConfig.ajax && _siteConfig.ajax.ajax_nonce )
      || '';

    // 100'er item'lık chunk'lara böl — büyük sayfalarda payload kontrolü
    var CHUNK_SIZE = 100;
    var chunks = [];
    for (var i = 0; i < items.length; i += CHUNK_SIZE) {
      chunks.push(items.slice(i, i + CHUNK_SIZE));
    }

    function sendChunk(index) {
      if (index >= chunks.length) return;
      var chunk = chunks[index];

      fetch(getApiUrl('reaction_state'), {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
        body:    JSON.stringify({ vars: { items: chunk } }),
      })
      .then(function(res) { return res.ok ? res.json() : null; })
      .then(function(data) {
        if (data && !data.error && data.results) {
          data.results.forEach(function(result) {
            var selector = '.salt-reaction-btn'
              + '[data-reaction-object="'      + result.object_id   + '"]'
              + '[data-reaction-object-type="' + result.object_type + '"]'
              + '[data-reaction-type="'        + result.type        + '"]';
            document.querySelectorAll(selector).forEach(function(btn) {
              if (result.count !== undefined) updateCount(btn, result.count);
              if (isLoggedIn() && result.has !== undefined) {
                applyOptimistic(btn, result.has);
              }
            });
          });
        }
        // Sonraki chunk'ı gönder
        sendChunk(index + 1);
      })
      .catch(function() {
        // Hata olsa bile sonraki chunk'ı dene
        sendChunk(index + 1);
      });
    }

    sendChunk(0);
  }

  // ─── GUEST STATE ───────────────────────────────────────

  function applyGuestStates(container) {
    if (isLoggedIn()) return;

    var reactions = readGuestCookie();
    if (!reactions.length) return;

    var root = container || document;
    reactions.forEach(function (item) {
      root.querySelectorAll(
        '.salt-reaction-btn[data-reaction-object="' + item.object_id + '"][data-reaction-object-type="' + item.object_type + '"][data-reaction-type="' + item.type + '"]'
      ).forEach(function (btn) {
        var mode  = btn.dataset.reactionMode || 'toggle';
        var value = parseInt(item.value || '1', 10);
        if (mode === 'cumulative') {
          // Cumulative: value'yu set et, is-active ekle
          btn.dataset.reactionValue = value;
          if (value > 0) {
            btn.classList.add('is-active');
            btn.setAttribute('aria-pressed', 'true');
            updateCount(btn, parseInt(btn.querySelector('.salt-reaction-count')?.textContent || '0', 10));
          }
        } else {
          applyOptimistic(btn, true);
        }
      });
    });
  }

  function readGuestCookie() {
    const match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_KEY + '=([^;]*)'));
    if (!match) return [];
    try {
      return JSON.parse(decodeURIComponent(match[1])) || [];
    } catch (e) {
      return [];
    }
  }

  // ─── API ───────────────────────────────────────────────

  function getApiUrl(method) {
    var base = ( _siteConfig.ajax && _siteConfig.ajax.site_url )
      ? _siteConfig.ajax.site_url.replace(/\/$/, '')
      : window.location.origin;
    return base + '/api/' + method;
  }

  function fetchToggle(objectId, objectType, type, amount, requireLogin) {
    var nonce = _siteConfig.nonce
      || ( _siteConfig.ajax && _siteConfig.ajax.ajax_nonce )
      || '';

    var body = {
      vars: {
        object_id:     objectId,
        object_type:   objectType,
        type:          type,
        require_login: requireLogin !== false,
      },
    };
    if (amount && amount > 1) {
      body.vars.amount = amount;
    }

    return fetch(getApiUrl('reaction_toggle'), {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce':   nonce,
      },
      body: JSON.stringify(body),
    }).then(function (res) {
      if (!res.ok) throw new Error('Network error');
      return res.json();
    });
  }

  // ─── HELPERS ───────────────────────────────────────────

  function isLoggedIn() {
    return document.body.classList.contains('logged-in');
  }

})();
