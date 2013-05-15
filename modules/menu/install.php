<?php
/**
 * Инсталятор модуля "Меню"
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0
 */

class menu_install extends install_modules
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

//Непосредственно параметры интсталируемого модуля

$install = new menu_install();

//Основные настройки модуля
$install->set_name('[#menu_name_modul_base_name#]');
$install->set_id_modul('menu');
$install->set_admin_interface(0);


//Параметр модуля к странице, управляющей видимостью страницы в построенном меню
$param = new properties_select();
$param->set_id("visible");
$param->set_caption("[#module_menu_label_visible#]");
$param->set_data(array ("true"=>"[#module_menu_visible_var1#]","false"=>"[#module_menu_visible_var2#]"));
$install->add_page_properties($param);



//========================================================================================
//Опишем публичные методы со всеми возможными параметрами
//========================================================================================

//Метод отображения меню, отображаему меню зависит от текущей страницы
$install->add_public_metod('pub_show_menu', '[#menu_show_menu#]');

$param = new properties_pagesite();
$param->set_id("id_page_start");
$param->set_caption("[#module_menu_id_page_start1#]");
$install->add_public_metod_parametrs('pub_show_menu',$param);

$param = new properties_select();
$param->set_id("count_level_start");
$param->set_caption("[#module_menu_count_level_start#]");
$param->set_data(array ("1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_show_menu',$param);

$param = new properties_select();
$param->set_id("count_level_show");
$param->set_caption("[#module_menu_count_level_show#]");
$param->set_data(array ("1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_show_menu',$param);

$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#module_menu_label_propertes1#]');
$param->set_patch("modules/menu/templates_user");
$param->set_mask("html,htm");
$install->add_public_metod_parametrs('pub_show_menu',$param);

//Метод отображения меню, аналогичен предыдущему, с той лишь разницей,
//что меню выводиться внезависиости от того, на какой странице находиться пользователь
$install->add_public_metod('pub_show_menu_static', '[#menu_show_menu_static#]');

$param = new properties_pagesite();
$param->set_id("id_page_start");
$param->set_caption("[#module_menu_id_page_start1#]");
$install->add_public_metod_parametrs('pub_show_menu_static',$param);

$param = new properties_select();
$param->set_id("count_level_show");
$param->set_caption("[#module_menu_count_level_show#]");
$param->set_data(array ("1"=>"1","2"=>"2","3"=>"3","4"=>"4","5"=>"5","6"=>"6"));
$install->add_public_metod_parametrs('pub_show_menu_static',$param);

$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#module_menu_label_propertes1#]');
$param->set_patch("modules/menu/templates_user");
$param->set_mask("html,htm");
$install->add_public_metod_parametrs('pub_show_menu_static',$param);


//описание объектов, которые будут созданы в момент инсталяции базового модуля
$install->module_copy[0]['name']                                    = 'menu_name_modul_base_name1';

//Создадим предустановленное действие для вывода меню
$install->module_copy[0]['action'][0]['caption']                    = 'Вывести линейное меню';
$install->module_copy[0]['action'][0]['id_metod']                   = 'pub_show_menu_static';
$install->module_copy[0]['action'][0]['param']['id_page_start']     = 'index';
$install->module_copy[0]['action'][0]['param']['count_level_start'] = '1';
$install->module_copy[0]['action'][0]['param']['count_level_show']  = '1';
$install->module_copy[0]['action'][0]['param']['template']          = 'modules/menu/templates_user/menu.html';
//Предуставновленное второе дейсвтие
$install->module_copy[0]['action'][1]['caption']                    = 'Вывести древовидное меню';
$install->module_copy[0]['action'][1]['id_metod']                   = 'pub_show_menu';
$install->module_copy[0]['action'][1]['param']['id_page_start']     = 'index';
$install->module_copy[0]['action'][1]['param']['count_level_start'] = '2';
$install->module_copy[0]['action'][1]['param']['count_level_show']  = '3';
$install->module_copy[0]['action'][1]['param']['template']          = 'modules/menu/templates_user/extended.html';

?>
