<?php
/**
 * Вызывается при инсталяции модуля
 * @copyright ArtProm (с) 2001-2005
 * @version 1.0
 */


require_once("include/indexator.class.php");
require_once("include/searcher.class.php");

require_once("include/urlparser.class.php");
require_once("include/webcontentparser.class.php");
require_once("include/htmlparser.class.php");


require_once("include/searchdb.class.php");


//Осонвные параметры модуля
class search_install extends install_modules
{
	/**
     * Вызывается при инстялции базового модуля
     */
	function install($id_module, $reinstall = false)
	{

	}


	/**
     * Методы вызывается при деинтсаляции базового модуля. ID базоовго модуля
     * точно известно и определется самим модулем, но он (ID) так же передается в
     * качестве параметра. Здесь необходимо производить удаление каталогов, файлов и таблиц используемых
     * базовым модулем и создаваемых в install
     * @param string $id_module ID удаляемого базового модуля
     */

	function uninstall($id_module)
	{

	}


	/**
     * Методы вызывается, при инсталяции каждого дочернего модуля, здесь необходимо
     * создавать таблицы каталоги, или файлы используемые дочерним модулем. Уникальность создаваемых
     * объектов обеспечивается с помощью передвавемого ID модуля
     *
     * @param string $id_module ID вновь создаваемого дочернего модуля
     * @param boolean $reinstall переинсталяция?
     */
	function install_children($id_module, $reinstall = false)
	{
		global $kernel;

        $query ="CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_docs`
		(
			id INT AUTO_INCREMENT NOT NULL,
			doc TEXT,
			doc_hash char(32),
			contents_hash char(32),
			format_id tinyint,
			snipped MEDIUMBLOB,
			primary key(id),
			unique(doc_hash)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $kernel->runSQL($query);


        $query ="CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_words`
		(
			id INT AUTO_INCREMENT NOT NULL,
			word VARCHAR(50) BINARY,
			primary key(id),
			unique(word)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $kernel->runSQL($query);


        $query ="CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_index`
		(
			id 			INT AUTO_INCREMENT NOT NULL,
			doc_id 		INT,
			word_id 	INT,
			weight		INT, # вес, умноженный на тысячу и округлённый
			primary key(id),
			key(doc_id, word_id),
			key(word_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $kernel->runSQL($query);

        $query ="CREATE TABLE IF NOT EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_ignored`
		(
          `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
          `word` varchar(255) NOT NULL,
          PRIMARY KEY (`id`),
          UNIQUE KEY `word` (`word`)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8";
        $kernel->runSQL($query);
    }

   /**
    * Метод вызывается при деинсталяции каждого дочернего модуля, здесь необходимо
    * удалять таблицы, каталоги, или файлы используемые дочерним модулем.
    *
    * @param string $id_module ID удаляемого дочернего модуля
    */
	function uninstall_children($id_module)
	{
        global $kernel;
        $kernel->runSQL("DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_docs`");
        $kernel->runSQL("DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_words`");
        $kernel->runSQL("DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_index`");
        $kernel->runSQL("DROP TABLE IF EXISTS `".$kernel->pub_prefix_get()."_".$id_module."_ignored`");
	}


}

$install = new search_install();


$install->set_name('[#search_name_modul_base_name#]');
$install->set_id_modul('search');
$install->set_admin_interface(2);

//Параметры модуля, здесь он один
$param = new properties_string();
$param->set_id('user_name');
$param->set_caption('[#search_modul_prop_user_auth#]');
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('user_pass');
$param->set_caption('[#search_modul_prop_pass_auth#]');
$install->add_modul_properties($param);

$param = new properties_pagesite();
$param->set_id('page_search');
$param->set_caption('[#search_modul_prop_page_search#]');
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('url_count');
$param->set_caption('[#search_modul_prop_user_count_url#]');
$param->set_default(1);
$install->add_modul_properties($param);

$param = new properties_string();
$param->set_id('php_mem');
$param->set_caption('[#search_modul_prop_user_mem_php#]');
$param->set_default(0);
$install->add_modul_properties($param);


//========================================================================================
//Опишем публичные методы со всеми возможными параметрами
//========================================================================================
//Отображает маленькую форму поиска
$install->add_public_metod('pub_show_only_form', '[#search_pub_show_only_form#]');
$property = new properties_file();
$property->set_caption('[#search_pub_show_only_form_template#]');
$property->set_default('modules/search/templates_user/form_small.html');
$property->set_id('template');
$property->set_mask('html,htm');
$property->set_patch('modules/search/templates_user/');
$install->add_public_metod_parametrs('pub_show_only_form', $property);

//Выводит результаты поиска, и, если надо, форму поиска.
$install->add_public_metod('pub_show_search_results', '[#search_pub_show_search_results#]');

$property = new properties_file();
$property->set_caption('[#search_pub_show_search_results_template#]');
$property->set_default('modules/search/templates_user/search.html');
$property->set_id('template');
$property->set_mask('html,htm');
$property->set_patch('modules/search/templates_user/');
$install->add_public_metod_parametrs('pub_show_search_results', $property);



//То, что ставится автоматически при инсталяции базового модуля пока оставим так, как есть...
//Теперь можно прописать дочерние модули, которые будут автоматически созданы при
//инсталяции модуля а так же макросы и свойства, каждого из дочерних модулей.
//Свойства модуля
$install->module_copy[0]['name'] = 'search_name_modul_base_name1';
$install->module_copy[0]['action'][0]['caption'] = 'Вывести форму поиска (для главной)';
$install->module_copy[0]['action'][0]['id_metod'] = 'pub_show_only_form';
$install->module_copy[0]['action'][0]['param']['template'] = 'modules/search/templates_user/form_small.html'; //у метода нет параметров
$install->module_copy[0]['action'][1]['caption'] = 'Вывести результаты поиска';
$install->module_copy[0]['action'][1]['id_metod'] = 'pub_show_search_results';
$install->module_copy[0]['action'][1]['param']['template'] = 'modules/search/templates_user/search.html'; //у метода нет параметров
