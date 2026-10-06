# All Feature included shoping Management System (SHOP_SYS)

Plain PHP 8 (no Composer, no framework) + MySQL/MariaDB + Bootstrap 5. Custom MVC, RBAC, audit trail, POS, purchasing, inventory, reports.

## 1. Install on Windows 10 with Laragon
1. Copy this folder to `C:\laragon\www\shop_sys`.
2. Start Laragon (**Start All**: Apache + MySQL). Laragon's PHP already has `pdo_mysql`, `mbstring`, `fileinfo`, `iconv` enabled.
3. Open **http://shop_sys.test/install.php** (Laragon auto virtual host; or `http://localhost/shop_sys/install.php`).
4. Laragon defaults: host `127.0.0.1`, port `3306`, user `root`, password *(empty)*. Choose a database name (e.g. `shop_sys`) - it is created for you.
5. Enter business name and the first administrator. Tick *Load demo data* to get sample books/stationery and users `manager`, `cashier`, `clerk` (password `Demo@1234`; they must change it at first login).
6. After success, **delete `install.php`**, then sign in at `http://shop_sys.test/`.

Bootstrap, Bootstrap Icons and Chart.js load from a CDN, so the browser needs internet access (to run fully offline, download them into `assets/vendor/` and update the two layout files in `app/views/layout/`).

## 2. How it works
* **Login -> tiles**: after sign-in the home page shows up to three tiles (Administration, POS, Product & Inventory). A tile is shown only if the user's roles have at least one permission in that module. Selecting a tile logs into that system (its own menu/dashboard); switch via the top-bar menu.
* **RBAC**: User -> Role(s) -> Permission (`module.feature.action`) -> enforced server-side on every action and used to hide menu items/buttons. Administrator role = all permissions. Edit roles in *Administration -> Roles & Permissions* (per-action matrix, "enable all module access" switch).
* **Segregation of duties**: PO, stock adjustments, transfers, purchase returns, stocktake variances and expenses cannot be approved by their creator (unless Administrator).

### Modules
| Area | Highlights |
|---|---|
| Administration | users (create/edit/activate/lock/reset/assign roles), roles, employees, attendance, branches/warehouses/counters, suppliers (profile, products, payments, statements), customers (groups, credit limit, payments, loyalty), price lists, promotions/coupons/quantity discounts, expenses with approval, cash drawer & reconciliation, financial transactions, settings, audit logs, reports & dashboard |
| POS | register sessions (open/suspend/resume/close, cash variance), scan/search terminal, hold/resume carts, line/cart discounts with manager approval, price override permission, split payments, credit sales with limit check, exchange credit notes, receipts (80 mm), A4 invoice, digital receipt link, email, void, returns/exchanges with approval, history |
| Product & Inventory | products, books (ISBN validation), stationery (variants), categories/brands/authors/publishers/units, **barcode generation & label printing**, stock levels, adjustments (approval), movements, purchase orders -> GRN -> purchase invoice -> supplier payment, purchase returns, transfers, stocktake, low-stock alerts -> purchase request -> draft PO |

### Barcodes
* Product form: **Generate** button fills a unique EAN-13 (in-store prefix `200`). Blank barcode on save is auto-generated; books with a 13-digit ISBN use the ISBN.
* *Inventory -> Barcode Labels*: bulk **Generate missing barcodes**, choose quantity per product, label size (38x25 / 50x30 / 70x40 mm), name/price on/off, print. Single label: product list -> barcode icon (also "Generate new barcode").
* Barcodes are rendered as SVG (EAN-13, or Code 39 for other codes). USB scanners work as keyboards: scan into the POS search box.

### Reports
Sales (daily/product/cashier/payment), returns, voids, inventory valuation, low stock, purchases, supplier & customer outstanding, top customers, expenses, **profit & loss**, cash variances, attendance, employee sales, price change log. Filter, search, **compare with previous period**, **CSV**, **PDF** (built-in writer, no library), print. Scheduled e-mail (CSV): add schedules in *Reports*, then run `cron\run_scheduled_reports.php` from **Windows Task Scheduler**:
`C:\laragon\bin\php\php-8.x.x\php.exe C:\laragon\www\shop_sys\cron\run_scheduled_reports.php` (daily). E-mail needs PHP `mail()`/sendmail configured (Laragon: Menu -> Tools -> Mail Sender / Mailpit).
Note: the built-in PDF uses Latin-1 fonts; for Sinhala text use CSV or the browser *Print -> Save as PDF* view. Data is stored as utf8mb4, so Sinhala names work everywhere else.

## 3. Security measures
Prepared statements everywhere; output escaping; CSRF token on every POST (and AJAX header); bcrypt hashes with rehash; password policy; forced change for admin-set passwords; login lockout (5 failures -> 15 min) + IP throttling + generic error messages; session fixation protection, HttpOnly/SameSite cookies, idle timeout (30 min); server-side RBAC on every action; server-side price/discount/tax calculation (client values are never trusted); stock row locks and DB transactions; idempotency key on checkout (no double sales); validated image uploads (MIME + extension whitelist, random names, PHP execution blocked in `uploads/`); CSV formula-injection protection; security headers; `.htaccess` blocks `app/`, `database/`, `storage/`; full audit log (logins, CRUD with diffs, price changes, stock adjustments, voids/refunds, role/permission changes, approvals, exports).
Production tips: serve over HTTPS, set `debug` to `false` (default), delete `install.php`, restrict `app/config.local.php` file permissions.

## 4. Folder map
`index.php` front controller (`?r=controller/action`) - `install.php` - `app/core` (DB, Auth, Controller, Validator, helpers) - `app/controllers` - `app/views` - `app/lib` (Stock, Pos engine, Barcode, SimplePdf, Report...) - `app/entities.php` (declarative CRUD) - `app/permissions.php` (RBAC catalogue) - `app/menus.php` - `database/schema.sql` - `cron/` - `assets/` - `uploads/`.
To add a permission/feature: add it to `permissions.php`, re-run seeding (`Seeder::permissions()`; the installer does this) and reference it with `Auth::can('module.feature.action')`.
