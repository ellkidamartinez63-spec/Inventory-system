<?php
require_once 'includes/auth.php';

do_logout();

header("Location: login.php?msg=" . urlencode("You have been logged out"));
exit;