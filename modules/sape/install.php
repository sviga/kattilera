<?php
/**
 * Модуль по продаже ссылок в SAPE
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class sape_install extends install_modules
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

$install = new sape_install();
$install->set_name('[#sape_modul_base_name#]');
$install->set_id_modul('sape');

$install->set_admin_interface(0);


//Код идентификаци пользователя в Sape
$property = new properties_string();
$property->set_caption('[#sape_modul_user_code#]');
$property->set_default('');
$property->set_id('user_code_sape');
$install->add_modul_properties($property);

$install->add_public_metod('pub_link_show', '[#sape_pub_link_show#]');

//приоритетность
$p = new properties_select();
$p->set_id('num_for_sort');
$p->set_caption('[#sape_pub_link_show_param_num_for_sort#]');
$p->set_data(array("4"=>"[#sape_modul_metod_1_param_value_1#]","3"=>"[#sape_modul_metod_1_param_value_2#]","2"=>"[#sape_modul_metod_1_param_value_3#]","1"=>"[#sape_modul_metod_1_param_value_4#]","0"=>"[#sape_modul_metod_1_param_value_5#]"));
$p->set_default('1');
$install->add_public_metod_parametrs('pub_link_show',$p);

// кол-во ссылок
$p = new properties_string();
$p->set_id('count_link');
$p->set_caption('[#sape_pub_link_show_param_count_link#]');
$p->set_default('2');
$install->add_public_metod_parametrs('pub_link_show',$p);

// Шаблон
$property = new properties_file();
$property->set_caption('[#sape_tpl#]');
$property->set_default('template.html');
$property->set_id('template_file');
$property->set_mask('htm,html');
$property->set_patch('modules/sape/templates_user/');
$install->add_public_metod_parametrs('pub_link_show', $property);

$install->module_copy[0]['name'] = 'sape_modul_base_name1';

$install->module_copy[0]['action'][0]['caption']    = 'Вывод трех ссылок';
$install->module_copy[0]['action'][0]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][0]['param']['num_for_sort'] = "4";
$install->module_copy[0]['action'][0]['param']['count_link'] = "3";
$install->module_copy[0]['action'][0]['param']['template_file'] = "modules/sape/templates_user/template.html";

$install->module_copy[0]['action'][1]['caption']    = 'Вывод двух ссылок';
$install->module_copy[0]['action'][1]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][1]['param']['num_for_sort'] = "3";
$install->module_copy[0]['action'][1]['param']['count_link'] = "2";
$install->module_copy[0]['action'][1]['param']['template_file'] = "modules/sape/templates_user/template.html";

$install->module_copy[0]['action'][2]['caption']    = 'Вывод оставшихся ссылок';
$install->module_copy[0]['action'][2]['id_metod']   = 'pub_link_show';
$install->module_copy[0]['action'][2]['param']['num_for_sort'] = "0";
$install->module_copy[0]['action'][2]['param']['count_link'] = "0";
$install->module_copy[0]['action'][2]['param']['template_file'] = "modules/sape/templates_user/template.html";
?>