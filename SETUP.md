# SPeED TraQR Setup

1. Install the packages and enable some php extensions:
   - `go to php.ini and add these extensions`
      - `extention=gd`
      - `extension=exif`
   - Then run: `composer require simplesoftwareio/simple-qrcode`
   - Then run: `npm install axios`
   - Lastly run: `npm install`
2. Check GD extension (optional):
   - For windows: `php -m | findstr gd`
   - For linux: `php -m | grep gd`
   - If missing: `sudo apt-get install php-gd`

--BEFORE DOING SETPS 3-5 GO TO STEP 6 FIRST--
3. Create storage symlink:
   - `php artisan storage:link`
4. Prepare queue table + run migrations:
   - `php artisan queue:table && php artisan migrate`
5. Seed roles and permissions:
   - `php artisan db:seed --class=RolesAndPermissionsSeeder`
---------------------------------------------
6. Configure `.env` but first make a copy of the `.env.example`, then rename the copy to `.env`:
   - Database settings (`DB_*`) copy these and paste it to the `.env` under the `DB_CONNECTION=sqlite`:
      REMOVE THE `#`
      # DB_CONNECTION=mysql
      # DB_HOST=localhost
      # DB_PORT=3306
      # DB_DATABASE=speedtraqr
      # DB_USERNAME=root
      # DB_PASSWORD= //your mysql password
   - Then open MySQL Workbench and create a new schema: `speedtraqr`
   - `Then proceed to steps 3-5`
   - `MAIL_MAILER=log`
   - `QUEUE_CONNECTION=database`
7. Start queue worker:
   - `php artisan queue:work`
8. Start app:
   - `php artisan serve`
   - `npm run dev`
9. Login credentials:
   - Use your seeded demo accounts (Admin / Department Head / Clerk) from your seeder.
