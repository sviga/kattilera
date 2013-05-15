<?php
/**
 * Вызывается при инсталяции модуля "авторизации"
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0
 */

//Осонвные параметры модуля
class auth_install extends install_modules
{
	/**
     * Вызывается при инстялции базвоового модуля
     */
	function install($id_module, $reinstall = false)
	{
	}


	/**
     * Методы вызывается при деинсталяции базового модуля. ID базоовго модуля
     * точно известно и определется самим модулем, но он (ID) так же передается в
     * качестве параметра. Здесь необходимо производить удаление каталогов, файлов и таблиц используемых
     * базовым модулем и создаваемых в install
     * @param string $id_module ID удаляемого базового модуля
     */

	function uninstall($id_module)
	{
	}


	/**
     * Методы вызывается при инсталяции каждого дочернего модуля, здесь необходимо
     * создавать таблицы каталоги, или файлы используемые дочерним модулем. Уникальность создаваемых
     * объектов обеспечивается с помощью передвавемого ID модуля
     *
     * @param string $id_module ID вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install_children($id_module, $reinstall = false)
	{
        global $kernel;
        $kernel->pub_dir_create_in_images('auth');
        $kernel->pub_dir_create_in_images('auth/tn');
        $kernel->pub_dir_create_in_images('auth/source');
	}

	/**
    * Методы вызывается, при деинсталяции каждого дочернего модуля, здесь необходимо
    * удалять таблицы, каталоги, или файлы используемые дочерним модулем.
    *
    * @param string $id_module ID удаляемого дочернего модуля
    */
	function uninstall_children($id_module)
	{
	}


}

$install = new auth_install();
$install->set_name('[#auth_modul_base_name#]');
$install->set_id_modul('auth');
$install->set_admin_interface(1);

//Параметры модуля
//Страница где происходит регистрация
$param = new properties_pagesite();
$param->set_id("id_page_registration");
$param->set_caption("[#auth_module_method_name1_param1_caption#]");
$install->add_modul_properties($param);

//Страница с личным кабинетом
$param = new properties_pagesite();
$param->set_id("id_page_cabinet");
$param->set_caption("[#auth_module_method_name1_param2_caption#]");
$install->add_modul_properties($param);

//емейл(ы) админов, куда будет отсылаться письмо о регистрации
$param = new properties_string();
$param->set_id("admin_email_4_registration");
$param->set_caption("[#auth_admin_email4reg#]");
$install->add_modul_properties($param);

//сабж письма о регистрации юзера для админа
$param = new properties_string();
$param->set_id("admin_subj_4_registration");
$param->set_caption("[#auth_admin_subj4reg#]");
$param->set_default('Регистрация нового пользователя на сайте %host%');
$install->add_modul_properties($param);

//сабж письма о регистрации для юзера
$param = new properties_string();
$param->set_id("user_subj_4_registration");
$param->set_caption("[#auth_user_subj4reg#]");
$param->set_default('Ваша регистрация на сайте %host%');
$install->add_modul_properties($param);

$param = new properties_select();
$param->set_id('reg_activation_type');
$param->set_caption('[#auth_reg_activation_type#]');
$param->set_data(array(
    'confirm_link' => '[#auth_activation_confirmation_link#]',
    'admin_manual' => '[#auth_activation_admin_manual#]',
));
$param->set_default('confirm_link');




