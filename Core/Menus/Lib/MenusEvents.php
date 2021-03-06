<?php
	/**
	 * Menu plugin events.
	 *
	 * The events for the menu plugin for setting up cache and the general
	 * configuration of the plugin.
	 *

	 *
	 * @filesource
	 * @copyright Copyright (c) 2010 Carl Sutton ( dogmatic69 )
	 * @link http://www.infinitas-cms.org
	 * @package Infinitas.Menus.Lib
	 * @license http://www.opensource.org/licenses/mit-license.php The MIT License
	 * @since 0.8a
	 *
	 * @author Carl Sutton <dogmatic69@infinitas-cms.org>
	 */

	class MenusEvents extends AppEvents {
		public function onSetupCache(Event $Event) {
			return array(
				'name' => 'menus',
				'config' => array(
					'prefix' => 'core.menus.'
				)
			);
		}

		public function onAdminMenu(Event $Event) {
			$menu['main'] = array(
				'Dashboard' => array('plugin' => 'management', 'controller' => 'management', 'action' => 'site'),
				'Menus' => array('controller' => false, 'action' => false),
				'Menu Items' => array('controller' => 'menu_items', 'action' => 'index')
			);

			return $menu;
		}

		public function onRequireHelpersToLoad(Event $Event) {
			return array(
				'Menus.Menu'
			);
		}
		
	}