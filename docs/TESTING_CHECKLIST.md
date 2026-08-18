# AZARED — Manual Testing Checklist

Use this before every production deploy. Each item lists what to do
and what "pass" looks like. Items marked **[SEC]** are security-critical
and should never be skipped.

---

## AUTH

- [ ] Login with correct username/password → redirected to `/dashboard.php`, session cookie set with `HttpOnly`.
- [ ] Login with wrong password → generic "username atau password salah" message (does **not** say which field was wrong). **[SEC]**
- [ ] Fail login 5x in a row (default `login.max_attempts`) → 6th attempt returns HTTP 429 and the 429 page/message, even with the correct password. **[SEC]**
- [ ] Wait out the lockout window (`login.lockout_minutes`) → login succeeds again with correct credentials.
- [ ] Logout → session destroyed; pressing browser Back does not show any authenticated page. **[SEC]**
- [ ] While logged in as User A in one browser, have an Admin deactivate User A in another browser/tab → User A's very next request is redirected to login, not allowed to continue using the stale session. **[SEC]**
- [ ] While logged in as a Manager, have an Admin downgrade them to Cashier → the Manager's next page load reflects the new (reduced) menu/permissions immediately, without needing to log out. **[SEC]**
- [ ] Navigate directly to any `/api/*.php` URL, or any clean route (e.g. `/users/index.php`) while logged out → redirected to `/login.php`, page content never renders. **[SEC]**
- [ ] Submit any form (e.g. add expense) after leaving the tab open for a long time / after the CSRF token would be stale → 419 page or JSON `419` shown, not a silent failure or the action going through anyway. **[SEC]**

## USER MANAGEMENT

- [ ] Create user as Admin, assign Admin role → succeeds.
- [ ] Create user as a non-Admin holding `users.create` (e.g. Manager), attempt to assign the Admin role → rejected server-side with a clear error, even if the role dropdown is tampered with via devtools. **[SEC]**
- [ ] Edit an existing user's role/store access as Admin → change is saved and takes effect on that user's *next request* without them logging out.
- [ ] Log in as any user, open `/users/edit.php?id=<your own id>` → role, status, and store-access fields are disabled in the UI; submit the form with those fields force-enabled via devtools → server ignores the tampered values and keeps the original role/status/store access. **[SEC]**
- [ ] As a non-Admin with `users.edit`, try to edit an Admin account by URL (`/users/edit.php?id=<admin id>`) → 403. **[SEC]**
- [ ] Toggle a user's status to Inactive → that user cannot log in (correct error), and if already logged in, their next request is kicked to login. **[SEC]**
- [ ] Reset another user's password → they can log in with the new temporary password and are prompted to change it.

## POS / KASIR

- [ ] Search product by name and by SKU → correct results.
- [ ] Scan/enter a barcode → correct product added to cart.
- [ ] Add same product twice → quantity increments instead of duplicating the line.
- [ ] Set quantity to 0 or negative via the qty input → line is removed, not saved as 0/negative.
- [ ] Apply a transaction-level discount (amount and percent) → total recalculates correctly; discount cannot exceed subtotal (negative total is not possible).
- [ ] Add a product with tax configured → tax amount shown matches the product's tax rate; a `tax_transactions` row is created after checkout.
- [ ] Complete checkout with a single payment method exactly equal to the total → change = 0, sale status `completed`.
- [ ] Complete checkout with split payment (two methods) summing to more than the total → change calculated correctly, both payment rows saved.
- [ ] Attempt checkout with payments summing to **less** than the total → blocked, clear message, no sale is created. **[SEC]**
- [ ] Print receipt (58mm and 80mm) → renders without page chrome (sidebar/topbar hidden via `@media print`).
- [ ] Hold a cart, resume it → all items/discount/customer restored exactly.
- [ ] Log in as Cashier A (assigned to Store 1), hold a cart. Log in as Cashier B (assigned to Store 2) → Cashier B's held-cart list does **not** show Cashier A's cart, and directly calling `/pos/resume.php?id=<A's cart id>` returns "not found", not the cart contents. **[SEC]**
- [ ] Process a return on a completed sale → stock is restored (if restock checked), `returned_qty` updates on the sale item, status becomes `partially_returned` or `returned` correctly.
- [ ] Open the POS screen in two browser tabs as the same cashier, sell the last unit of a low-stock product in both tabs at nearly the same time → only one succeeds (or both succeed only if stock allowed it); stock never goes negative unless `allow_negative_stock` is set on that product. **[SEC]**

## INVENTORY

