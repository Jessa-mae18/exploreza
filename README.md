# Exploreza – Travel & Tour Guide Booking System (PHP + MySQL)

## Run it (XAMPP)
1. Copy this folder to `C:\xampp\htdocs\exploreza`. Start Apache and MySQL.
2. phpMyAdmin > Import > `database/schema.sql` (first time only).
3. Existing database? Also import `database/update_photos.sql` once (adds photo columns).
4. Open http://localhost/exploreza/
Admin login: `admin` / `Admin_123`.

## Structure
- `index.php` landing page
- `auth/` login, register, verify, logout
- `customer/` availability, book, checkout, my-trips
- `admin/` dashboard, bookings, payments, manage, photos, guide-payments
- `tour-guide/` guide portal: dashboard, bookings, availability, places, payments, profile
- `includes/` header, footer, functions, auth-check, admin layout and actions
- `config/` database and site settings (BASE_URL is detected automatically)
- `assets/` css, js, images, uploads/payment-ids
- `database/` schema.sql, update_guide_destinations.sql
- `errors/` 403, 404, 500

## Photos
Admin > Photos: upload your own, search free Wikimedia Commons photos, or click Auto-fill. Credits show on the landing page.

## Tour guide accounts
Import `database/update_guide_accounts.sql` once. Then Admin > Manage content > 'Create logins for all guides without one' (temporary password Guide_123), or create a login per guide.
Guides log in at auth/login.php and land in the guide portal. Only the admin creates guide accounts.

## Version 2 (map, guide fee, ratings)
Import `database/update_v2.sql` once (it also creates the guide logins). Guide fee for travelers: GUIDE_FEE_PER_DAY in config/config.php (PHP 1,500 per tour day).
Maps use Leaflet + OpenStreetMap; addresses are looked up with Nominatim. Travelers rate guides in My trips after the admin marks a tour completed.

## Version 3 (age check)
Import `database/update_v3.sql` once. Travelers must be 18+ (checked at sign-up and again at booking).
The package page shows a map with nearby accommodations (OpenStreetMap Overpass API).
