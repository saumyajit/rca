<?php
/**
 * RCA Registry view — outputs JSON response for the RcaRegistry AJAX endpoint.
 * Zabbix MVC requires every action to have a matching view file.
 *
 * @var array $data  Response data from RcaRegistry::doAction()
 */

header('Content-Type: application/json');
echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
