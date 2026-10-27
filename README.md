# More & More

More & More is a native PHP 8.1+ and MySQL online store for Sri Lanka. The storefront uses PHP-rendered HTML, CSS and vanilla JavaScript; PDO connects to MySQL. Customers can create optional username/password accounts without email, or place orders as guests without an account.

## Features

- Responsive product catalog with the 13 requested categories, search, sorting, discounts, product specifications, image gallery and inventory availability.
- Session-backed guest cart and checkout. Product prices, discount totals, delivery and stock are recalculated on the server. Checkout reserves inventory in a database transaction and creates a unique order number.
- Optional customer sign-up/sign-in with a unique username and password; no Gmail/email is needed. Signed-in customers can see their own order history, current fulfillment status, status timeline, and private order detail page; guests can securely track status using their private tracking code. Guest checkout remains available and does not require registration.
- Private order tracking requires both the order number and a cryptographically random tracking code, displayed once after checkout. Tracking attempts are rate limited.
- Optional cash on delivery. There is no fake online payment flow.
- WhatsApp contact button on storefront pages and contact details, controlled by the country-code phone number in admin Store settings.
- Protected admin dashboard with a private, unlinked sign-in route, administrator username/password authentication, a forced initial password change, and live product/order/customer/sales statistics; product, image, stock and category management; order status history, private admin notes, cancellation and inventory restoration; settings; customer listing and account access management; contact message inbox; inventory/order reports and CSV downloads.
- MySQL schema with indexes, foreign keys, order snapshots, session/CSRF protection, output escaping, prepared statements, admin login throttling, upload MIME/dimension checks and Apache access rules.

## Requirements

- XAMPP with PHP 8.1 or later and MySQL/MariaDB.
- Enable PHP extensions `pdo_mysql`, `fileinfo`, and `mbstring`.
- Apache `.htaccess` overrides enabled. The site also works on other Apache/PHP/MySQL hosting that supports these requirements.

## Run locally with XAMPP

1. Copy the project folder to `C:\xampp\htdocs\more-and-more`. Start **Apache** and **MySQL** in the XAMPP Control Panel.
2. Open `http://localhost/phpmyadmin`, select **Import**, and import the project’s `database.sql`. It creates the `more_and_more` database, all tables, and the 13 starter categories. It creates no products, customer accounts, or administrator password.
3. Copy `.env.example` to `.env`. Set a new random `APP_KEY` of at least 32 characters and verify the database settings. For a default local XAMPP installation:

   ```dotenv
   APP_ENV=development
   APP_URL=http://localhost/more-and-more
   APP_KEY=put-a-unique-random-value-here
   ADMIN_LOGIN_TOKEN=put-a-different-random-64-character-hex-value-here
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=more_and_more
   DB_USER=root
   DB_PASSWORD=
   SESSION_SECURE=0
   ```

   Generate unique secrets with PHP:

   ```powershell
   C:\xampp\php\php.exe -r "echo 'APP_KEY=', bin2hex(random_bytes(32)), PHP_EOL, 'ADMIN_LOGIN_TOKEN=', bin2hex(random_bytes(32)), PHP_EOL;"
   ```

   Put the two generated values on their matching lines in `.env`. `.env` is ignored by Git and denied by Apache. Never reuse example secrets.

4. Create the first administrator from a terminal in the project folder:

   ```powershell
   C:\xampp\php\php.exe scripts\create-admin.php
   ```

   Enter a unique username (for example `killerex`), optional display name and a unique password of 12–72 characters when prompted. Email is not required. This command is CLI-only and refuses to create another initial admin if one already exists.

5. To display your private administrator sign-in link in your local terminal, run:

   ```powershell
   C:\xampp\php\php.exe scripts\show-admin-login-url.php
   ```

   The direct `/admin/login.php` address returns 404, and no store page links to sign-in. Keep the generated link private. Each successful admin sign-in rotates both random URL parts and invalidates the previous link; run the command again for the next sign-in. The secret URL is an extra layer—not a replacement for a strong administrator password, rate limiting, or backend authorization.
6. Visit `http://localhost/more-and-more/`. Sign in with the username and password created in step 4. Set a new unique password on first sign-in before using the dashboard. Sign-in rotates the private URL; use the CLI command to retrieve its next value.
7. Create categories or products, upload images, and configure the delivery areas/charges and contact details in **Store settings**. Enter the WhatsApp number with its country code (for example, `+94` for Sri Lanka); leave it blank to hide the WhatsApp button.

Images must be JPG, PNG or WebP, no larger than 5 MB, 6000×6000 pixels or 30 million pixels. PHP’s `upload_max_filesize` and `post_max_size` in `C:\xampp\php\php.ini` must allow uploads up to the application limit. Restart Apache after editing `php.ini`.

## Testing

Run the local pure-PHP checks:

```powershell
C:\xampp\php\php.exe tests\run.php
```

