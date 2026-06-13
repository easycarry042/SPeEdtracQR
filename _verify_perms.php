<?php
$admin = App\Models\User::find(2);
echo 'Super Admin can manage system: '.var_export($admin->can('manage system'), true).PHP_EOL;
echo 'Visible users for org-wide admin: '.App\Models\User::count().PHP_EOL;
