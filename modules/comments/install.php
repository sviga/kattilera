<?php

/**
 * Модуль "Комментарии и отзывы"
 *
 * @copyright ArtProm (с) 2001-2012
 * @version 2.0
 *
 */
class comments_install extends install_modules
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

	    $query = 'CREATE TABLE IF NOT EXISTS `'.$kernel->pub_prefix_get().'_comments` ( '
        . ' `id` int(10) unsigned NOT NULL auto_increment, '
        . ' `module_id` varchar(255) NOT NULL, '
        . ' `page_id` varchar(255), '
        . ' `page_sub_id` varchar(255), '
        . ' `date` date NOT NULL, '
        . ' `time` time NOT NULL, '
        . ' `available` tinyint(1) unsigned default 1, '
        . ' `txt` text NOT NULL, '
        . ' `author` varchar(255) NOT NULL, '
        . ' PRIMARY KEY  (`id`), '
        . ' KEY `module_id` (`module_id`), '
        . ' KEY `date` (`date`), '
        . ' KEY `time` (`time`), '
        . ' KEY `available` (`available`), '
        . ' KEY `author` (`author`) '
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
		$query = 'DROP TABLE IF EXISTS `'.$kernel->pub_prefix_get().'_comments`';
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
        //таблица отзывов
        $query="CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_reviews` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `name` varchar(255) NOT NULL,
          `pageid` text NOT NULL,
          `rate` tinyint(1) unsigned DEFAULT NULL COMMENT 'оценка 1..5',
          `pros` text COMMENT 'достоинства',
          `cons` text COMMENT 'недостатки',
          `comment` text COMMENT 'комментарий',
          `when` datetime NOT NULL COMMENT 'дата-время',
          `available` tinyint(1) unsigned NOT NULL DEFAULT '0' COMMENT 'показываем на сайте?',
          PRIMARY KEY (`id`)
          ,KEY `pageid_when` (`pageid`(200),`when`,`available`)
          ,KEY `available_pageid_rate` (`pageid`(200),`rate`,`available`)
          ,KEY `when_available` (`when`,`available`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        $kernel->runSQL($query);
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
        $query = "DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_reviews`";
        $kernel->runSQL($query);
	}
}

$install = new comments_install();

$install->set_name('[#comments_base_name#]');
$install->set_id_modul('comments');
$install->set_admin_interface(2);

// Премодерация
$property = new properties_checkbox();
$property->set_caption('[#comments_property_premod#]');
$property->set_default('true');
$property->set_id('premod');
$install->add_modul_properties($property);

// Показывать каптчу?
$property = new properties_checkbox();
$property->set_caption('[#comments_property_showcaptcha#]');
$property->set_default('true');
$property->set_id('showcaptcha');
$install->add_modul_properties($property);

// Лимит комментов на страницу для админа
$property = new properties_string();
$property->set_caption('[#comments_property_comments_per_page_admin#]');
$property->set_default('10');
$property->set_id('comments_per_page_admin');
$install->add_modul_properties($property);

// Email админа
$property = new properties_string();
$property->set_caption('[#comments_property_admin_email#]');
$property->set_default('');
$property->set_id('comments_admin_email');
$install->add_modul_properties($property);


// Публичный метод для отображения статистики по отзывам
$install->add_public_metod('pub_show_reviews_stat', '[#comments_pub_show_reviews_stat#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#comments_pub_show_comments_template#]');
$property->set_default('modules/comments/templates_user/reviews_stat.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/comments/templates_user');
$install->add_public_metod_parametrs('pub_show_reviews_stat', $property);
// http-параметры, по которым работаем
$property = new properties_string();
$property->set_caption('[#comments_pub_show_comments_httpparams#]');
$property->set_default('');
$property->set_id('httpparams');
$install->add_public_metod_parametrs('pub_show_reviews_stat', $property);