Check PHP syntax across the application:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { C:\xampp\php\php.exe -l $_.FullName }
```

Guest checkout and administrator workflows require MySQL. Acceptance checks are described below; test with a non-production database and a disposable admin account.

### Guest checkout acceptance

1. Create a product with stock `2`, regular price `10000`, discounted price `8500`, and publish it.
2. Add quantity `2` to the guest bag. Verify the total uses server-stored prices and any delivery settings.
3. Place a COD order with a test name, phone and address. Verify the confirmation shows an `MM...` order number and one-time tracking code.
4. In admin → Orders, verify the order/contact/address/items/totals; confirm stock is `0`, status is `Pending`, and history contains the initial event.
5. Track the order using the number and code; verify a wrong code does not disclose order details. Confirm the order in admin and verify tracking status history changes.
6. Cancel it with a reason; verify cancellation history, retained order items, and stock restoration to `2`. Repeat with a duplicate/expired checkout session and confirm it cannot create a duplicate order.

### Customer account acceptance

1. Register with a username, name, mobile number and password; verify no email address is requested and the password is saved as a hash.
2. Sign out and sign back in with the username/password. Confirm the username is shown in the storefront header.
3. Place an order while signed in and verify it appears only in that customer’s account history and the admin account list.
4. Sign out, place a separate guest order and verify checkout still works without an account or email address.
5. Disable a customer account in admin and verify it can no longer sign in; re-enable it and confirm previous orders remain intact.

### Admin acceptance

1. While signed out, open `/admin/login.php` and `/admin/index.php`; both should return 404. Generate the private sign-in link using the CLI setup command, then attempt a bad password repeatedly and verify rate limiting.
2. Sign in using the administrator username, change the initial password from **Change password**, create/edit/hide a product and category, and verify public listing/search immediately reflects published changes.
3. Change an order’s status and private admin notes; confirm the notes do not appear in customer tracking. Print an invoice and export the CSV reports.
4. Try an invalid image type/oversized file and invalid prices/stock; verify the server rejects them and does not change saved data.

## Production deployment

1. Provision PHP 8.1+ hosting with PDO MySQL, fileinfo, mbstring, HTTPS, and a MySQL database. Import `database.sql`.
2. Upload the project over SFTP/SSH, preferably configure the web root to the project’s public PHP pages. If the project root is the web root, make sure Apache honors its `.htaccess` rules; do not disable those rules.
3. Configure the environment outside version control: `APP_ENV=production`, `APP_DEBUG=0`, a unique random `APP_KEY` (32+ characters), `SESSION_SECURE=1`, HTTPS `APP_URL`, and least-privilege MySQL credentials. Never use the XAMPP `root` account in production.
4. Give PHP write access only to `uploads/products/`; use HTTPS and back up the database and uploaded images. Ensure the hosting provider permits the upload size limits.
5. Run the CLI-only first-admin setup command over SSH. Do not expose `scripts/` as a web-accessible setup page.
6. Test guest checkout, tracking, admin login, order status changes and stock restoration in the production-like staging environment before opening the store.

The application supports configurable COD and delivery charges/service areas. Card or local gateway payments are not integrated; configure and verify a real provider and server-side webhooks before adding online payment support. Customer registration is optional; guest checkout remains available.

### Existing database upgrade

If upgrading a database created with an earlier version of this project, back it up first and import `database-migrate-username-accounts.sql` once using phpMyAdmin, then import `database-migrate-admin-security.sql` once. Existing administrators receive temporary usernames such as `admin1`; set a desired username before signing in. The migrations preserve administrator password hashes and orders and require each existing administrator to change their password. Also set a private `ADMIN_LOGIN_TOKEN` in `.env`.

## Project map

- `index.php`, `products.php`, `category.php`, `product.php`, `search.php` — customer catalog.
- `cart.php`, `checkout.php`, `place-order.php`, `order-success.php`, `track-order.php` — guest shopping/order flow.
- `account-register.php`, `account-login.php`, `account.php`, `account-order.php`, `account-logout.php` — optional username-only customer accounts and private order history/details.
- `contact.php`, `about.php`, `faq.php`, `privacy-policy.php`, `terms.php`, `return-policy.php` — information pages.
- `admin/` — admin authentication and protected dashboard, including submitted contact messages. Apache maps the private tokenized URL to the login handler; direct login-file requests return 404.
- `config/database.php`, `includes/` — environment, PDO, session/CSRF, output escaping and shared layout/helpers.
- `database.sql` — initial MySQL database, indexes, foreign keys, and categories/settings. `database-migrate-username-accounts.sql` upgrades an existing installation.
- `uploads/products/` — validated product image files; executable file extensions are blocked by Apache.

All amounts are stored as whole Sri Lankan rupees (LKR). Discounted unit prices are snapshotted in order items; reports only count sales marked paid and delivered.
