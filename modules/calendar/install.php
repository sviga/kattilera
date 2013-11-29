<?php

/**
 * Модуль "Календарь"
 *
 * @author sviga svigani4ok@gmail.com
 * @version 1.0
 *
 */
class calendar_install extends install_modules
{
	/**
	 * Инсталяция базового модуля
	 *
	 * @param string $id_module Идентификатор создаваемого базового модуля
     * @param boolean $reinstall переинсталяция?
	 */
	function install($id_module, $reinstall = false)
	{
	    global $kernel;

	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_calendar` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' `date` date NOT NULL, '
        . ' `available` tinyint(1) unsigned NOT NULL, '
        . ' `header` varchar(255) NOT NULL, '
        . ' `source_url` varchar(255) default NULL, '
        . ' `description` text NOT NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `module_id` (`module_id`), '
        . ' KEY `date` (`date`), '
        . ' KEY `available` (`available`), '
        . ' KEY `header` (`header`), '
        . ' KEY `source_url` (`source_url`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);
	}

	/**
     * Деинсталяция базового модуля
     *
     * @param string $id_module Идентификатор удаляемого базового модуля
     */
	function uninstall($id_module)
	{
		global $kernel;

		$query = 'DROP TABLE `'.PREFIX.'_calendar`';
		$kernel->runSQL($query);
	}

	/**
     * Инсталяция дочернего модуля
     *
     * @param string $id_module Идентификатор вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install_children($id_module, $reinstall = false)
	{

    }

	/**
	 * Деинсталяция дочернего модуля
	 *
     *
     * @param string $id_module ID удоляемого дочернего модуля
     */
	function uninstall_children($id_module)
	{

	}
}

$install = new calendar_install();

$install->set_name('[#calendar_base_name#]');
$install->set_id_modul('calendar');
$install->set_admin_interface(2);

// Публичный метод для отображения календаря
$install->add_public_metod('pub_show_calendar', '[#calendar_pub_show_calendar#]');

// Шаблон ленты новостей
$property = new properties_file();
$property->set_caption('[#calendar_pub_show_calendar_template#]');
$property->set_default('modules/calemdar/templates_user/calendar.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/calendar/templates_user');
$install->add_public_metod_parametrs('pub_show_calendar', $property);

$install->module_copy[0]['name'] = 'calendar_base_name';

$install->module_copy[0]['action'][0]['caption']    = 'Вывести календарь';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_show_calendar';
$install->module_copy[0]['action'][0]['properties']['template'] = 'modules/calendar/templates_user/calendar.html';