//========================================================================================
//Опишем публичные методы со всеми возможными параметрами
//========================================================================================
//Метод отображения авторизации
$install->add_public_metod('pub_show_authorize', '[#auth_module_method_name1#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('');
$property->set_id('authorize_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_authorize', $property);
// Страница регистрации
$property = new properties_pagesite();
$property->set_caption('[#auth_module_method_name1_param1_caption#]');
$property->set_default('');
$property->set_id('id_page_registration');
$install->add_public_metod_parametrs('pub_show_authorize', $property);
// Страница кабинета
$property = new properties_pagesite();
$property->set_caption('[#auth_module_method_name1_param2_caption#]');
$property->set_default('');
$property->set_id('id_page_cabinet');
$install->add_public_metod_parametrs('pub_show_authorize', $property);



//Метод отображения регистрации
$install->add_public_metod('pub_show_registration', '[#auth_module_method_name2#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('');
$property->set_id('reg_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_registration', $property);
// Станица кабинета
$property = new properties_pagesite();
$property->set_caption('[#auth_module_method_name1_param2_caption#]');
$property->set_default('');
$property->set_id('id_page_cabinet');
$install->add_public_metod_parametrs('pub_show_registration', $property);



//Метод отображения личного кабинета
$install->add_public_metod('pub_show_cabinet', '[#auth_module_method_name3#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('');
$property->set_id('cabinet_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_cabinet', $property);



//Показываем личные данные
$install->add_public_metod('pub_show_info', '[#auth_module_method_name4#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('');
$property->set_id('info_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_info', $property);
// Станица кабинета
$property = new properties_pagesite();
$property->set_caption('[#auth_module_method_name1_param2_caption#]');
$property->set_default('');
$property->set_id('id_page_cabinet');
$install->add_public_metod_parametrs('pub_show_info', $property);


//Отображение публичного профиля
$install->add_public_metod('pub_show_profile', '[#auth_pub_show_profile#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('modules/auth/templates_user/auth_show_profile.html');
$property->set_id('info_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_profile', $property);


//Показываем форму восстановления пароля
$install->add_public_metod('pub_show_remember', '[#auth_module_method_name5#]');
// Шаблон
$property = new properties_file();
$property->set_caption('[#auth_user_tpl#]');
$property->set_default('');
$property->set_id('remember_tpl');
$property->set_mask('htm,html');
$property->set_patch('modules/auth/templates_user/');
$install->add_public_metod_parametrs('pub_show_remember', $property);

//То, что ставится автоматически при интсляции базового модуля пока оставим так, как есть...
//Теперь можно прописать дочерние модули, которые будут автоматически созданы при
//инсталяции модуля а так же макросы и свойства, каждого из дочерних модулей.
//Свойства модуля
$install->module_copy[0]['name'] = 'auth_modul_base_name1';
$install->module_copy[0]['action'][0]['caption'] = 'Форма авторизации';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_show_authorize';
$install->module_copy[0]['action'][0]['properties']['authorize_tpl'] = 'auth_show_auth.html';
$install->module_copy[0]['action'][0]['properties']['id_page_registration'] = 'register';
$install->module_copy[0]['action'][0]['properties']['id_page_cabinet'] = 'cabinet';

$install->module_copy[0]['action'][1]['caption'] = 'Форма регистрации';
$install->module_copy[0]['action'][1]['id_metod'] = 'pub_show_registration';
$install->module_copy[0]['action'][1]['properties']['reg_tpl'] = 'auth_show_reg.html';
$install->module_copy[0]['action'][1]['properties']['id_page_cabinet'] = 'cabinet';

$install->module_copy[0]['action'][2]['caption'] = 'Личный кабинет';
$install->module_copy[0]['action'][2]['id_metod'] = 'pub_show_cabinet';
$install->module_copy[0]['action'][2]['properties']['cabinet_tpl'] = 'auth_show_cab.html';

$install->module_copy[0]['action'][3]['caption'] = 'Личные данные';
$install->module_copy[0]['action'][3]['id_metod'] = 'pub_show_info';
$install->module_copy[0]['action'][3]['properties']['remember_tpl'] = 'auth_show_remember.html';

$install->module_copy[0]['action'][4]['caption'] = 'Напоминание пароля';
$install->module_copy[0]['action'][4]['id_metod'] = 'pub_show_remember';
$install->module_copy[0]['action'][3]['properties']['info_tpl'] = 'auth_show_cab.html';