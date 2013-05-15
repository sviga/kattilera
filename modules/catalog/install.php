<?php
require_once 'modules/catalog/catalog.commons.class.php';
/**
 * Модуль "Каталог товаров"
 *
 * @author s@nchez sanchezby [at] gmail.com
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0 beta
 *
 */
class catalog_install extends install_modules
{
	/**
	 * Инсталяция базового модуля
	 *
	 * @param string $id_module Идентификатор создаваемого базового модуля
	 * @param boolean $reinstall
	 */

	function install($id_module, $reinstall = false)
	{
	    global $kernel;


       //таблица товарных групп
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_item_groups` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' `name_db` varchar(255) NOT NULL, '
        . ' `name_full` varchar(255) NOT NULL, '
        . ' `front_tpl_md5` varchar(32) default NULL, '
        . ' `back_tpl_md5` varchar(32) default NULL, '
        . ' `template_items_list` varchar(255) default NULL, '
        . ' `template_items_one` varchar(255) default NULL, '
        . ' `defcatids` varchar(255) default NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `module_id` (`module_id`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

        //таблица свойств товаров
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_item_props` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `group_id` int(10) unsigned NOT NULL, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' `name_db` varchar(255) NOT NULL, '
        . ' `name_full` varchar(255) NOT NULL, '
        . ' `type` enum(\'string\',\'enum\',\'number\',\'text\',\'html\',\'file\',\'pict\',\'date\',\'set\') NOT NULL, '
        . ' `showinlist` tinyint(1) unsigned NOT NULL default \'0\', '
        . ' `sorted` tinyint(1) unsigned NOT NULL default \'0\', '
        . ' `ismain` tinyint(1) unsigned NOT NULL default \'0\', '
        . ' `add_param` text, '
        . ' `order` SMALLINT(3) unsigned default 1, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `group_id` (`group_id`), '
        . ' KEY `module_id` (`module_id`(100)), '
        . ' KEY `order` (`order`), '
        . ' KEY `group_main` (`group_id`,`ismain`), '
        . ' KEY `group_sort` (`group_id`,`sorted`), '
        . ' UNIQUE KEY `gmn` (`group_id`,module_id(100),name_db(100)) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

        //таблица видимости свойств в тов. группах админки
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_visible_gprops` ( '
        . ' `group_id` int(5) unsigned NOT NULL, '
        . ' `prop` varchar(255) NOT NULL, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' KEY `module_id` (module_id(100)), '
        . ' KEY `group_module` (group_id, module_id(100)), '
        . ' KEY `prop_module` (prop(100), `module_id`(100)), '
        . ' UNIQUE KEY `uniq` (`group_id`,module_id(100),prop(100)) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
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

		//$query = 'DROP TABLE `'.PREFIX.'_catalog_items`';
		//$kernel->runSQL($query);
		//$query = 'DROP TABLE `'.PREFIX.'_catalog_cats`';
		//$kernel->runSQL($query);

		$query = 'DROP TABLE `'.PREFIX.'_catalog_item_groups`';
		$kernel->runSQL($query);
		$query = 'DROP TABLE `'.PREFIX.'_catalog_item_props`';
		$kernel->runSQL($query);
		//$query = 'DROP TABLE `'.PREFIX.'_catalog_item2cat`';
		//$kernel->runSQL($query);
	}

	/**
     * Инсталяция дочернего модуля
     *
     * @param string $id_module Идентификатор вновь создаваемого дочернего модуля
     * @param boolean $reinstall
     */
	function install_children($id_module, $reinstall = false)
	{
		global $kernel;

       //таблица категорий  для модуля
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_'.$id_module.'_cats` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `parent_id` int(10) unsigned NOT NULL, '
        . ' `order` smallint(3) unsigned default 1, '
        . ' `_hide_from_waysite` tinyint(1) unsigned default 0, '
        . ' `is_default` tinyint(1) unsigned NOT NULL default 0, '
        . ' `name` varchar(255) NOT NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `is_default` (`is_default`), '
        . ' KEY `parent_id_order` (`parent_id`,`order`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

       //таблица товары <-> категории для модуля
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_'.$id_module.'_item2cat` ( '
        . ' `cat_id` int(10) unsigned NOT NULL, '
        . ' `item_id` int(10) unsigned NOT NULL, '
        . ' `order` smallint(3) unsigned default 1, '
        . ' UNIQUE KEY `pri` (`cat_id`,`item_id`), '
        . ' KEY `order` (`order`), '
        . ' KEY `item_id` (`item_id`), '
        . ' KEY `cat_id` (`cat_id`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

        //таблица свойств категорий для модуля
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_'.$id_module.'_cats_props` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `name_db` varchar(255) NOT NULL, '
        . ' `name_full` varchar(255) NOT NULL, '
        . ' `type` enum(\'string\',\'enum\',\'number\',\'text\',\'html\',\'file\',\'pict\') NOT NULL, '
        . ' `add_param` text, '
        . ' PRIMARY KEY  (`id`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1';
        $kernel->runSQL($query);

        //свойство категорий по-умолчанию - название/name
        $query = 'INSERT INTO `'.PREFIX.'_catalog_'.$id_module.'_cats_props` '.
        		 '(`name_db`,`name_full`,`type`) '.
        		 'VALUES ("name", "Название", "string")';
         $kernel->runSQL($query);


	    //таблица товаров для модуля
	    $query = 'CREATE TABLE IF NOT EXISTS `'.PREFIX.'_catalog_'.$id_module.'_items` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `group_id` int(10) unsigned NOT NULL, '
        . ' `ext_id` int(10) unsigned NOT NULL, '
        . ' `available` tinyint(1) unsigned default 0, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `group_id` (`group_id`) '
        . ' ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);

        //таблица внутренних фильтров
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_inner_filters` (
          `id` int(5) unsigned NOT NULL auto_increment,
          `stringid` varchar(255) character set utf8 NOT NULL,
          `name` varchar(255) character set utf8 NOT NULL,
          `query` text character set utf8 NOT NULL,
          `limit` int(5) unsigned default NULL,
          `catids` varchar(255) character set utf8 default NULL,
          `targetpage` varchar(255) character set utf8 default NULL,
          `template` varchar(255) character set utf8 NOT NULL,
          `perpage` int(5) unsigned default NULL,
          `maxpages` int(5) unsigned default NULL,
          `groupid` int(5) unsigned default NULL,
          PRIMARY KEY  (`id`),
          UNIQUE KEY `stringid` (`stringid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);


