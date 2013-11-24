<?php

/********** Настройки ***********************************/

// ПОЛНЫЙ (от корня) путь к папке c ini.php
$santapath = realpath(dirname(__FILE__)."/../../../")."/";
//если предыдущая строчка не срабатывает, закомментируйте ей и укажите путь вручную:
//$santapath = "/home/domains/папка_домена/public_html/";
$moduleid = "search1";

// URL страницы сайта, с которой начинается индексация, например http://www.webkes.info/
$start_url = "http://kattilera.ru/";

print "santa path: ".$santapath."\n";
print "start url:".$start_url."\n";

/**********************************************************/
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);

//chdir($path);

require_once($santapath."ini.php");
include_once($santapath."include/kernel.class.php"); //Ядро
include_once($santapath."include/pub_interface.class.php");
include_once($santapath."admin/manager_modules.class.php"); //Менеджер управления модулями
$kernel = new kernel(PREFIX);
$kernel->priv_module_for_action_set($moduleid);

require_once($santapath."modules/search/include/indexator.class.php");
require_once($santapath."modules/search/include/searcher.class.php");
require_once($santapath."modules/search/include/urlparser.class.php");
require_once($santapath."modules/search/include/webcontentparser.class.php");
require_once($santapath."modules/search/include/htmlparser.class.php");
require_once($santapath."modules/search/include/searchdb.class.php");
require_once($santapath."modules/search/include/lingua_stem_ru.class.php");
require_once($santapath."modules/search/include/pdfparser/pdfobject.class.php");
require_once($santapath."modules/search/include/pdfparser/type1encoding/win-1251.inc.php");
require_once($santapath."modules/search/include/pdfparser/dictionaryparser.class.php");
require_once($santapath."modules/search/include/pdfparser/kvadrpdfobject.class.php");
require_once($santapath."modules/search/include/pdfparser/pdfparser.class.php");
require_once($santapath."modules/search/include/pdfparser/spacepdfobject.class.php");
require_once($santapath."modules/search/include/pdfparser/ugolpdfobject.class.php");

$indexator = new Indexator();
$indexator->clear_index_data();
$indexator->index_site($start_url);