// Публичный метод для отображения отзывов и формы
$install->add_public_metod('pub_show_reviews', '[#comments_pub_show_reviews#]');
// Шаблон отзывов с формой
$property = new properties_file();
$property->set_caption('[#comments_pub_show_comments_template#]');
$property->set_default('modules/comments/templates_user/reviews.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/comments/templates_user');
$install->add_public_metod_parametrs('pub_show_reviews', $property);
// Количество комментариев на страницу
$property = new properties_string();
$property->set_caption('[#comments_pub_show_comments_limit#]');
$property->set_default('10');
$property->set_id('limit');
$install->add_public_metod_parametrs('pub_show_reviews', $property);
// Тип сортировки - новые сверху или снизу
$property = new properties_select();
$property->set_caption('[#comments_pub_show_comments_type#]');
$property->set_data(array(
    'default'   => '[#comments_pub_show_comments_type_default#]',
    'new_at_top'      => '[#comments_pub_show_comments_type_new_at_top#]',
    'new_at_bottom'    => '[#comments_pub_show_comments_type_new_at_bottom#]'
));
$property->set_default('default');
$property->set_id('type');
$install->add_public_metod_parametrs('pub_show_reviews', $property);
// http-параметры, по которым работаем
$property = new properties_string();
$property->set_caption('[#comments_pub_show_comments_httpparams#]');
$property->set_default('');
$property->set_id('httpparams');
$install->add_public_metod_parametrs('pub_show_reviews', $property);



// Публичный метод для отображения комментариев и формы
$install->add_public_metod('pub_show_comments', '[#comments_pub_show_comments#]');
// Шаблон комментариев с формой
$property = new properties_file();
$property->set_caption('[#comments_pub_show_comments_template#]');
$property->set_default('modules/comments/templates_user/comments.html');
$property->set_id('template');
$property->set_mask('htm,html');
$property->set_patch('modules/comments/templates_user');
$install->add_public_metod_parametrs('pub_show_comments', $property);

// Количество комментариев на страницу
$property = new properties_string();
$property->set_caption('[#comments_pub_show_comments_limit#]');
$property->set_default('10');
$property->set_id('limit');
$install->add_public_metod_parametrs('pub_show_comments', $property);
// Тип сортировки - новые сверху или снизу
$property = new properties_select();
$property->set_caption('[#comments_pub_show_comments_type#]');
$property->set_data(array(
    'default'   => '[#comments_pub_show_comments_type_default#]',
    'new_at_top'      => '[#comments_pub_show_comments_type_new_at_top#]',
    'new_at_bottom'    => '[#comments_pub_show_comments_type_new_at_bottom#]'
));
$property->set_default('default');
$property->set_id('type');
$install->add_public_metod_parametrs('pub_show_comments', $property);
// http-параметры, по которым работаем
$property = new properties_string();
$property->set_caption('[#comments_pub_show_comments_httpparams#]');
$property->set_default('');
$property->set_id('httpparams');
$install->add_public_metod_parametrs('pub_show_comments', $property);


$property = new properties_select();
$property->set_caption('Выводить блок комментариев на родительской странице?');
$property->set_data(array(
    'no'   => 'Нет, не выводить',
    'yes'  => 'Да, выводить'
));
$property->set_default('no');
$property->set_id('no_parent');
$install->add_public_metod_parametrs('pub_show_comments', $property);

$install->module_copy[0]['name'] = 'comments_modul_base_name1';
$install->module_copy[0]['action'][0]['caption']    = 'Комментарии по-умолчанию';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_show_comments';
$install->module_copy[0]['action'][0]['properties']['template'] = 'modules/comments/templates_user/comments.html';
$install->module_copy[0]['action'][0]['properties']['limit']    = '5';
$install->module_copy[0]['action'][0]['properties']['type']     = 'new_at_top';
$install->module_copy[0]['action'][0]['properties']['page']     = 'index';
$install->module_copy[0]['action'][0]['properties']['httpparams']  = '';
$install->module_copy[0]['action'][0]['properties']['no_parent']  = 'no';
