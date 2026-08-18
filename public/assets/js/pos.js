/**
 * AZARED - POS (Kasir) screen logic.
 *
 * IMPORTANT: this file only drives the UI. It never decides the final
 * price/tax/total - those are always recomputed and re-validated
 * server-side in Sale::checkout() at the moment of payment. Anything
 * shown here (subtotal, tax, change) is a best-effort preview for the
 * cashier only.
 */
(function () {
  'use strict';

  var root = document.getElementById('azrPos');
  if (!root) { return; }

  var CSRF_HEADER = 'X-CSRF-Token';
  var csrfMeta = document.querySelector('meta[name="azr-csrf-token"]');
  var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';

  var state = {
    cart: [],              // [{product_id, sku, name, unit_price, wholesale_price, wholesale_min_qty, qty, discount_amount, tax_percent, tax_inclusive, stock, unit_symbol, image_path}]
    customerId: null,
    discountType: 'amount',
    discountValue: 0,
    note: '',
    activeCategory: null,
  };

  var els = {
    searchInput: document.getElementById('azrPosSearch'),
    barcodeInput: document.getElementById('azrPosBarcode'),
    categoryWrap: document.getElementById('azrPosCategories'),
    productGrid: document.getElementById('azrPosProducts'),
    cartWrap: document.getElementById('azrPosCart'),
    customerSelect: document.getElementById('azrPosCustomer'),
    noteInput: document.getElementById('azrPosNote'),
    discountType: document.getElementById('azrPosDiscountType'),
    discountValue: document.getElementById('azrPosDiscountValue'),
    subtotalEl: document.getElementById('azrPosSubtotal'),
    discountEl: document.getElementById('azrPosDiscountAmt'),
    taxEl: document.getElementById('azrPosTax'),
    totalEl: document.getElementById('azrPosTotal'),
    btnHold: document.getElementById('azrPosHold'),
    btnClear: document.getElementById('azrPosClear'),
    btnCharge: document.getElementById('azrPosCharge'),
    holdList: document.getElementById('azrPosHeldList'),
    payModal: document.getElementById('azrPayModal'),
    payRows: document.getElementById('azrPaySplitRows'),
    payAddRow: document.getElementById('azrPayAddRow'),
    payTotalDue: document.getElementById('azrPayTotalDue'),
    payTotalPaid: document.getElementById('azrPayTotalPaid'),
    payChange: document.getElementById('azrPayChange'),
    payConfirm: document.getElementById('azrPayConfirm'),
    receiptModal: document.getElementById('azrReceiptModal'),
    receiptBody: document.getElementById('azrReceiptBody'),
  };

  function fmtMoney(n) {
    n = Math.round((n || 0) * 100) / 100;
    return 'Rp ' + n.toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }

  function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = s == null ? '' : String(s);
    return div.innerHTML;
  }

  // ---------- Product search ----------
  var searchTimer = null;
  function searchProducts() {
    var q = els.searchInput ? els.searchInput.value.trim() : '';
    var params = new URLSearchParams();
    if (q) { params.set('q', q); }
    if (state.activeCategory) { params.set('category_id', state.activeCategory); }

    fetch('/pos/search.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (json.success) { renderProducts(json.data); }
      });
  }

  function renderProducts(products) {
    if (!els.productGrid) { return; }
    if (!products.length) {
      els.productGrid.innerHTML = '<div class="azr-cart-empty">Produk tidak ditemukan.</div>';
      return;
    }
    els.productGrid.innerHTML = products.map(function (p) {
      var outOfStock = parseFloat(p.stock) <= 0;
      var thumb = p.image_path
        ? '<img src="' + escapeHtml(p.image_path) + '" alt="">'
        : (p.name ? escapeHtml(p.name.charAt(0).toUpperCase()) : '?');
      return (
        '<div class="azr-pos-product-card' + (outOfStock ? ' out-of-stock' : '') + '" data-product=\'' + JSON.stringify(p).replace(/'/g, '&#39;') + '\'>' +
          '<div class="azr-pos-product-thumb">' + thumb + '</div>' +
          '<div class="azr-pos-product-name">' + escapeHtml(p.name) + '</div>' +
          '<div class="azr-pos-product-price">' + fmtMoney(p.sell_price) + '</div>' +
          '<div class="azr-pos-product-stock">Stok: ' + parseFloat(p.stock) + ' ' + escapeHtml(p.unit_symbol || '') + '</div>' +
        '</div>'
      );
    }).join('');
  }

  if (els.productGrid) {
    els.productGrid.addEventListener('click', function (e) {
      var card = e.target.closest('[data-product]');
      if (!card) { return; }
      var product = JSON.parse(card.getAttribute('data-product').replace(/&#39;/g, "'"));
      addToCart(product, 1);
    });
  }

  if (els.searchInput) {
    els.searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(searchProducts, 300);
    });
  }

  if (els.categoryWrap) {
    els.categoryWrap.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-category-id]');
      if (!chip) { return; }
      state.activeCategory = chip.getAttribute('data-category-id') || null;
      els.categoryWrap.querySelectorAll('.azr-pos-cat-chip').forEach(function (c) { c.classList.remove('active'); });
      chip.classList.add('active');
      searchProducts();
    });
  }

  // ---------- Barcode scanner input ----------
  if (els.barcodeInput) {
    els.barcodeInput.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') { return; }
      e.preventDefault();
      var code = els.barcodeInput.value.trim();
      els.barcodeInput.value = '';
      if (!code) { return; }
      fetch('/products/barcode.php?barcode=' + encodeURIComponent(code), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (json.success) {
            addToCart(json.data, 1);
          } else {
            window.AzrToast(json.message || 'Barcode tidak ditemukan.', false);
          }
        });
    });
  }

  // ---------- Cart ----------
  function addToCart(product, qty) {
    if (parseFloat(product.stock) <= 0 && !product.allow_negative_stock) {
      window.AzrToast('Stok produk ini habis.', false);
      return;
    }
    var existing = state.cart.find(function (i) { return i.product_id === product.id; });
    if (existing) {
      existing.qty += qty;
    } else {
      state.cart.push({
        product_id: product.id,
        sku: product.sku,
        name: product.name,
        unit_price: parseFloat(product.sell_price),
        wholesale_price: product.wholesale_price ? parseFloat(product.wholesale_price) : null,
        wholesale_min_qty: product.wholesale_min_qty ? parseFloat(product.wholesale_min_qty) : null,
        qty: qty,
        discount_amount: 0,
        tax_percent: parseFloat(product.tax_percent || 0),
        tax_inclusive: !!parseInt(product.tax_inclusive || 0, 10),
        stock: parseFloat(product.stock),
        unit_symbol: product.unit_symbol || '',
        image_path: product.image_path || null,
      });
    }
    renderCart();
  }

  function lineUnitPrice(item) {
    if (item.wholesale_price && item.wholesale_min_qty && item.qty >= item.wholesale_min_qty) {
      return item.wholesale_price;
    }
    return item.unit_price;
  }

  function renderCart() {
    if (!els.cartWrap) { return; }
    if (!state.cart.length) {
      els.cartWrap.innerHTML = '<div class="azr-cart-empty">Keranjang masih kosong.<br>Pilih produk di sebelah kiri atau scan barcode.</div>';
    } else {
      els.cartWrap.innerHTML = state.cart.map(function (item, idx) {
        var price = lineUnitPrice(item);
        var lineSubtotal = Math.max(0, price * item.qty - item.discount_amount);
        return (
          '<div class="azr-cart-item" data-idx="' + idx + '">' +
            '<div class="azr-cart-item-info">' +
              '<div class="azr-cart-item-name">' + escapeHtml(item.name) + '</div>' +
              '<div class="azr-cart-item-price">' + fmtMoney(price) + ' / ' + escapeHtml(item.unit_symbol) + '</div>' +
            '</div>' +
            '<div class="azr-cart-item-qty">' +
              '<button type="button" class="azr-qty-btn" data-action="dec">-</button>' +
              '<input type="number" class="azr-qty-input" data-action="qty" value="' + item.qty + '" min="0.001" step="any">' +
              '<button type="button" class="azr-qty-btn" data-action="inc">+</button>' +
            '</div>' +
            '<div class="azr-cart-item-subtotal">' + fmtMoney(lineSubtotal) + '</div>' +
            '<button type="button" class="azr-cart-item-remove" data-action="remove" title="Hapus">&times;</button>' +
          '</div>'
        );
      }).join('');
    }
    renderSummary();
  }

  if (els.cartWrap) {
    els.cartWrap.addEventListener('click', function (e) {
      var row = e.target.closest('[data-idx]');
      if (!row) { return; }
      var idx = parseInt(row.getAttribute('data-idx'), 10);
      var action = e.target.getAttribute('data-action');
      if (action === 'inc') { state.cart[idx].qty += 1; renderCart(); }
      if (action === 'dec') {
        state.cart[idx].qty -= 1;
        if (state.cart[idx].qty <= 0) { state.cart.splice(idx, 1); } 
        renderCart();
      }
      if (action === 'remove') { state.cart.splice(idx, 1); renderCart(); }
    });
    els.cartWrap.addEventListener('change', function (e) {
      if (e.target.getAttribute('data-action') !== 'qty') { return; }
      var row = e.target.closest('[data-idx]');
      var idx = parseInt(row.getAttribute('data-idx'), 10);
      var val = parseFloat(e.target.value);
      if (!val || val <= 0) { state.cart.splice(idx, 1); } else { state.cart[idx].qty = val; }
      renderCart();
    });
  }

  if (els.btnClear) {
    els.btnClear.addEventListener('click', function () {
      if (!state.cart.length || window.confirm('Kosongkan keranjang?')) {
        state.cart = [];
        renderCart();
      }
    });
  }

  // ---------- Totals (client-side preview only) ----------
  function computeTotals() {
    var subtotal = 0;
    var tax = 0;
    state.cart.forEach(function (item) {
      var price = lineUnitPrice(item);
      var gross = price * item.qty;
      var net = Math.max(0, gross - item.discount_amount);
      subtotal += net;
      if (item.tax_percent > 0 && !item.tax_inclusive) {
        tax += net * item.tax_percent / 100;
      }
    });

    var discountValue = parseFloat((els.discountValue && els.discountValue.value) || 0) || 0;
    var discountType = els.discountType ? els.discountType.value : 'amount';
    var discountAmount = discountType === 'percent'
      ? subtotal * Math.min(100, Math.max(0, discountValue)) / 100
      : Math.min(subtotal, Math.max(0, discountValue));

    var grandTotal = Math.max(0, subtotal - discountAmount + tax);
    return { subtotal: subtotal, discountAmount: discountAmount, tax: tax, grandTotal: grandTotal };
  }

  function renderSummary() {
    var t = computeTotals();
    if (els.subtotalEl) { els.subtotalEl.textContent = fmtMoney(t.subtotal); }
    if (els.discountEl) { els.discountEl.textContent = '- ' + fmtMoney(t.discountAmount); }
    if (els.taxEl) { els.taxEl.textContent = fmtMoney(t.tax); }
    if (els.totalEl) { els.totalEl.textContent = fmtMoney(t.grandTotal); }
    if (els.btnCharge) { els.btnCharge.disabled = state.cart.length === 0; }
  }

  if (els.discountType) { els.discountType.addEventListener('change', renderSummary); }
  if (els.discountValue) { els.discountValue.addEventListener('input', renderSummary); }

  // ---------- Hold / Resume cart ----------
  if (els.btnHold) {
    els.btnHold.addEventListener('click', function () {
      if (!state.cart.length) { window.AzrToast('Keranjang masih kosong.', false); return; }
      var payload = {
        items: state.cart,
        customer_id: els.customerSelect ? (els.customerSelect.value || null) : null,
        note: els.noteInput ? els.noteInput.value : '',
      };
      var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[CSRF_HEADER] = csrfToken;
      fetch('/pos/hold.php', { method: 'POST', headers: headers, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          window.AzrToast(json.message || (json.success ? 'Berhasil.' : 'Gagal.'), json.success);
          if (json.success) {
            state.cart = [];
            renderCart();
            setTimeout(function () { window.location.reload(); }, 600);
          }
        });
    });
  }

  if (els.holdList) {
    els.holdList.addEventListener('click', function (e) {
      var resumeBtn = e.target.closest('[data-resume-id]');
      var discardBtn = e.target.closest('[data-discard-id]');

      if (resumeBtn) {
        var id = resumeBtn.getAttribute('data-resume-id');
        fetch('/pos/resume.php?id=' + id, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then(function (r) { return r.json(); })
          .then(function (json) {
            if (!json.success) { window.AzrToast(json.message || 'Gagal memuat keranjang.', false); return; }
            var cartData = json.data.cart_data || {};
            state.cart = cartData.items || [];
            if (els.customerSelect && cartData.customer_id) { els.customerSelect.value = cartData.customer_id; }
            if (els.noteInput && cartData.note) { els.noteInput.value = cartData.note; }
            renderCart();
            window.AzrToast('Keranjang berhasil dimuat.', true);
          });
      }

      if (discardBtn) {
        if (!window.confirm('Buang keranjang yang ditahan ini?')) { return; }
        var did = discardBtn.getAttribute('data-discard-id');
        var headers = { 'X-Requested-With': 'XMLHttpRequest' };
        var body = new FormData();
        var csrfNameMeta = document.querySelector('meta[name="azr-csrf-name"]');
        var csrfFieldName = csrfNameMeta ? (csrfNameMeta.getAttribute('content') || 'azared_csrf_token') : 'azared_csrf_token';
        body.append(csrfFieldName, csrfToken);
        fetch('/pos/discard.php?id=' + did, { method: 'POST', headers: headers, body: body })
          .then(function () { window.location.reload(); });
      }
    });
  }

  // ---------- Payment modal ----------
  var paymentRows = [];

  function openPayModal() {
    var t = computeTotals();
    paymentRows = [{ method: 'cash', amount: Math.round(t.grandTotal) }];
    renderPayRows();
    if (els.payTotalDue) { els.payTotalDue.textContent = fmtMoney(t.grandTotal); }
    updatePaySummary();
    if (els.payModal) { els.payModal.classList.add('open'); }
  }

  function renderPayRows() {
    if (!els.payRows) { return; }
    els.payRows.innerHTML = paymentRows.map(function (row, idx) {
      return (
        '<div class="azr-pay-split-row" data-idx="' + idx + '">' +
          '<select class="azr-select" data-pay="method" style="max-width:150px;">' +
            ['cash', 'transfer', 'debit', 'credit', 'ewallet', 'qris', 'other'].map(function (m) {
              return '<option value="' + m + '"' + (row.method === m ? ' selected' : '') + '>' + payLabel(m) + '</option>';
            }).join('') +
          '</select>' +
          '<input type="number" class="azr-input" data-pay="amount" value="' + row.amount + '" min="0" step="any" placeholder="Jumlah">' +
          (paymentRows.length > 1 ? '<button type="button" class="azr-cart-item-remove" data-pay="remove">&times;</button>' : '') +
        '</div>'
      );
    }).join('');
  }

  function payLabel(m) {
    return { cash: 'Tunai', transfer: 'Transfer', debit: 'Kartu Debit', credit: 'Kartu Kredit', ewallet: 'E-Wallet', qris: 'QRIS', other: 'Lainnya' }[m] || m;
  }

  if (els.payRows) {
    els.payRows.addEventListener('change', function (e) {
      var row = e.target.closest('[data-idx]');
      if (!row) { return; }
      var idx = parseInt(row.getAttribute('data-idx'), 10);
      if (e.target.getAttribute('data-pay') === 'method') { paymentRows[idx].method = e.target.value; }
      if (e.target.getAttribute('data-pay') === 'amount') { paymentRows[idx].amount = parseFloat(e.target.value) || 0; }
      updatePaySummary();
    });
    els.payRows.addEventListener('click', function (e) {
      if (e.target.getAttribute('data-pay') !== 'remove') { return; }
      var row = e.target.closest('[data-idx]');
      paymentRows.splice(parseInt(row.getAttribute('data-idx'), 10), 1);
      renderPayRows();
      updatePaySummary();
    });
  }

  if (els.payAddRow) {
    els.payAddRow.addEventListener('click', function () {
      paymentRows.push({ method: 'cash', amount: 0 });
      renderPayRows();
    });
  }

  function updatePaySummary() {
    var t = computeTotals();
    var paid = paymentRows.reduce(function (s, r) { return s + (parseFloat(r.amount) || 0); }, 0);
    if (els.payTotalPaid) { els.payTotalPaid.textContent = fmtMoney(paid); }
    if (els.payChange) { els.payChange.textContent = fmtMoney(Math.max(0, paid - t.grandTotal)); }
    if (els.payConfirm) { els.payConfirm.disabled = paid < t.grandTotal; }
  }

  if (els.btnCharge) {
    els.btnCharge.addEventListener('click', function () {
      if (!state.cart.length) { return; }
      openPayModal();
    });
  }

  if (els.payConfirm) {
    els.payConfirm.addEventListener('click', function () {
      els.payConfirm.disabled = true;
      var payload = {
        items: state.cart.map(function (i) {
          return { product_id: i.product_id, qty: i.qty, discount_amount: i.discount_amount };
        }),
        payments: paymentRows.filter(function (r) { return r.amount > 0; }),
        customer_id: els.customerSelect ? (els.customerSelect.value || null) : null,
        discount_type: els.discountType ? els.discountType.value : 'amount',
        discount_value: parseFloat((els.discountValue && els.discountValue.value) || 0) || 0,
        note: els.noteInput ? els.noteInput.value : '',
      };
      var headers = { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
      headers[CSRF_HEADER] = csrfToken;

      fetch('/pos/checkout.php', { method: 'POST', headers: headers, body: JSON.stringify(payload) })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (!json.success) {
            window.AzrToast(json.message || 'Transaksi gagal.', false);
            els.payConfirm.disabled = false;
            return;
          }
          if (els.payModal) { els.payModal.classList.remove('open'); }
          state.cart = [];
          if (els.noteInput) { els.noteInput.value = ''; }
          if (els.discountValue) { els.discountValue.value = 0; }
          renderCart();
          showReceipt(json.data);
        })
        .catch(function () {
          window.AzrToast('Gagal menghubungi server.', false);
          els.payConfirm.disabled = false;
        });
    });
  }

  document.querySelectorAll('[data-azr-modal-close-pay]').forEach(function (btn) {
    btn.addEventListener('click', function () { els.payModal.classList.remove('open'); });
  });

  // ---------- Receipt ----------
  function showReceipt(sale) {
    if (!els.receiptModal || !els.receiptBody) { return; }
    var itemsHtml = sale.items.map(function (it) {
      return (
        '<div class="azr-receipt-item-name">' + escapeHtml(it.product_name) + '</div>' +
        '<div class="azr-receipt-row"><span>' + parseFloat(it.qty) + ' x ' + fmtMoney(it.unit_price) + '</span><span>' + fmtMoney(it.subtotal) + '</span></div>'
      );
    }).join('');
    var paymentsHtml = sale.payments.map(function (p) {
      return '<div class="azr-receipt-row"><span>' + payLabel(p.method) + '</span><span>' + fmtMoney(p.amount) + '</span></div>';
    }).join('');

    els.receiptBody.innerHTML =
      '<div class="azr-receipt size-80mm" id="azrReceiptPrintArea">' +
        '<div class="azr-receipt-center">' +
          '<div class="azr-receipt-brand">AZARED</div>' +
          '<div>' + escapeHtml(sale.store_name || 'Toko AZARED') + '</div>' +
          '<div>' + escapeHtml(sale.store_address || '') + '</div>' +
        '</div>' +
        '<hr class="azr-receipt-hr">' +
        '<div class="azr-receipt-row"><span>No. Invoice</span><span>' + escapeHtml(sale.invoice_no) + '</span></div>' +
        '<div class="azr-receipt-row"><span>Tanggal</span><span>' + escapeHtml(sale.created_at) + '</span></div>' +
        '<div class="azr-receipt-row"><span>Kasir</span><span>' + escapeHtml(sale.cashier_name || '-') + '</span></div>' +
        (sale.customer_name ? '<div class="azr-receipt-row"><span>Pelanggan</span><span>' + escapeHtml(sale.customer_name) + '</span></div>' : '') +
        '<hr class="azr-receipt-hr">' +
        itemsHtml +
        '<hr class="azr-receipt-hr">' +
        '<div class="azr-receipt-row"><span>Subtotal</span><span>' + fmtMoney(sale.subtotal) + '</span></div>' +
        '<div class="azr-receipt-row"><span>Diskon</span><span>-' + fmtMoney(sale.discount_amount) + '</span></div>' +
        '<div class="azr-receipt-row"><span>Pajak</span><span>' + fmtMoney(sale.tax_amount) + '</span></div>' +
        '<div class="azr-receipt-row" style="font-weight:800;font-size:13px;"><span>TOTAL</span><span>' + fmtMoney(sale.grand_total) + '</span></div>' +
        '<hr class="azr-receipt-hr">' +
        paymentsHtml +
        '<div class="azr-receipt-row"><span>Kembali</span><span>' + fmtMoney(sale.change_amount) + '</span></div>' +
        '<hr class="azr-receipt-hr">' +
        '<div class="azr-receipt-center">Terima kasih atas kunjungan Anda!<br>*** AZARED ***</div>' +
      '</div>';

    els.receiptModal.classList.add('open');
    els.receiptModal.dataset.saleId = sale.id;
  }

  document.querySelectorAll('[data-azr-print-receipt]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });
  document.querySelectorAll('[data-azr-modal-close-receipt]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      els.receiptModal.classList.remove('open');
      window.location.reload();
    });
  });

  // ---------- Init ----------
  renderCart();
  searchProducts();
})();
