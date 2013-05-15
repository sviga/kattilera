<?php
/**
 * Модуль по продаже ссылок в Linkfeed
 * @copyright ArtProm (с) 2001-2011
 * @version 1.0
 */

class linkfeed_install extends install_modules
{

	function install($id_module, $reinstall = false)
	{
	}

	function uninstall($id_module)
	{
	}


	function install_children($id_module, $reinstall = false)
	{
	}


	function uninstall_children($id_module)
	{
	}
}

$install = new linkfeed_install();
$install->set_name('[#linkfeed_modul_base_name#]');
$install->set_id_modul('linkfeed');
$install->set_admin_interface(0);


//Код идентификаци пользователя в Linkfeed
$property = new properties_string();
$property->set_caption('[#linkfeed_modul_user_code#]');
$property->set_default('');
$property->set_id('user_code_linkfeed');
$install->add_modul_properties($property);

$install->add_public_metod('pub_link_show', '[#linkfeed_pub_link_show#]');

//приоритетность
$p = new properties_select();
$p->set_id('num_for_sort');
$p->set_caption('[#linkfeed_pub_link_show_param_num_for_sort#]');
$p->set_data(array("4"=>"[#linkfeed_modul_metod_1_param_value_1#]","3"=>"[#linkfeed_modul_metod_1_param_value_2#]","2"=>"[#linkfeed_modul_metod_1_param_value_3#]","1"=>"[#linkfeed_modul_metod_1_param_value_4#]","0"=>"[#linkfeed_modul_metod_1_param_value_5#]"));
$p->set_default('1');
$install->add_public_metod_parametrs('pub_link_show',$p);

// кол-во ссылок
$p = new properties_string();
$p->set_id('count_link');
$p->set_caption('[#linkfeed_pub_link_show_param_count_link#]');
$p->set_default('2');
$install->add_public_metod_parametrs('pub_link_show',$p);

// Шаблон
$property = new properties_file();
$property->set_caption('[#linkfeed_tpl#]');
$property->set_default('modules/linkfeed/templates_user/template.html');
$property->set_id('template_file');
$property->set_mask('htm,html');
$property->set_patch('modules/linkfeed/templates_user/');
$install->add_public_metod_parametrs('pub_link_show', $property);

$install->module_copy[0]['name'] = 'linkfeed_modul_base_name1';

$install->module_copy[0]['action'][0]['caption']    = 'Вывод трех ссылок';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][0]['param']['num_for_sort'] = "4";
$install->module_copy[0]['action'][0]['param']['count_link'] = "3";

$install->module_copy[0]['action'][1]['caption']    = 'Вывод двух ссылок';
$install->module_copy[0]['action'][1]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][1]['param']['num_for_sort'] = "3";
$install->module_copy[0]['action'][1]['param']['count_link'] = "2";

$install->module_copy[0]['action'][2]['caption']    = 'Вывод оставшихся ссылок';
$install->module_copy[0]['action'][2]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][2]['param']['num_for_sort'] = "0";
$install->module_copy[0]['action'][2]['param']['count_link'] = "0";
?>