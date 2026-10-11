<?php
require_once __DIR__ . '/includes/functions.php';

$_SESSION = [];
session_destroy();
session_start();
flash('success', 'Vous êtes déconnecté.');
redirect('login.php');
