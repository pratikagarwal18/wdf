<?php
/**
 * Practical 10: Secure Logout Controller
 * Destroys session, wipes cookies, revokes remember tokens, and redirects with notification.
 */

require_once __DIR__ . '/auth.php';

logoutUser(true);