        //таблица товаров в корзине
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_items` (
          `id` int(5) unsigned NOT NULL auto_increment,
          `orderid` int(5) unsigned NOT NULL,
          `itemid` int(5) unsigned NOT NULL,
          `qty` int(5) unsigned NOT NULL,
          PRIMARY KEY  (`id`),
          UNIQUE KEY `itemid_orderid` (`orderid`,`itemid`),
          KEY `orderid` (`orderid`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);

        //таблица заказов
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_orders` (
          `id` int(5) unsigned NOT NULL auto_increment,
          `sessionid` varchar(32) character set utf8 NOT NULL,
          `lastaccess` datetime default NULL,
          `isprocessed` tinyint(1) unsigned NOT NULL default \'0\',
		  `name` varchar(255) default NULL,
  		  `email` varchar(255) default NULL,
  		  `text` text default NULL,
  		  `userid` int(5) unsigned default \'0\' ,
          `totalprice` decimal(10,2) unsigned DEFAULT NULL,
          PRIMARY KEY  (`id`),
          UNIQUE KEY `sessionid` (`sessionid`),
          KEY `userid` (`userid`),
          KEY `lastaccess` (`lastaccess`)
        ) ENGINE=MyISAM  DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);


        //таблица полей заказа
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_order_fields` (
          `id` int(5) unsigned NOT NULL auto_increment,
          `name_db` varchar(255) character set utf8 NOT NULL,
          `name_full` varchar(255) character set utf8 NOT NULL,
          `type` enum(\'text\',\'number\',\'enum\',\'string\') NOT NULL,
          `order` smallint(3) unsigned NOT NULL,
          `isrequired` tinyint(1) unsigned NOT NULL default \'0\',
          `regexp` varchar(255) character set utf8 default NULL,
          PRIMARY KEY  (`id`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);

        //два поля по-умолчанию для таблицы заказов
        $query = "INSERT INTO `".PREFIX."_catalog_".$id_module."_basket_order_fields` ".
        		  "(`name_db`, `name_full`, `type`, `order`,`isrequired`,`regexp`) VALUES ".
        		 "('name','имя','string','10','0',''), ".
        		 "('email','email','string','20','1','/^[a-z0-9]{1}[a-z0-9_\\.-]*@[a-z0-9\\.-]{1,}\\.[a-z]{2,6}$/i')";
        $kernel->runSQL($query);


        //таблица связей товаров
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_items_links` (
                  `itemid1` int(5) unsigned NOT NULL,
                  `itemid2` int(5) unsigned NOT NULL,
                  UNIQUE KEY `uniq` (`itemid1`,`itemid2`),
                  KEY `item1` (`itemid1`),
                  KEY `item2` (`itemid2`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);

        //таблица переменных модуля
        $query = 'CREATE TABLE `'.PREFIX.'_catalog_'.$id_module.'_variables` (
                  `name_db` varchar(255) NOT NULL,
                  `name_full` varchar(255) NOT NULL,
                  `value` varchar(255) NOT NULL,
                  PRIMARY KEY (`name_db`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8';
        $kernel->runSQL($query);

        $kernel->pub_dir_create_in_files($id_module);
	}

	/**
	 * Деинсталяция дочернего модуля
	 *
     *
     * @param string $id_module ID удаляемого дочернего модуля
     */
	function uninstall_children($id_module)
	{
		global $kernel;

        $groups = CatalogCommons::get_groups($id_module);
        foreach ($groups as $group)
        {
		    $query = 'DROP TABLE `'.PREFIX.'_catalog_items_'.$id_module.'_'.strtolower($group['name_db']).'`';
		    $fname = 'modules/catalog/templates_admin/'.$id_module.'_'.$group['name_db'].'_edit_tpl.html';
		    unlink($fname);
		    $kernel->runSQL($query);
        }

        $query = 'DELETE FROM `'.PREFIX.'_catalog_item_groups` WHERE `module_id`="'.$id_module.'"';
        $kernel->runSQL($query);
        $query = 'DELETE FROM `'.PREFIX.'_catalog_item_props` WHERE `module_id`="'.$id_module.'"';
        $kernel->runSQL($query);
        $query = 'DELETE FROM `'.PREFIX.'_catalog_visible_gprops` WHERE `module_id`="'.$id_module.'"';
        $kernel->runSQL($query);

        //таблица товаров
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_items`';
        $kernel->runSQL($query);

        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_cats`';
        $kernel->runSQL($query);
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_item2cat`';
        $kernel->runSQL($query);
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_cats_props`';
        $kernel->runSQL($query);

        //таблица внутренних фильтров
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_inner_filters`';
        $kernel->runSQL($query);

        //таблицы корзины и заказов
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_order_fields`';
        $kernel->runSQL($query);
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_orders`';
        $kernel->runSQL($query);
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_basket_items`';
        $kernel->runSQL($query);
        //таблица связей товаров
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_items_links`';
        $kernel->runSQL($query);

        //таблица переменных модуля
        $query = 'DROP TABLE `'.PREFIX.'_catalog_'.$id_module.'_variables`';
        $kernel->runSQL($query);
	}
}

$install = new catalog_install();

$install->set_name('[#catalog_base_name#]');
$install->set_id_modul('catalog');
$install->set_admin_interface(2);

//Обозначим свойство, которое будет запрещать при переинсталляции работать с таблицами
$install->set_call_reinstall_mysql(false);

// Лимит элементов на страницу для админа
$property = new properties_string();
$property->set_caption('[#catalog_property_items_per_page_admin#]');
$property->set_default('10');
$property->set_id('catalog_terms_per_page_admin');
$install->add_modul_properties($property);

// Формат отображения поля типа "дата"
$property = new properties_string();
$property->set_caption('[#catalog_property_date_format#]');
$property->set_default('d.m.Y');
$property->set_id('catalog_property_date_format');
$install->add_modul_properties($property);

// Шаблон для отображения названия товара в дороге
$property = new properties_string();
$property->set_caption('[#catalog_property_way_item_tpl#]');
$property->set_default('%name%');
$property->set_id('catalog_property_way_item_tpl');
$property->set_description_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_all_group_props_html'));
$install->add_modul_properties($property);

// Шаблон для отображения названия категории в дороге
$property = new properties_string();
$property->set_caption('[#catalog_property_way_cat_tpl#]');
$property->set_default('%name%');
$property->set_id('catalog_property_way_cay_tpl');
$property->set_description_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_cats_props_html'));
$install->add_modul_properties($property);


// кол-во дней, по которым учитывать статистику при сортировке по популярности
$property = new properties_string();
$property->set_caption('[#catalog_property_popular_days#]');
$property->set_description("[#catalog_property_popular_days_descr#]");
$property->set_id('catalog_property_popular_days');
$install->add_modul_properties($property);


// Публичный метод для вывода списка категорий
$install->add_public_metod('pub_catalog_show_cats', '[#catalog_show_cats#]');

// Шаблон
$property = new properties_file();
$property->set_caption('[#catalog_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'categories.html');
$property->set_id('cat_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);

// Категория начала построения
$property = new properties_string();
$property->set_caption('[#catalog_categories_build_from_cat_label#]');
$property->set_default('0');
$property->set_id('catalog_categories_build_from_cat');
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);
// Уровень начала построения
$property = new properties_select();
$property->set_caption('[#catalog_categories_build_from_depth_label#]');
$property->set_id('catalog_categories_build_from_depth');
$property->set_default('1');
$property->set_data(array ("1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);
// Количество раскрываемых уровней меню
$property = new properties_select();
$property->set_caption('[#catalog_categories_open_levels_show_label#]');
$property->set_id('catalog_categories_open_levels_show');
$property->set_default('1');
$property->set_data(array ("0"=>"0","1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);
// Макс. количество выводимых уровней меню
$property = new properties_select();
$property->set_caption('[#catalog_categories_max_levels_show_label#]');
$property->set_id('catalog_categories_max_levels_show');
$property->set_default('3');
$property->set_data(array ("0"=>"0","1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);


// Станица для вывода списка товаров
$property = new properties_pagesite();
$property->set_caption('[#catalog_items_page#]');
$property->set_default('');
$property->set_id('catalog_items_pagename');
$install->add_public_metod_parametrs('pub_catalog_show_cats', $property);


// Публичный метод для вывода списка товаров
$install->add_public_metod('pub_catalog_show_items', '[#catalog_show_items#]');

// Кол-во товаров на страницу
$property = new properties_string();
$property->set_caption('[#catalog_items_per_page_label#]');
$property->set_default(20);
$property->set_id('items_per_page');
$install->add_public_metod_parametrs('pub_catalog_show_items', $property);

// Выводить ли список категорий, если нет товаров?
$property = new properties_checkbox();
$property->set_caption('[#catalog_show_cats_if_empty_items_label#]');
$property->set_default(true);
$property->set_id('show_cats_if_empty_items');
$install->add_public_metod_parametrs('pub_catalog_show_items', $property);

// Шаблон вывода списка товаров (одна тов. группа)
$property = new properties_file();
$property->set_caption('[#catalog_cats_list_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'list.html');
$property->set_id('item_list_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_items', $property);

// Шаблон вывода списка товаров (разные тов. группы)
$property = new properties_file();
$property->set_caption('[#catalog_item_list_multi_group_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'list.html');
$property->set_id('multi_group_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_items', $property);


// Публичный метод для вывода названия категории или полей товара
$install->add_public_metod('pub_catalog_show_item_name', '[#catalog_show_item_name#]');
// Шаблон из DB-названий полей и текста для вывода инфы о товаре
$property = new properties_string();
$property->set_caption('[#catalog_show_item_fields_label#]');
$property->set_default('%name%');
$property->set_id('catalog_show_item_fields');
$property->set_description_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_all_group_props_html'));
$install->add_public_metod_parametrs('pub_catalog_show_item_name', $property);

$property = new properties_string();
$property->set_caption('[#catalog_show_cat_fields_label#]');
$property->set_default('%name%');
$property->set_id('catalog_show_cat_fields');
$property->set_description_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_cats_props_html'));
$install->add_public_metod_parametrs('pub_catalog_show_item_name', $property);

// Публичный метод для результатов выборки по внутреннему фильтру
$install->add_public_metod('pub_catalog_show_inner_selection_results', '[#catalog_show_inner_selection_results#]');
// Шаблон из DB-названий полей и текста для вывода инфы о товаре
$property = new properties_select();
$property->set_caption('[#catalog_inner_selection_filter_label#]');
$property->set_default('');
$property->set_id('catalog_inner_selection_stringid');
$property->set_data_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_inner_filters_kvarray'));
$install->add_public_metod_parametrs('pub_catalog_show_inner_selection_results', $property);


// Публичный метод для вывода стикера корзины
$install->add_public_metod('pub_catalog_show_basket_label', '[#catalog_show_basket_label#]');
// Шаблон для пустой корзины
$property = new properties_file();
$property->set_caption('[#catalog_basket_label_empty_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'basket_sticker_empty.html');
$property->set_id('basket_label_empty_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_label', $property);
// Шаблон для НЕ пустой корзины
$property = new properties_file();
$property->set_caption('[#catalog_basket_label_notempty_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'basket_sticker.html');
$property->set_id('basket_label_notempty_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_label', $property);


// Публичный метод для вывода связанных товаров
$install->add_public_metod('pub_catalog_show_linked_items', '[#catalog_show_linked_items#]');
// Шаблон для списка
$property = new properties_file();
$property->set_caption('[#catalog_linked_items_tpl#]');
$property->set_default('');
$property->set_id('linked_items_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_linked_items', $property);
// Кол-во товаров на страницу
$property = new properties_string();
$property->set_caption('[#catalog_items_per_page_label#]');
$property->set_default(20);
$property->set_id('items_per_page');
$install->add_public_metod_parametrs('pub_catalog_show_linked_items', $property);


// Публичный метод для вывода содержимого корзины
$install->add_public_metod('pub_catalog_show_basket_items', '[#catalog_show_basket_items#]');
// Шаблон для корзины
$property = new properties_file();
$property->set_caption('[#catalog_basket_items_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'basket_items.html');
$property->set_id('basket_items_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_items', $property);


// Публичный метод для вывода формы оформления заказа
$install->add_public_metod('pub_catalog_show_basket_order_form', '[#catalog_order_show_order_form#]');
// HTML-Шаблон для формы
$property = new properties_file();
$property->set_caption('[#catalog_order_template_order_label#]');
//$property->set_default(CatalogCommons::get_templates_user_prefix().'basket_items.html');
$property->set_default('');
$property->set_id('catalog_order_form_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);
// Шаблон письма менеджеру
$property = new properties_file();
$property->set_caption('[#catalog_order_manager_mail_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'order_manager_mail_tpl.html');
$property->set_id('catalog_order_manager_mail_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);
//Тема письма менеджеру
$property = new properties_string();
$property->set_caption('[#catalog_order_manager_mail_subj#]');
$property->set_default('Заказ на сайте');
$property->set_id('catalog_order_manager_mail_subj');
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);
//Email менеджера
$property = new properties_string();
$property->set_caption('[#catalog_order_manager_email#]');
$property->set_default('');
$property->set_id('catalog_order_manager_email');
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);


// Шаблон письма юзеру
$property = new properties_file();
$property->set_caption('[#catalog_order_user_mail_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'order_user_mail_tpl.html');
$property->set_id('order_user_mail_tpl');
$property->set_mask('htm,html');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);
//Тема письма юзеру
$property = new properties_string();
$property->set_caption('[#catalog_order_user_mail_subj#]');
$property->set_default('Ваш заказ');
$property->set_id('catalog_order_user_mail_subj');
$install->add_public_metod_parametrs('pub_catalog_show_basket_order_form', $property);

// Публичный метод для вывода экспорта
$install->add_public_metod('pub_catalog_show_export', '[#catalog_show_export#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#catalog_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'export_yandex_market.xml');
$property->set_id('export_tpl');
$property->set_mask('htm,html,xml');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_export', $property);
//селект с внутренним фильтром
$property = new properties_select();
$property->set_caption('[#catalog_inner_selection_filter_label#]');
$property->set_default('');
$property->set_id('export_filter');
$property->set_data_user_func("modules/catalog/catalog.commons.class.php", array("CatalogCommons", 'get_inner_filters_kvarray'));
$install->add_public_metod_parametrs('pub_catalog_show_export', $property);


// Публичный метод для сравнения товаров
$install->add_public_metod('pub_catalog_show_compare', '[#catalog_pub_compare#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#catalog_tpl#]');
$property->set_default(CatalogCommons::get_templates_user_prefix().'compare.xml');
$property->set_id('tpl');
$property->set_mask('htm,html,xml');
$property->set_patch(CatalogCommons::get_templates_user_prefix());
$install->add_public_metod_parametrs('pub_catalog_show_compare', $property);
//макс. кол-во товаров в сравнении
$property = new properties_string();
$property->set_caption('[#catalog_compare_max_items#]');
$property->set_default(5);
$property->set_id('max_items');
$install->add_public_metod_parametrs('pub_catalog_show_compare', $property);


$install->module_copy[0]['name'] = 'catalog_modul_base_name1';

$install->module_copy[0]['action'][0]['caption']    = 'Список категорий';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_catalog_show_cats';
$install->module_copy[0]['action'][0]['properties']['cat_tpl']                             = 'categories.html';
$install->module_copy[0]['action'][0]['properties']['catalog_categories_build_from_cat']   = '0';
$install->module_copy[0]['action'][0]['properties']['catalog_categories_build_from_depth'] = '1';
$install->module_copy[0]['action'][0]['properties']['catalog_categories_open_levels_show'] = '1';
$install->module_copy[0]['action'][0]['properties']['catalog_categories_max_levels_show']  = '4';
$install->module_copy[0]['action'][0]['properties']['catalog_items_pagename']              = '';

$install->module_copy[0]['action'][1]['caption']    = 'Список товаров';
$install->module_copy[0]['action'][1]['id_metod']   = 'pub_catalog_show_items';
$install->module_copy[0]['action'][1]['properties']['items_per_page']           = 20;
$install->module_copy[0]['action'][1]['properties']['show_cats_if_empty_items'] = true;
$install->module_copy[0]['action'][1]['properties']['cats_list_tpl']            = 'categories.html';
$install->module_copy[0]['action'][1]['properties']['item_list_tpl']            = 'items_list.html';
//$install->module_copy[0]['action'][0]['properties']['limit']    = '5';


$install->module_copy[0]['action'][2]['caption']    = 'Название элемента';
$install->module_copy[0]['action'][2]['id_metod']   = 'pub_catalog_show_item_name';
$install->module_copy[0]['action'][2]['properties']['catalog_show_item_fields'] = '';

?>