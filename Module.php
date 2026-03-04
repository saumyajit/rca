<?php
/**
 * RCA Module — Root Cause Analysis View
 * Zabbix 7.0+ compatible
 * Namespace: Modules\RCA
 */

namespace Modules\RCA;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {

	public function init(): void {
		$menu = APP::Component()->get('menu.main')
			->findOrAdd(_('Monitoring'))
				->getSubmenu();

		$menu->insertAfter(_('Problems'),
			(new CMenuItem(_('RCA Page')))->setAction('RcaView')
		);
	}
}
