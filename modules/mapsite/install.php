<?php
/**
 * Инсталятор модуля "карта сайта"
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */


class mapsite_install extends install_modules
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

$install = new mapsite_install();

//Основные настройки модуля
$install->set_name('[#mapsite_name_modul_base_name#]');
$install->set_id_modul('mapsite');
$install->set_admin_interface(0);


//Параметры страницы, прописываемые модулем
$param = new properties_select();
$param->set_id("visible");
$param->set_caption("[#module_mapsite_label_visible#]");
$param->set_data(array ("true"=>"[#module_mapsite_visible_var1#]","false"=>"[#module_mapsite_visible_var2#]"));
$install->add_page_properties($param);


//Метод отображения карты сайта (использует один параметр)
$install->add_public_metod('pub_show_mapsite', '[#mapsite_show_map#]');
$param = new properties_pagesite();
$param->set_id("id_page_start");
$param->set_caption("[#module_mapsite_id_page_start#]");
$install->add_public_metod_parametrs('pub_show_mapsite',$param);

$param = new properties_file();
$param->set_id('template');
$param->set_caption('[#module_mapsite_label_propertes1#]');
$param->set_patch("modules/mapsite/templates_user");
$param->set_mask("html,htm");
$param->set_default("modules/mapsite/templates_user/map.html");
$install->add_public_metod_parametrs('pub_show_mapsite',$param);


//Уровни доступа
//$install->add_admin_acces_label('acces_admin','Доступ в административную часть');
//$install->add_admin_acces_label('acces_admin2','Доступ в административную часть 2');


//То, что ставится автоматически при интсляции базового модуля пока оставим так, как есть...
//Теперь можно прописать дочерние модули, которые будут автоматически созданы при
//инсталяции модуля а так же макросы и свойства, каждого из дочерних модулей.
//Свойства модуля
$install->module_copy[0]['name'] = 'mapsite_name_modul_base_name1';

$install->module_copy[0]['action'][0]['caption'] = 'Показать карту';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_show_mapsite';
$install->module_copy[0]['action'][0]['param']['id_page_start'] = 'index';
$install->module_copy[0]['action'][0]['param']['template'] = 'modules/mapsite/templates_user/map.html';
$install->module_copy[0]['propertes_in_page']['index']['visible'] = 'true';
?>
