<?php
// /public/app/public/pages/logout.php
auth_logout();
session_start();
flash_set('success', 'Logged out.');
redirect('/?r=login');