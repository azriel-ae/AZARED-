/**
 * AZARED - shared frontend behaviour.
 * No sensitive logic lives here: every action performed via these
 * helpers is re-validated and re-authorized on the server.
 */
(function () {
  'use strict';

  // ---- Sidebar toggle (mobile) ----
  var toggleBtn = document.querySelector('[data-azr-menu-toggle]');
  var sidebar = document.querySelector('.azr-sidebar');
  var backdrop = document.querySelector('[data-azr-sidebar-backdrop]');

  function closeSidebar() {
    if (sidebar) sidebar.classList.remove('open');
    if (backdrop) backdrop.classList.remove('open');
  }

  if (toggleBtn && sidebar) {
    toggleBtn.addEventListener('click', function () {
      sidebar.classList.toggle('open');
      if (backdrop) backdrop.classList.toggle('open');
    });
  }
  if (backdrop) {
    backdrop.addEventListener('click', closeSidebar);
  }

  // ---- Generic modal open/close via data attributes ----
  document.querySelectorAll('[data-azr-modal-open]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-azr-modal-open');
      var modal = document.getElementById(id);
      if (modal) modal.classList.add('open');
    });
  });

  document.querySelectorAll('[data-azr-modal-close]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var backdropEl = btn.closest('.azr-modal-backdrop');
      if (backdropEl) backdropEl.classList.remove('open');
    });
  });

  // ---- Confirm before destructive form submits (status change, reset password) ----
  document.querySelectorAll('[data-azr-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var message = form.getAttribute('data-azr-confirm') || 'Apakah Anda yakin?';
      if (!window.confirm(message)) {
        e.preventDefault();
      }
    });
  });

  // ---- Auto-dismiss alerts after a few seconds ----
  document.querySelectorAll('.azr-alert[data-azr-autodismiss]').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 300ms ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 300);
    }, 4000);
  });

  // ---- Product form: autofill the display tax-rate field from the
  // selected tax option's data-rate attribute (views/products/form.php).
  // Delegated + presence-guarded so this file stays safe to load on
  // every page even when the element isn't on the current one. ----
  var productTaxSelect = document.getElementById('azrProductTaxSelect');
  if (productTaxSelect) {
    productTaxSelect.addEventListener('change', function () {
      var opt = this.selectedOptions[0];
      var percentInput = document.getElementById('azrProductTaxPercent');
      if (opt && opt.value && percentInput) {
        percentInput.value = opt.getAttribute('data-rate') || 0;
      }
    });
  }

  // ---- "Cetak" (print) buttons: [data-azr-print] instead of an inline
  // onclick="window.print()" attribute, since inline event-handler
  // attributes are blocked by the app's `script-src 'self'` CSP too. ----
  document.querySelectorAll('[data-azr-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });

  // ---- Auto-print pages (thermal receipt / label print views) ----
  // Any element with [data-azr-autoprint] triggers window.print() once
  // the page has finished loading. Used by views/sales/show.php instead
  // of an inline <script>, so it works under a strict CSP.
  if (document.querySelector('[data-azr-autoprint]')) {
    window.addEventListener('load', function () { window.print(); });
  }
})();
