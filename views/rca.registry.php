<?php
/**
 * RCA Registry view — outputs JSON for the RcaRegistry AJAX endpoint.
 * @var array $data  Response data from RcaRegistry::doAction()
 */

echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
