<?php

/**
 * Модуль "Новости"
 *
 * @author Александр Ильин mecommayou@gmail.com
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0 beta
 *
 */
class newsi_install extends install_modules
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

	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_newsi` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' `date` date NOT NULL, '
        . ' `time` time NOT NULL, '
        . ' `available` tinyint(1) unsigned NOT NULL, '
        . ' `lenta` tinyint(1) unsigned NOT NULL, '
        . ' `delivery` tinyint(1) unsigned NOT NULL, '
        . ' `rss` tinyint(1) unsigned NOT NULL, '
        . ' `header` varchar(255) NOT NULL, '
        . ' `description_short` text NOT NULL, '
        . ' `description_full` text NOT NULL, '
        . ' `author` varchar(255) NOT NULL, '
        . ' `source_name` varchar(255) default NULL, '
        . ' `source_url` varchar(255) default NULL, '
        . ' `image` varchar(255) NOT NULL, '
        . ' `post_date` DATETIME default NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `module_id` (`module_id`), '
        . ' KEY `date` (`date`), '
        . ' KEY `time` (`time`), '
        . ' KEY `available` (`available`), '
        . ' KEY `lenta` (`lenta`), '
        . ' KEY `delivery` (`delivery`), '
        . ' KEY `rss` (`rss`), '
        . ' KEY `header` (`header`), '
        . ' KEY `author` (`author`), '
        . ' KEY `source_name` (`source_name`), '
        . ' KEY `source_url` (`source_url`), '
        . ' KEY `post_date` (`post_date`), '
        . ' KEY `image` (`image`) '
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

		$query = 'DROP TABLE `'.PREFIX.'_newsi`';
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
		global $kernel;
		$kernel->pub_dir_create_in_images($id_module);
		$kernel->pub_dir_create_in_images($id_module.'/tn');
		$kernel->pub_dir_create_in_images($id_module.'/source');
	}

	/**
	 * Деинсталяция дочернего модуля
	 *
     *
     * @param string $id_module ID удоляемого дочернего модуля
     */
	function uninstall_children($id_module)
	{
		global $kernel;

		$kernel->pub_dir_recurs_delete('content/images/'.$id_module);
	}
}

$install = new newsi_install();

$install->set_name('[#news_base_name#]');
$install->set_id_modul('newsi');
$install->set_admin_interface(2);

// Ширина большой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#news_property_img_big_width#]');
$property->set_default('300');
$property->set_id('img_big_width');
$install->add_modul_properties($property);

// Высота большой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#news_property_img_big_height#]');
$property->set_default('300');
$property->set_id('img_big_height');
$install->add_modul_properties($property);

// Ширина маленькой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#news_property_img_small_width#]');
$property->set_default('100');
$property->set_id('img_small_width');
$install->add_modul_properties($property);

// Высота маленькой картинки в пикселях
$property = new properties_string();
$property->set_caption('[#news_property_img_small_height#]');
$property->set_default('100');
$property->set_id('img_small_height');
$install->add_modul_properties($property);

// Автоматически добавлять в рассылку
$property = new properties_checkbox();
$property->set_caption('[#news_property_deliver#]');
$property->set_default('true');
$property->set_id('deliver');
$install->add_modul_properties($property);

// Автоматически добавлять в ленту
$property = new properties_checkbox();
$property->set_caption('[#news_property_lenta#]');
$property->set_default('true');
$property->set_id('lenta');
$install->add_modul_properties($property);

// Автоматически добавлять в rss
$property = new properties_checkbox();
$property->set_caption('[#news_property_rss#]');
$property->set_default('true');
$property->set_id('rss');
$install->add_modul_properties($property);

// Лимит новостей на страницу
$property = new properties_string();
$property->set_caption('[#news_property_news_per_page#]');
$property->set_default('10');
$property->set_id('news_per_page');
$install->add_modul_properties($property);

// Станица для просмотра полного текста новости
$property = new properties_pagesite();
$property->set_caption('[#news_property_page_for_lenta#]');
$property->set_default('');
$property->set_id('page_for_lenta');
$install->add_modul_properties($property);


// Публичный метод для отображения ленты новостей
$install->add_public_metod('pub_show_lenta', '[#news_pub_show_lenta#]');

// Шаблон ленты новостей
$property = new properties_file();
$property->set_caption('[#news_pub_show_lenta_template#]');
$property->set_default('modules/newsi/templates_user/lenta.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/newsi/templates_user');
$install->add_public_metod_parametrs('pub_show_lenta', $property);

// Количество новостей в ленте
$property = new properties_string();
$property->set_caption('[#news_pub_show_lenta_limit#]');
$property->set_default('10');
$property->set_id('limit');
$install->add_public_metod_parametrs('pub_show_lenta', $property);

// Тип отображения
$property = new properties_select();
$property->set_caption('[#news_pub_show_lenta_type#]');
$property->set_data(array(
    'default'   => '[#news_pub_show_lenta_type_default#]',
    'past'      => '[#news_pub_show_lenta_type_past#]',
    'future'    => '[#news_pub_show_lenta_type_future#]'
));
$property->set_default('default');
$property->set_id('type');
$install->add_public_metod_parametrs('pub_show_lenta', $property);

// Станица для просмотра полного текста новости
$property = new properties_pagesite();
$property->set_caption('[#news_pub_show_lenta_page#]');
$property->set_default('index');
$property->set_id('page');
$install->add_public_metod_parametrs('pub_show_lenta', $property);

// ID модулей, для которых формируется данная новостаня лента
$property = new properties_string();
$property->set_caption('[#news_pub_show_lenta_id_modules#]');
$property->set_default('');
$property->set_id('id_modules');
$install->add_public_metod_parametrs('pub_show_lenta', $property);

// Публичный метод для отображения архива новостей
$install->add_public_metod('pub_show_archive', '[#news_pub_show_archive#]');

// Шаблон архива
$property = new properties_file();
$property->set_caption('[#news_pub_show_archive_template#]');
$property->set_default('modules/newsi/templates_user/arhive.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/newsi/templates_user');
$install->add_public_metod_parametrs('pub_show_archive', $property);

// Количество новостей на страницу
$property = new properties_string();
$property->set_caption('[#news_pub_show_archive_limit#]');
$property->set_default('10');
$property->set_id('limit');
$install->add_public_metod_parametrs('pub_show_archive', $property);

// Тип отоба
$property = new properties_select();
$property->set_caption('[#news_pub_show_archive_type#]');
$property->set_data(array(
    'default'   => '[#news_pub_show_archive_default#]',
    'past'      => '[#news_pub_show_archive_past#]',
    'future'    => '[#news_pub_show_archive_future#]'
));
$property->set_default('default');
$property->set_id('type');
$install->add_public_metod_parametrs('pub_show_archive', $property);

//Тип вывода блока для постраничной навигации в архиве
$property = new properties_select();
$property->set_caption('[#news_pub_pages_type#]');
$property->set_data(array(
    'block' => '[#news_pub_pages_get_block#]',
    'float' => '[#news_pub_pages_get_float#]'
));
$property->set_default('block');
$property->set_id('pages_type');
$install->add_public_metod_parametrs('pub_show_archive', $property);

// Количество выводимых страниц
$property = new properties_string();
$property->set_caption('[#news_property_pages_count#]');
$property->set_default('5');
$property->set_id('pages_count');
$install->add_public_metod_parametrs('pub_show_archive', $property);


$install->add_public_metod('pub_show_selection', '[#news_pub_show_selection#]');
$property = new properties_file();
$property->set_caption('[#news_pub_show_selection_template#]');
$property->set_default('modules/newsi/templates_user/arhive.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/newsi/templates_user');
$install->add_public_metod_parametrs('pub_show_selection', $property);


$install->module_copy[0]['name'] = 'newsi_modul_base_name1';

$install->module_copy[0]['action'][0]['caption']    = 'Вывести ленту';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_show_lenta';
$install->module_copy[0]['action'][0]['properties']['template'] = 'modules/newsi/templates_user/lenta.html';
$install->module_copy[0]['action'][0]['properties']['limit']    = '5';
$install->module_copy[0]['action'][0]['properties']['type']     = 'past';
$install->module_copy[0]['action'][0]['properties']['page']     = 'index';

$install->module_copy[0]['action'][1]['caption']    = 'Вывести архив';
$install->module_copy[0]['action'][1]['id_metod']   = 'pub_show_archive';
$install->module_copy[0]['action'][1]['properties']['template']    = 'modules/newsi/templates_user/arhive.html';
$install->module_copy[0]['action'][1]['properties']['limit']       = '20';
$install->module_copy[0]['action'][1]['properties']['type']        = 'past';
$install->module_copy[0]['action'][1]['properties']['pages_type']  = 'block';
$install->module_copy[0]['action'][1]['properties']['pages_count'] = '5';

