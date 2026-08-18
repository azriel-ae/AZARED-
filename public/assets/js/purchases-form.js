/**
 * AZARED - Purchases (Pembelian) form: dynamic item rows + payment
 * split rows. Extracted from an inline <script> so the page complies
 * with the strict `script-src 'self'` CSP (no 'unsafe-inline').
 * Presence-guarded: safely does nothing if this markup isn't on the page.
 */
(function () {
  'use strict';

  var itemsBody = document.getElementById('azrItemsBody');
  var itemTpl = document.getElementById('azrItemRowTpl');
  var paymentsWrap = document.getElementById('azrPurchasePayments');
  var payTpl = document.getElementById('azrPayRowTpl');

  if (!itemsBody || !itemTpl || !paymentsWrap || !payTpl) { return; }

  function addItemRow() {
    itemsBody.appendChild(itemTpl.content.cloneNode(true));
  }
  function addPayRow() {
    paymentsWrap.appendChild(payTpl.content.cloneNode(true));
  }

  var addItemBtn = document.getElementById('azrAddItemRow');
  var addPayBtn = document.getElementById('azrAddPayRow');
  if (addItemBtn) addItemBtn.addEventListener('click', addItemRow);
  if (addPayBtn) addPayBtn.addEventListener('click', addPayRow);

  itemsBody.addEventListener('click', function (e) {
    if (e.target.getAttribute('data-role') === 'remove-row') {
      e.target.closest('tr').remove();
    }
  });
  itemsBody.addEventListener('change', function (e) {
    if (e.target.getAttribute('data-role') === 'product') {
      var row = e.target.closest('tr');
      var opt = e.target.selectedOptions[0];
      var costInput = row.querySelector('[data-role="cost"]');
      if (opt && costInput && parseFloat(costInput.value) === 0) {
        costInput.value = opt.getAttribute('data-cost') || 0;
      }
    }
    if (e.target.getAttribute('data-role') === 'tax') {
      var row2 = e.target.closest('tr');
      var opt2 = e.target.selectedOptions[0];
      var rateInput = row2.querySelector('[data-role="tax-rate"]');
      if (rateInput) {
        rateInput.value = opt2 ? (opt2.getAttribute('data-rate') || 0) : 0;
      }
    }
  });
  paymentsWrap.addEventListener('click', function (e) {
    if (e.target.getAttribute('data-role') === 'remove-pay') {
      e.target.closest('.azr-pay-split-row').remove();
    }
  });

  addItemRow();
  addPayRow();
})();
