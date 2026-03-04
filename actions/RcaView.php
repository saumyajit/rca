<?php
/**
 * RcaView — Main page controller for the RCA module.
 *
 * Populates filter dropdowns from live Zabbix data:
 *   - Environments: from hostname_map.json (pr/ts/dv are fixed codes)
 *   - Customers:    from Zabbix host groups matching 'CUSTOMER/*'
 *                   fallback to hostname_map.json if no groups found
 *
 * Namespace: Modules\RCA\Actions
 * Zabbix 7.0+ compatible
 */

namespace Modules\RCA\Actions;

use CController;
use CControllerResponseData;
use CWebUser;
use API;

class RcaView extends CController {

	protected function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return CWebUser::isLoggedIn();
	}

	protected function doAction(): void {
		$mapFile = __DIR__ . '/../config/hostname_map.json';
		$map     = [];

		if (file_exists($mapFile)) {
			$decoded = json_decode(file_get_contents($mapFile), true);
			if (is_array($decoded)) {
				$map = $decoded;
			}
		}

		// Environments — always from hostname_map.json (pr/ts/dv are fixed codes)
		$environments = $map['environments'] ?? [
			'pr' => ['name' => 'Production',  'short' => 'PROD', 'color' => 'critical'],
			'ts' => ['name' => 'Test',         'short' => 'TEST', 'color' => 'warning'],
			'dv' => ['name' => 'Development',  'short' => 'DEV',  'color' => 'ok'],
		];

		// Customers — live from Zabbix CUSTOMER/* host groups
		$customers = $this->fetchCustomersFromZabbix($map);

		// Server types — passed to JS for dep map
		$serverTypes = $map['server_types'] ?? [];

		$this->setResponse(new CControllerResponseData([
			'is_super_admin' => (CWebUser::getType() == USER_TYPE_SUPER_ADMIN),
			'environments'   => $environments,
			'customers'      => $customers,
			'server_types'   => $serverTypes,
		]));
	}

	/**
	 * Fetch customers from Zabbix host groups named 'CUSTOMER/...'
	 * Tries to match group names to codes in hostname_map.json.
	 * Falls back to hostname_map.json customers if Zabbix returns nothing.
	 */
	private function fetchCustomersFromZabbix(array $map): array {
		try {
			$groups = API::HostGroup()->get([
				'output'      => ['groupid', 'name'],
				'search'      => ['name' => 'CUSTOMER/'],
				'startSearch' => true,
				'sortfield'   => 'name',
				'sortorder'   => 'ASC',
			]) ?: [];
		} catch (\Exception $e) {
			// API call failed — fall back to map
			return $map['customers'] ?? [];
		}

		// No CUSTOMER/* groups found — fall back to hostname_map
		if (empty($groups)) {
			return $map['customers'] ?? [];
		}

		// Build a lookup: uppercase name/short → code + data
		$codeByName = [];
		foreach ($map['customers'] ?? [] as $code => $cust) {
			$codeByName[strtoupper($cust['name'])]  = ['code' => $code, 'data' => $cust];
			$codeByName[strtoupper($cust['short'])] = ['code' => $code, 'data' => $cust];
		}

		$customers = [];

		foreach ($groups as $group) {
			// Strip "CUSTOMER/" prefix  e.g. "CUSTOMER/GOOGLE INC" → "GOOGLE INC"
			$label = trim(substr($group['name'], strlen('CUSTOMER/')));
			if ($label === '') continue;

			$upper = strtoupper($label);

			if (isset($codeByName[$upper])) {
				// Matched to a known code
				$entry = $codeByName[$upper];
				$customers[$entry['code']] = [
					'name'    => $entry['data']['name'],
					'short'   => $entry['data']['short'],
					'groupid' => $group['groupid'],
					'group'   => $group['name'],
				];
			} else {
				// Unknown — present as-is, key by slugified label
				$key = strtolower(preg_replace('/[^a-z0-9]/i', '_', $label));
				$customers[$key] = [
					'name'       => ucwords(strtolower($label)),
					'short'      => ucwords(strtolower($label)),
					'groupid'    => $group['groupid'],
					'group'      => $group['name'],
					'unresolved' => true,
				];
			}
		}

		return $customers;
	}
}