- [ ] Create a purchase, mark it Received → stock increases by the purchased quantity; `avg_cost` recalculates using the weighted-average formula (verify by hand for one product: `(old_stock*old_avg + qty*cost) / (old_stock+qty)`).
- [ ] Sell a product → `stock_movements` gets a `sale` row with correct `before_stock`/`after_stock`; product stock decreases.
- [ ] Manually adjust stock (in and out) from `/inventory/index.php` → movement recorded with a reason, stock updates correctly.
- [ ] Process a purchase return → stock decreases, `purchase_items.returned_qty` updates.
- [ ] Process a sales return with "restock" checked vs. unchecked → stock only changes when restock is checked.
- [ ] View `/reports/inventory` with a date range → stock in/out totals for that range match the sum of `stock_movements` for each product in that window.

## FINANCE

- [ ] Add an expense → appears in `/expenses/index.php`, filterable by category/date/payment method.
- [ ] Delete (soft-delete) an expense → disappears from the list and from finance totals, but the audit log still shows the deletion event.
- [ ] Check `/finance` (dashboard) HPP figure against a manual calculation: sum of `(qty - returned_qty) * cost_price` from `sale_items` for the period — should match exactly (cost_price is a snapshot, not the live product cost).
- [ ] Check `/finance/profit-loss` for a period with known sales+expenses → Laba Kotor = Pendapatan Bersih − HPP; Laba Bersih = Laba Kotor − Total Biaya, both arithmetically correct on screen.
- [ ] Check `/finance/cash-flow` opening balance for a period → matches the account's balance calculated as of the period's first moment (opening_balance + all movements before that date).
- [ ] Change a tax rate in `/tax/settings`, then re-open a *pre-existing* sale from before the change → the old sale's tax_transactions row still shows the OLD rate, not the new one. **[SEC/QA]**

## TAX

- [ ] Create a new tax type with an initial rate → appears active immediately, usable on the product form.
- [ ] Add a new rate to an existing tax with a future `effective_from` → old rate row gets `effective_to` set to the day before; rate history table shows both rows.
- [ ] Sell a product with a tax-inclusive tax type → `tax_transactions.tax_amount` is back-calculated from the inclusive price (not 0).
- [ ] Check `/tax/output` and `/tax/input` filters (date, store, customer/supplier, tax type) each narrow the list correctly, and the summary row total matches the sum of the visible-filtered rows (not the whole table).
- [ ] Set a manual "Nomor Faktur Pajak" on a sale from `/tax/output` → saved, visible on reload.
- [ ] Close a tax period covering a transaction's date, then try to edit that transaction's faktur number → blocked with a clear message referencing the closed period. **[SEC]**
- [ ] As Cashier, try to open `/tax/settings` directly by URL → 403. **[SEC]**

## REPORTS

- [ ] `/reports/sales`, `/reports/purchases`, `/reports/inventory`, `/reports/tax` — date range filter actually restricts results (spot-check one row outside the range is excluded).
- [ ] Store filter on each report restricts to that store only.
- [ ] Export CSV on each report → opens cleanly in Excel/Google Sheets, column headers match the on-screen table, row count matches the filtered total (not just the current page).
- [ ] Print button on each report → sidebar/topbar/filter bar hidden, table prints cleanly.

## RESPONSIVE UI

Test at these widths (browser devtools device toolbar is fine):
- [ ] 1440px (desktop) — sidebar always visible, multi-column stat grids.
- [ ] 1024px (laptop/tablet landscape) — stat grids collapse to 2 columns, layout stays usable.
- [ ] 768px (tablet portrait) — sidebar collapses behind the menu toggle; opening it doesn't break the page layout underneath.
- [ ] 375px (mobile) — every data table scrolls horizontally within its own container (page itself does not scroll sideways); every modal (add expense, add tax rate, add category, POS payment) fits on screen with its Save button reachable by scrolling *inside* the modal, not cut off; filter bars stack into a single column.
- [ ] POS screen specifically at 375–414px width — product grid and cart stack vertically and both remain usable (not just technically visible).

## ERROR PAGES (navigate directly, while logged in)

- [ ] A non-existent URL → 404 page, AZARED-branded, no PHP path/stack trace visible.
- [ ] A URL you lack permission for → 403 page.
- [ ] Trigger a CSRF failure (submit a form with devtools-edited/removed hidden token field) → 419 page or JSON, not a blank page or raw PHP error.
- [ ] Trigger 6 failed logins → 429 page/message.
- [ ] (Staging only) Temporarily force an exception (e.g. rename a DB table) and hit any page → 500 page shown, and confirm the real error appears in server logs (`error_log`), not on screen.

---

## Sign-off

| Area | Tester | Date | Result |
|---|---|---|---|
| Auth | | | |
| User Management | | | |
| POS | | | |
| Inventory | | | |
| Finance | | | |
| Tax | | | |
| Reports | | | |
| Responsive UI | | | |
| Error Pages | | | |
