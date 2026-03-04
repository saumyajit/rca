<?php
/**
 * RCA Data AJAX view — returns pure JSON.
 * session_write_close() lets Zabbix persist the session before we exit,
 * which prevents the "headers already sent" session warning.
 */
session_write_close();
while (ob_get_level() > 0) { ob_end_clean(); }
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
exit;
