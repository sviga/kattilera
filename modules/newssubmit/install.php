<?php
/**
 * Вызывается при инсталяции модуля
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */

class newssubmit_install extends install_modules
{
	/**
     * Инсталлятор базового модуля
     *
     * Вызывается при инсталляции базового модуля
     * @param string $id_module ID инсталлируемого базового модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install($id_module, $reinstall = false)
	{
	}


	/**
     * Деинсталлятор базового модуля
     *
     * Вызывается при деинсталляции базового модуля.
     * @param string $id_module ID удаляемого базового модуля
     */
	function uninstall($id_module)
	{
	}


    /**
     * Инсталятор дочернего модуля
     *
     * Методы вызывается при инсталяции каждого дочернего модуля. В качестве параметра
     * передается уникальный идентефикатор вновь создаваемого дочернего объекта.
     * @param string $id_module ID вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install_children($id_module, $reinstall = false)
	{
        require_once( dirname(__FILE__).'/mysql_submit.php');
        $newssub = new mysql_submit(PREFIX.'_'.$id_module);
        $newssub->create_table();
	}

	/**
     * Деинсталлятор дочернего модуля
     *
     * Вызывается при деинсталляции дочеренго модуля.
     * @param string $id_module ID удаляемого базового модуля
     */
	function uninstall_children($id_module)
	{
		require_once( dirname(__FILE__).'/mysql_submit.php');
        $newssub = new mysql_submit(PREFIX.'_'.$id_module);
        $newssub->drop_table();
	}

}


$install = new newssubmit_install();

//Основные настройки модуля
$install->set_name('[#newssubmit_name_modul_base_name#]');
$install->set_id_modul('newssubmit');
$install->set_admin_interface(2);


$param = new properties_pagesite();
$param->set_id('page_submit');
$param->set_caption('[#module_newssubmit_label_propertes1#]');
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('server_email');
$param->set_caption('[#module_newssubmit_label_propertes2#]');
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('server_user');
$param->set_caption('[#module_newssubmit_label_propertes7#]');
$install->add_modul_properties($param);


$param = new properties_string();
$param->set_id('theme_mail_1');
$param->set_caption('[#module_newssubmit_label_propertes3#]');
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('theme_mail_2');
$param->set_caption('[#module_newssubmit_label_propertes4#]');
$install->add_modul_properties($param);

$param = new properties_checkbox();
$param->set_id('is_test');
$param->set_caption('[#module_newssubmit_label_propertes5#]');
$install->add_modul_properties($param);

$param = new properties_file();
$param->set_id("template_send");
$param->set_caption("[#module_newssubmit_label_propertes6#]");
$param->set_mask("html,htm");
$param->set_patch("modules/newssubmit/templates_user/");
$param->set_default("modules/newssubmit/templates_user/submit_letter.html");
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('prefix_unic');
$param->set_caption('[#module_newssubmit_label_propertes_prefix#]');
$param->set_default("mysite");
$install->add_modul_properties($param);


//для крона2 - ограничение по кол-ву писем за раз
$param = new properties_string();
$param->set_id('max_letters');
$param->set_caption('[#module_newssubmit_max_letters_per_run#]');
$param->set_default("100");
$install->add_modul_properties($param);


//========================================================================================
//Опишем публичные методы со всеми возможными параметрами
//========================================================================================

//Метод отображения меню, отображаему меню зависит от текущей страницы
$install->add_public_metod('pub_formsubmit_show', '[#newssubmit_pub_formsubmit_show#]');

$param = new properties_file();
$param->set_id("template");
$param->set_caption("[#module_newssubmit_metod1_param1#]");
$param->set_mask("html,htm");
$param->set_patch("modules/newssubmit/templates_user/");
$param->set_default("form_subscripe.html");
$install->add_public_metod_parametrs('pub_formsubmit_show', $param);

$param->set_id("template2");
$param->set_caption("[#module_newssubmit_metod1_param2#]");
$param->set_mask("html,htm");
$param->set_patch("modules/newssubmit/templates_user/");
$param->set_default("form_control.html");
$install->add_public_metod_parametrs('pub_formsubmit_show', $param);



$install->module_copy[0]['name']                            = 'newssubmit_name_modul_base_name1';
$install->module_copy[0]['action'][0]['caption']            = 'Вывести форму подписки';
$install->module_copy[0]['action'][0]['id_metod']           = 'pub_formsubmit_show';
$install->module_copy[0]['action'][0]['param']['template']  = 'modules/newssubmit/templates_user/form_subscripe.html';
$install->module_copy[0]['action'][0]['param']['template2'] = 'modules/newssubmit/templates_user/form_control.html';

?>
