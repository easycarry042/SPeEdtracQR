# SPeED TraQR Setup

1. Install QR package:
   - `composer require simplesoftwareio/simple-qrcode`
2. Check GD extension:
   - `php -m | grep gd`
   - If missing: `sudo apt-get install php-gd`
3. Create storage symlink:
   - `php artisan storage:link`
4. Prepare queue table + run migrations:
   - `php artisan queue:table && php artisan migrate`
5. Seed roles and permissions:
   - `php artisan db:seed --class=RolesAndPermissionsSeeder`
6. Configure `.env`:
   - Database settings (`DB_*`)
   - `MAIL_MAILER=log`
   - `QUEUE_CONNECTION=database`
7. Start queue worker:
   - `php artisan queue:work`
8. Start app:
   - `php artisan serve`
9. Login credentials:
   - Use your seeded demo accounts (Admin / Department Head / Clerk) from your seeder.
