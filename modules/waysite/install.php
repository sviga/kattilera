<?php
/**
 * Инсталятор модуля "Дороги"
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0
 */

class way_install extends install_modules
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
	}

	/**
     * Деинсталлятор дочернего модуля
     *
     * Вызывается при деинсталляции дочеренго модуля.
     * @param string $id_module ID удаляемого базового модуля
     */
	function uninstall_children($id_module)
	{
	}


}

$install = new way_install();

//Основные настройки модуля
$install->set_name('[#waysite_name_modul_base_name#]');
$install->set_id_modul('waysite');
$install->set_admin_interface(0);

//Параметры страницы, прописываемые модулем
$param = new properties_select();
$param->set_id("visible");
$param->set_caption("[#module_waysite_label_visible#]");
$param->set_data(array ("true"=>"[#module_waysite_visible_var1#]","false"=>"[#module_waysite_visible_var2#]"));
$install->add_page_properties($param);


//Метод отображения дороги
$install->add_public_metod('pub_show_waysite', '[#waysite_pub_show_waysite#]');
$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#module_waysite_label_propertes1#]');
$param->set_patch("modules/waysite/templates_user");
$param->set_mask("html,htm");
$param->set_default('modules/waysite/templates_user/way.html');

$install->add_public_metod_parametrs('pub_show_waysite',$param);
//Метод отображения из дороги заданного уровня
$install->add_public_metod('pub_show_caption_static', '[#waysite_show_caption_static#]');
$param = new properties_string();
$param->set_id("level_num");
$param->set_caption("[#module_waysite_level_num#]");
$install->add_public_metod_parametrs('pub_show_caption_static',$param);


//Значения, создаваемые при инсталяции модуля
$install->module_copy[0]['name'] = 'waysite_name_modul_base_name1';
$install->module_copy[0]['action'][0]['caption']  = 'Вывести дорогу';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_show_waysite';
$install->module_copy[0]['action'][0]['param']['template'] = 'modules/waysite/templates_user/way.html';

$install->module_copy[0]['action'][1]['caption'] = 'Вывести страницу 2-ого уровня';
$install->module_copy[0]['action'][1]['id_metod'] = 'pub_show_caption_static';
$install->module_copy[0]['action'][1]['param']['level_num'] = '2';

$install->module_copy[0]['properties_in_page']['index']['visible'] = 'true';

?>