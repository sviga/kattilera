<?php
// ©SantaFox 2010г.
//
//Assenbled by Ronin
//Crazy_Cat@pop3.ru
//fixes by s@nchez

//Без параметров выводит RSS всех новостных лент
//Вызов с параметром rss.php?id=newsi1 - выдает RSS новостной ленты с ID newsi1


$doc_root=$_SERVER['DOCUMENT_ROOT']."/";
$host_root=$_SERVER['HTTP_HOST'];
$script=$_SERVER['SCRIPT_NAME'];
$query_id="";



include ($doc_root."ini.php"); // Файл с настройками

if (SHOW_INT_ERRORE_MESSAGE)
    error_reporting(E_ALL^E_NOTICE);
else
    error_reporting(0);

include ($doc_root."include/kernel.class.php"); //Ядро
include ($doc_root."include/pub_interface.class.php");
include ($doc_root."include/frontoffice_manager.class.php"); //управление фронт офисом
include ($doc_root."admin/manager_modules.class.php"); //Менеджер управления модулями
include ($doc_root."admin/manager_users.class.php"); //Менеджер управления модулями
include ($doc_root."admin/manager_stat.class.php");

$kernel = new kernel(PREFIX);


define('DATE_FORMAT_RFC822','r');
header("Content-type: text/xml; charset=utf-8");

$last_date=date(DATE_FORMAT_RFC822);

/*
Поиск имени страницы для формирования новости по номеру новости в таблице 'prefix'_newsi
Сначала по таблице новостей выбираем модуль, который отвечает за вывод
новости под номером $index. Далее по 'prefix'_structure получаем имя страницы для формирования
корректного URL на новость.
*/

function page_by_id($index)
{
	global $kernel;

	$result = $kernel->runSQL('SELECT `module_id` FROM `'.$kernel->pub_prefix_get().'_newsi` WHERE `id`= "'.$index.'"');
	$module_id=mysql_fetch_row($result);
	$temp_query='SELECT `id` FROM `'.$kernel->pub_prefix_get().'_structure` WHERE `serialize` REGEXP \'content";a:[0-9]+:{s:6:"id_mod";s:[0-9]+:"'.$module_id[0]."'";
    	$result = $kernel->runSQL($temp_query);
	$id=mysql_fetch_row($result);
	return $id[0];
};


/*
В связи с тем, что $kernel->pub_httpget_get() возвращает пустой массив
приходится использовать $_GET
*/

if ($_GET["id"])
{
	$module_id=mysql_real_escape_string($_GET["id"]);
	$query_id="AND `module_id`='".$module_id."'";
}

$modules=$kernel->pub_modules_get("newsi");
$module=$modules[$module_id];
/*
Шапка RSS
*/
echo '<?xml version="1.0" encoding="utf-8"?>
	<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:content="http://purl.org/rss/1.0/modules/content/">
	<channel>
	<title>Новостная лента</title>
    <link>http://'.$host_root.$script.'</link>
    <description>Последние новости на '.$host_root.'</description>
    <pubDate>'.$last_date.'</pubDate>
    <lastBuildDate>'.$last_date.'</lastBuildDate>
    <language>ru</language>
	<copyright></copyright>
	<image>
		<url>http://'.$host_root.'/rss_logo.png</url>
		<title>Новостная лента</title>
		<link>http://'.$host_root.'/</link>
	</image>
    ';
$query = "SELECT `header`,`description_short`,`id`, CAST(concat(`date`,' ',`time`) AS DATETIME) AS data
          FROM `".$kernel->pub_prefix_get().'_newsi`
          WHERE (`date`<CURDATE() OR (`date`=CURDATE() AND `time`<=CURTIME())) AND `rss`=1 '.$query_id.'
          ORDER by `date` desc, `time` desc';
$result=$kernel->runSQL($query);

while ($row=mysql_fetch_array($result))
{
	$title   = strip_tags(trim($row['header']));
	$text    = $row['description_short'];
	$news_url= $row['id'];
	$pub_date= date(DATE_FORMAT_RFC822,strtotime($row['data']));
	$author= $row['author'];
	echo '<item>
		  <title>'.$title.'</title>
		  <link>http://'.$host_root.'/'.page_by_id($news_url).'.html?id='.$news_url.'</link>
		  <pubDate>'.$pub_date.'</pubDate>
		  <guid>http://'.$host_root.'/'.page_by_id($news_url).'.html?id='.$news_url.'</guid>
		  <dc:creator>'.$author.'</dc:creator>'.
		  '<description><![CDATA['.$text.']]></description>'.
//		  '<description>'.$text.'</description>'.
		  '</item>';

}

/*
Подвал RSS
*/
echo '</channel>
</rss>';
?>