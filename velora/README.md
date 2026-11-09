# Velora Bellmont

Online jewelry store. PHP 8 + MySQL, PDO, Paystack payments.

## Run it on a server (cPanel, shared hosting or any LAMP host)

1. Open phpMyAdmin, select your database and import `database.sql` (Import tab). It only creates tables prefixed `vb_` (vb_users, vb_categories, vb_products, vb_orders, vb_order_items) and loads 15 products, so it does not touch tables already in the database.
2. Edit `config.php`: set `db.host`, `db.name`, `db.user`, `db.pass`.
3. Upload every file in this folder to the server (public_html or a subfolder). Make `assets/uploads` writable (chmod 755 or 775).
4. Open the site in a browser.

## Run it on your laptop (XAMPP or similar)

Put the folder in `htdocs`, import `database.sql`, edit `config.php`, open `http://localhost/velora/`.
Or, with PHP and MySQL installed: `php -S localhost:8000` inside this folder.

## Admin

Open `/login.php`. Email `admin@velorabellmont.com`, password `Velora@2026`.
Change the password before going live: run `php -r 'echo password_hash("YourNewPassword", PASSWORD_DEFAULT);'` and paste the result into `users.password_hash` for the admin row.

## Paystack (real card and mobile money payments)

1. Create a Paystack account and copy your test keys from Settings > API Keys.
2. Put them in `config.php` under `paystack` (`public_key`, `secret_key`).
3. In the Paystack dashboard set the Callback URL to `https://YOUR-DOMAIN/paystack_callback.php` and the Webhook URL to `https://YOUR-DOMAIN/paystack_webhook.php`.
4. Pay with Paystack's test card or test mobile money numbers to try it.

While `secret_key` is empty the shop runs a demo payment page (no money moves) so the checkout flow can be shown without keys. Add your key before going live, which switches the demo page off.

## Security built in

- Prepared statements (PDO) on every query
- Passwords hashed with bcrypt, session id regenerated on login
- CSRF token on every form that changes data
- Output escaped everywhere, Content-Security-Policy and other security headers
- Login lockout after 5 failed attempts
- Payment confirmed server side with Paystack (amount and currency checked), webhook signature verified
- Card and mobile money details never reach this server
- Stock decremented in a database transaction, so two buyers cannot take the last piece
- Admin area limited to the admin role, uploads limited to JPG, PNG and WebP and blocked from running PHP

## Files

- `index.php`, `shop.php`, `product.php`: browsing
- `cart.php`, `checkout.php`, `pay.php`, `pay_demo.php`, `paystack_callback.php`, `paystack_webhook.php`: buying and payment
- `register.php`, `login.php`, `logout.php`, `account.php`, `order.php`: customer accounts and orders
- `policies.php`: delivery, returns, privacy and terms
- `admin/`: dashboard, products, orders
- `includes/`: shared code (bootstrap, Paystack, header, footer)
- `database.sql`: schema and seed data
