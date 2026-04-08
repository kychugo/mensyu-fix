<?php
/**
 * 登出
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';

session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
