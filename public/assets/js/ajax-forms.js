/**
 * AZARED - generic AJAX form helper.
 * Any <form data-azr-ajax> is submitted via fetch() to its action URL.
 * The server MUST return the standard {success, message, data, errors}
 * envelope (see App\Helpers\Response). On success the page is reloaded
 * (simplest way to reflect server state everywhere it is used) unless
 * the form has data-azr-no-reload, in which case a `azr:ajax-success`
 * CustomEvent is dispatched on the form with the response detail so a
 * page can react (e.g. POS updating the cart) without a full reload.
 *
 * No sensitive logic lives here: every action performed via these
 * helpers is re-validated and re-authorized on the server.
 */
(function () {
  'use strict';

  function showFieldErrors(form, errors) {
    form.querySelectorAll('[data-azr-error]').forEach(function (el) { el.textContent = ''; });
    Object.keys(errors || {}).forEach(function (field) {
      var el = form.querySelector('[data-azr-error="' + field + '"]');
      if (el) { el.textContent = errors[field]; }
    });
  }

  function toast(message, ok) {
    var el = document.createElement('div');
    el.className = 'azr-alert ' + (ok ? 'azr-alert-success' : 'azr-alert-error');
    el.style.position = 'fixed';
    el.style.top = '16px';
    el.style.right = '16px';
    el.style.zIndex = '999';
    el.style.minWidth = '260px';
    el.style.boxShadow = '0 6px 20px rgba(11,61,145,0.18)';
    el.textContent = message;
    document.body.appendChild(el);
    setTimeout(function () {
      el.style.transition = 'opacity 300ms ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 300);
    }, 3500);
  }

  async function submitAjaxForm(form) {
    var submitBtn = form.querySelector('[type="submit"]');
    if (submitBtn) { submitBtn.disabled = true; }

    try {
      var formData = new FormData(form);
      var response = await fetch(form.getAttribute('action'), {
        method: form.getAttribute('method') || 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
      });
      var json = await response.json();

      if (!json.success) {
        showFieldErrors(form, json.errors);
        toast(json.message || 'Terjadi kesalahan.', false);
        return;
      }

      toast(json.message || 'Berhasil.', true);

      if (form.hasAttribute('data-azr-no-reload')) {
        form.dispatchEvent(new CustomEvent('azr:ajax-success', { detail: json, bubbles: true }));
        var modal = form.closest('.azr-modal-backdrop');
        if (modal) { modal.classList.remove('open'); }
      } else {
        setTimeout(function () { window.location.reload(); }, 500);
      }
    } catch (err) {
      toast('Gagal menghubungi server. Periksa koneksi Anda.', false);
    } finally {
      if (submitBtn) { submitBtn.disabled = false; }
    }
  }

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches && form.matches('[data-azr-ajax]')) {
      e.preventDefault();
      submitAjaxForm(form);
    }
  });

  // Generic AJAX action buttons (no form) e.g. toggle-status / destroy via POST.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-azr-ajax-action]');
    if (!btn) { return; }

    var message = btn.getAttribute('data-azr-confirm');
    if (message && !window.confirm(message)) { return; }

    e.preventDefault();
    var url = btn.getAttribute('data-azr-ajax-action');
    var csrfNameMeta = document.querySelector('meta[name="azr-csrf-name"]');
    var csrfFieldName = csrfNameMeta ? (csrfNameMeta.getAttribute('content') || 'azared_csrf_token') : 'azared_csrf_token';
    var csrfInput = document.querySelector('input[name^="azared_csrf"], input[name="' + csrfFieldName + '"]');
    var formData = new FormData();
    if (csrfInput) { formData.append(csrfInput.name, csrfInput.value); }

    btn.disabled = true;
    fetch(url, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        toast(json.message || (json.success ? 'Berhasil.' : 'Gagal.'), json.success);
        if (json.success) { setTimeout(function () { window.location.reload(); }, 500); }
      })
      .catch(function () { toast('Gagal menghubungi server.', false); })
      .finally(function () { btn.disabled = false; });
  });

  window.AzrToast = toast;
})();
