<?php

/**
 * Модуль "Обратная связь"
 *
 * @author Александр Ильин mecommayou@gmail.com
 * @version 1.0 beta
 *
 */
class feedback_install extends install_modules
{
	/**
	 * Инсталяция базового модуля
	 *
	 * @param string $id_module Идентификатор создаваемого базового модуля
     * @param boolean $reinstall переинсталяция?
	 */
	function install($id_module, $reinstall = false)
	{
	}

	/**
     * Деинсталяция базового модуля
     *
     * @param string $id_module Идентификатор удаляемого базового модуля
     */
	function uninstall($id_module)
	{
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

$install = new feedback_install();
$install->set_name('[#feedback_modul_base_name#]');
$install->set_id_modul('feedback');
$install->set_admin_interface(1);

$install->add_public_metod('pub_show_form', '[#feedback_pub_show_form#]');

$p = new properties_file();
$p->set_id('template');
$p->set_caption('[#feedback_property_label_template#]');
$p->set_patch('modules/feedback/templates_user');
$p->set_mask('htm,html');
$p->set_default('modules/feedback/templates_user/default.html');
$install->add_public_metod_parametrs('pub_show_form',$p);

$p = new properties_string();
$p->set_id('email');
$p->set_caption('[#feedback_property_label_email#]');
$p->set_default(isset($_SERVER['SERVER_ADMIN'])?$_SERVER['SERVER_ADMIN']:'');
$install->add_public_metod_parametrs('pub_show_form',$p);

$p = new properties_select();
$p->set_id('type');
$p->set_caption('[#feedback_property_label_type#]');
$p->set_data(array("html"=>"[#feedback_property_label_html#]", "text"=>"[#feedback_property_label_text#]"));
$p->set_default('text');
$install->add_public_metod_parametrs('pub_show_form',$p);

$p = new properties_string();
$p->set_id('name');
$p->set_caption('[#feedback_property_label_name#]');
$p->set_default('');
$install->add_public_metod_parametrs('pub_show_form',$p);

$p = new properties_string();
$p->set_id('theme');
$p->set_caption('[#feedback_property_label_theme#]');
$p->set_default('Обращение через форму обратной связи');
$install->add_public_metod_parametrs('pub_show_form',$p);


$install->module_copy[0]['name'] = 'feedback_modul_base_name';

//$install->module_copy[0]['macros'][0]['caption']    = '[#feedback_pub_show_form#]';
//$install->module_copy[0]['macros'][0]['id_metod']   = 'pub_show_form';

//$install->module_copy[0]['macros'][0]['properties']['template'] = 'modules/feedback/templates_user/default.html';
//$install->module_copy[0]['macros'][0]['properties']['email']    = $_SERVER['SERVER_ADMIN'];