<?php
require_once __DIR__ . '/includes/functions.php';

if (is_logged_in()) {
    redirect(home_for_role(current_user()['role']));
}
redirect('login.php');
