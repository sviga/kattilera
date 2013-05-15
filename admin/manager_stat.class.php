<?php

class manager_stat
{
	var $form;
	var $width_image_graph = 600; //Ширина формируемых графиков

	function manager_stat()
	{
		$this->form	= "";
	}

	/**
     * Предопределённая функция для формирования меню
     *
     * Функция орпеделяет какие элементы меню присутсвуют в меню раздела
     * @param pub_interface $show Объект класса pub_interface
     * @return void
     * @access private
     */
	function interface_get_menu($show)
    {
        $show->set_menu_block('[#statist_block_label1#]');
        $show->set_menu("[#statist_host_label_main#]","form_host");
        $show->set_menu("[#statist_referer_label_main#]","show_referer");
        $show->set_menu("[#statist_words_label_main#]","show_word");
        $show->set_menu("[#statist_pages_label_main#]","show_top_pages");

        $show->set_menu_block("[#statist_block_label2#]");
        $show->set_menu("[#statist_ip_label_main#]","show_ip");

        $show->set_menu_block("[#statist_block_label3#]");
        $show->set_menu("[#statist_roboindex_label_main#]","show_index");

        $show->set_menu_block("[#statist_block_label4#]");
        $show->set_menu("[#statist_domain_label_main#]","show_domain");
        $show->set_menu("[#statist_partner_label_main#]","show_partner");

        $show->set_menu_default('form_host');
    }

	function start()
    {
    	global $kernel;

    	//Проверим, может в форме что-то есть и надо это записать туда
    	$_SESSION["stat_date_start"]				=			isset($_SESSION["stat_date_start"])?$_SESSION["stat_date_start"]:"01".date("-m-Y", mktime());
    	$_SESSION["stat_date_end"]					=			isset($_SESSION["stat_date_end"])?$_SESSION["stat_date_end"]:date("d-m-Y", mktime());
    	//$_SESSION["stat_date_end"]					=			isset($_SESSION["stat_date_end"])?$_SESSION["stat_date_end"]:date("d-m-Y", mktime());

    	$_SESSION["stat_checked_hit"]				=			isset($_SESSION["stat_checked_hit"])?$_SESSION["stat_checked_hit"]:"true";
    	$_SESSION["stat_checked_host"]				=			isset($_SESSION["stat_checked_host"])?$_SESSION["stat_checked_host"]:"true";
    	$_SESSION["stat_checked_f_people"]			=			isset($_SESSION["stat_checked_f_people"])?$_SESSION["stat_checked_f_people"]:"true";
    	$_SESSION["stat_checked_diagram"]			=			isset($_SESSION["stat_checked_diagram"])?$_SESSION["stat_checked_diagram"]:"true";

        //Определим текущее дейсвтие
		$action = $kernel->pub_section_leftmenu_get();
    	//В зависимости от действия будет разный контент в правой части
    	$html_content = "";
    	switch($action)
    	{
    		//Выводим форму для отчёта о хостах и хитах
    		case "form_host":
                $html_content = $this->get_form("host","[#statist_host_label_main#]", "get_host");
    			break;

    		//Выводим результаты обработки формы
    		case 'get_host':
    		    $html_content = $this->get_host();
    		    break;

    		case 'get_host_data':
            	$action = $kernel->pub_httpget_get();
				$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;


           		$date_start = isset($action["date_start"])?$action["date_start"]:"";
           		$date_end   = isset($action["date_end"])?$action["date_end"]:"";


           		$checked_f_people = isset($action["f_people"])?"true":"false";

           		//Приводим даты к формату UNIXTIME
           		$date_start_dmy = $this->date_to_untixtime($date_start);
                $date_end_dmy   = $this->date_to_untixtime($date_end, true);


           		//Определяем дополнительные условия для запроса
           		$_where		=	"";
           		if ($IDPartner)
           			$_where .= " AND IDPartner=".mysql_real_escape_string($IDPartner)."";

           		if ($checked_f_people == "true")
           			$_where	.=	" AND f_people=1";

                //Начнем выполнять запрос
           		$sql = "SELECT tstc, FROM_UNIXTIME(tstc, '%d.%m.%Y') as tstc_date,
           		               COUNT(DISTINCT IDSess) as host, COUNT(IDHost) as hit
           		        FROM ".$kernel->pub_prefix_get()."_stat_host
           		        WHERE tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." $_where
           		        GROUP BY tstc_date
           		        ORDER BY tstc";



                $result = $kernel->runSQL($sql);
                $data_hits = array();
                $data_hosts = array();
                $labels = array();
                while ($row = mysql_fetch_assoc($result))
                {
                    $data_hits[] = $row['hit'];
                    $data_hosts[] = $row['host'];
                    $labels[] = $row['tstc_date'];
                }

                require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open-flash-chart.php');
                if (($this->date_to_untixtime($_SESSION['stat_date_end'], true) - $this->date_to_untixtime($_SESSION['stat_date_start'])) >= 604800 )
                {
                    $g = new graph();
                    $g->title( $date_start.' / '.$date_end, '{font-size: 20px; color: #000000}' );

                        $g->set_data($data_hits);
                        $g->line( 2, '#4F81BD', 'Хиты', 10 );
                        $g->set_data($data_hosts);
                        $g->line( 4, '#4F81BD', 'Хосты', 10 );

                    $g->set_x_labels( $labels );
                    $g->set_x_label_style( 10, '0x000000', 0, 7 );
                    $g->set_x_axis_steps(7);

                    $g->set_tool_tip( '#key#: #val#<br>#x_label# #x_legend#' );
                    $g->set_y_max( max($data_hits) + 10);
                    $g->y_label_steps(10);
                    $g->set_x_offset( false );
                }
                else
                {
                    $bar_1 = new bar( 25, '#4F81BD' );
                    $bar_1->key('Хиты', 10 );
                    $bar_2 = new bar( 50, '#4F81BD' );
                    $bar_2->key('Хосты', 10 );
                    $bar_1->data = $data_hits;
                    $bar_2->data = $data_hosts;
                    $g = new graph();
                    $g->title($date_start.' / '.$date_end, '{font-size: 20px; color: #000000}' );
                    $g->data_sets[] = $bar_1;
                    $g->data_sets[] = $bar_2;
                    $g->set_tool_tip( '#key#: #val#<br>#x_label# #x_legend#' );
                    $g->set_x_labels( $labels );
                    $g->set_x_label_style( 10, '0x000000', 0, 1 );
                    $g->set_x_axis_steps( 1 );
                    $g->set_y_max( max($data_hits) + 10);
                    $g->y_label_steps( 2 );
                }
                $g->bg_colour = '#FFFFFF';
                $g->x_axis_colour( '#E0EDFD', '#E0EDFD' );
                $g->y_axis_colour( '#E0EDFD', '#E0EDFD' );
                $html_content = $g->render();
    		    break;

    		//Форма отчёта о ссылающихся страницах
    		case "show_referer":
    		    $html_content = $this->get_form("referer", "[#statist_referer_label_main#]", "get_referer");
    			break;

    		//Выводит отчёт о ссылающихся страницах
    		case "get_referer":
    		    $html_content = $this->get_referer();
    		    break;

    		//Форма отчёта по ключевым словам
    		case "show_word":
    		    $html_content = $this->get_form("word", "[#statist_words_label_main#]", "get_word");
    			break;

    	    //Формирует данные для отчёта по поисковым словам
    		case "get_word":
    			$html_content = $this->get_word();
    			break;

    		case "show_top_pages":
    			$html_content = $this->get_form(null, "[#statist_pages_label_main#]", "get_top_pages");
    			break;

    		case "get_top_pages":
    			$html_content = $this->get_top_pages();
    			break;

    		case "get_word_img_data":
                // generate some random data
                srand((double)microtime()*1000000);

                $data = array();
                $names = array();

                foreach ($_SESSION['stat'][$kernel->pub_httpget_get('cur_id_key')]['search'] as $value)
                {
                    $data[] = $value['count'];
                    $names[] = $value['search'];
                }

                $data_percents = array();
                foreach ($data as $value)
                {
                    $data_percents[] = round((100 * $value / array_sum($data)), 2);
                }
                require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open-flash-chart.php');
                $g = new graph();
                $g->bg_colour = '#E0EDFD';
                $g->pie(80,'#505050','#000000');
                $g->pie_values( $data_percents, $names);
                $g->pie_slice_colours(array('#6195ED', '#1C6CF2','#437FE5', '#79A7F6','#5689E1','#124AAB'));
                $g->set_tool_tip( '#val#%' );
                return $g->render();
                break;

            //Форма отчёта по прямым заходам
    		case "show_ip":
    			$html_content = $this->get_form("ip", "[#statist_ip_label_main#]", "get_ip", false);
    			break;

    		//Формирует результаты отчёта по прямым заходам
    		case "get_ip":
    			$html_content = $this->get_ip();
    			break;

    		//Форма отчёта по роботам
    		case "show_index":
    			$html_content = $this->get_form("index", "[#statist_roboindex_label_main#]", "get_index", false);
    			break;

    	    //Результаты отчёта по роботам
    		case "get_index":
    			$html_content = $this->get_index();
    			break;

    		//Конкретные страницы просмотренные роботом
    	    case "show_indexed_page":
    			$html_content = $this->show_indexed_page();
                break;

            //Картинка с уровнем индексации
    		case "get_index_img":
    			$html_content = $this->get_chart_data();
                break;

            //Форма с доменами сайта, считающимися своими
    		case "show_domain":
    			$html_content = $this->show_domain();
    			break;

    		//Вызывается при добавлении нового домена
    		case "add_domain":
                $domain = str_ireplace("http://", "", $kernel->pub_httppost_get("domain"));
                if( $domain )
                {
                    $IDDomain = $this->get_IDDomain_by_domain($domain);
                    if( !$IDDomain )
                    {
                        if (mb_strlen($domain)>128)
                            $domain=mb_substr($domain,0,128);
                        $query = "INSERT INTO ".$kernel->pub_prefix_get()."_stat_domain (IDDomain, domain) VALUES (0, '".mysql_real_escape_string($domain)."')";
                        $kernel->runSQL($query);
                    }
                }

                $html_content = $kernel->pub_json_encode(array("success"=>true,"errore_count"=>0,"result_message"=>"","redirect"=>"show_domain"));
    			break;

    		//Вызывается при удалении существующего домена
    		case 'del_domain':
                $IDDomain = $kernel->pub_httpget_get('delid');
                if ($IDDomain > 0)
                    $kernel->runSQL("DELETE FROM ".$kernel->pub_prefix_get()."_stat_domain WHERE IDDomain=".mysql_real_escape_string($IDDomain)."");

                $kernel->pub_redirect_refresh('show_domain');
    			break;

    		//Выводится форма с партнёрами
    		case "show_partner":
				$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;
                $html_content = $this->show_partner($IDPartner);
                break;

    		case "set_stat":
                echo "Добавляем запись в статистику.....<BR><BR><PRE>";
                echo session_id();
                echo "<HR>";
                $this->set_stat();
                break;
    	}
        $mypost = $kernel->pub_httppost_get();
    	$_SESSION["stat_date_start"] = isset($mypost["date_start"])?$mypost["date_start"]:$_SESSION["stat_date_start"];
        $_SESSION["stat_date_end"]   = isset($mypost["date_end"])?$mypost["date_end"]:$_SESSION["stat_date_end"];
        return $html_content;
    }


    function get_IDRobot_by_agent($agent)
    {
    	global $kernel;
    	$IDRobot	=	0;
    	if( !$q = $kernel->runSQL("SELECT * FROM ".$kernel->pub_prefix_get()."_stat_robot") )
            return $IDRobot;
        while( $qa = mysql_fetch_array($q))
        {
            if( preg_match("/".preg_quote($qa["agent"], "/")."/i", "$agent") )
                $IDRobot	=	$qa["IDRobot"];
        }
    	mysql_free_result($q);
    	return $IDRobot;
    }

    function get_IDSearch_by_referer($referer = "")
    {
    	global $kernel;
    	if (empty($referer))
    	   return 0;
        $recs = $kernel->db_get_list_simple("_stat_search","true","preg_host,IDSearch");
        foreach ($recs as $rec)
        {
            if( preg_match("/".$rec["preg_host"]."/i", $referer))
                return $rec["IDSearch"];
        }
    	return 0;
    }


    function get_IDDomain_by_domain($domain)
    {
    	global $kernel;

    	$IDDomain	=	0;

    	if( !$q = $kernel->runSQL("SELECT IDDomain FROM ".$kernel->pub_prefix_get()."_stat_domain WHERE domain='".mysql_real_escape_string($domain)."' OR domain='www.".mysql_real_escape_string($domain)."'") )
            return $IDDomain;
        if( $qa = mysql_fetch_array($q) )
            $IDDomain	=	$qa["IDDomain"];
    	mysql_free_result($q);
    	return $IDDomain;
    }

    function get_list_domain()
    {
    	global $kernel;
    	$list_domain	=	array();
    	if( !$q = $kernel->runSQL("SELECT * FROM ".$kernel->pub_prefix_get()."_stat_domain") )
            return $list_domain;
        while( $qa = mysql_fetch_array($q))
        {
            $list_domain[] = array("IDDomain" => "$qa[IDDomain]", "domain" => "$qa[domain]");
        }
    	mysql_free_result($q);
    	return $list_domain;
    }

    /**
     * Определяет партнера по заправшиваемой странице сайта
     *
     * @param string $str
     * @return integer
     */
    function get_IDPartner($str)
    {
    	global $kernel;
    	$sql = "SELECT * FROM ".$kernel->pub_prefix_get()."_stat_partner_eregs";
    	$q = $kernel->runSQL($sql);
		while ($qa = mysql_fetch_array($q))
		{
			if (preg_match("/".$qa['preg_partner']."/i", $str))
                return  $qa["IDPartner"];
		}
    	return 0;
    }

    function get_word_by_referer($IDSearch, $referer)
    {
    	global $kernel;

    	$word	=	"";
    	//Вытащим прегмач для поиска слов
    	$sql = "SELECT preg_word
                FROM ".$kernel->pub_prefix_get()."_stat_search
                WHERE IDSearch=".mysql_real_escape_string($IDSearch)."";

    	$q = $kernel->runSQL($sql);
		if ($qa = mysql_fetch_array($q))
		{
			if( preg_match("/".$qa['preg_word']."(.*?)(\\&|$)/i", "$referer", $matches) )
			{
				$count_matches	=	count($matches);
				$word			=	trim($matches[($count_matches-2)]);
				$word			=	urldecode($word);
				$word			=	urldecode($word);
				$word			=	urldecode($word);
				/////////////Вырубаем Яндексовскую фигню///////////////////
				$word			=	str_replace('&stype=www',	'',	$word);
        		$word			=	str_replace('&stype=image',	'',	$word);
        		$word			=	str_replace('&rpt=rad',		'',	$word);
        		///////////////////////////////////////////////////////////
				$word			=	preg_replace("/=(.*)/", "$1", $word);
				//echo "[$IDSearch]";

				//Yandex
				if( $IDSearch == 1 )
				{
					parse_str($referer, $vars);
					if (isset($vars['qs']))
                        $word = iconv("KOI8-R", "utf-8", $word);
				}
			}

		}
    	mysql_free_result($q);
    	return $word;
    }

    function save_uri($uri)
    {
    	global $kernel;

        if (mb_strlen($uri)>255)
            $uri=mb_substr($uri,0,255);
        $q="INSERT INTO ".$kernel->pub_prefix_get()."_stat_uri
            (IDUri, uri, tstc)
            VALUES
            (0, '".mysql_real_escape_string($uri)."', UNIX_TIMESTAMP())";
    	if (!$kernel->runSQL($q))
            return 0;
    	$IDUri = mysql_insert_id();
    	return $IDUri;
    }

    function save_referer($IDDomain, $IDPartner, $IDSearch, $referer, $referer_domain)
    {
    	global $kernel;
        if (mb_strlen($referer)>255)
            $referer=mb_substr($referer,0,255);
        if (mb_strlen($referer_domain)>128)
            $referer_domain=mb_substr($referer_domain,0,128);

        $q="INSERT INTO ".$kernel->pub_prefix_get()."_stat_referer
            (IDReferer, IDDomain, IDPartner, IDSearch, referer, referer_domain, tstc)
            VALUES
            (0, '".intval($IDDomain)."', '".intval($IDPartner)."', '".intval($IDSearch)."', '".mysql_real_escape_string($referer)."', '".mysql_real_escape_string($referer_domain)."', UNIX_TIMESTAMP())";
    	if( !$kernel->runSQL($q))
            return 0;
    	$IDReferer = mysql_insert_id();

    	return $IDReferer;
    }

    function save_word($IDSearch, $IDReferer, $IDPartner, $word)
    {
    	global $kernel;
        if (mb_strlen($word)>255)
            $word=mb_substr($word,0,255);
        $q="INSERT INTO ".$kernel->pub_prefix_get()."_stat_word
            (IDWord, IDSearch, IDReferer, IDPartner, word, tstc)
            VALUES
            (0, ".intval($IDSearch).", ".intval($IDReferer).", ".intval($IDPartner).", '".mysql_real_escape_string($word)."', UNIX_TIMESTAMP())";
    	if( !$kernel->runSQL($q))
            return 0;
    	$IDWord = mysql_insert_id();
    	return $IDWord;
    }

    function save_host($IDPartner, $IDReferer, $IDUri, $IDSearch, $IDWord, $IDSess, $ip)
    {
    	global $kernel;
        $iplong	= sprintf("%u", ip2long($ip));
        $q="INSERT INTO ".$kernel->pub_prefix_get()."_stat_host
            (IDHost, IDPartner, IDReferer, IDUri, IDSearch, IDWord, IDSess, ip, iplong, tstc)
            VALUES
            (0, '".intval($IDPartner)."', '".intval($IDReferer)."', '".intval($IDUri)."', '".intval($IDSearch)."', '".intval($IDWord)."', '".mysql_real_escape_string($IDSess)."', '".mysql_real_escape_string($ip)."', '".mysql_real_escape_string($iplong)."', UNIX_TIMESTAMP())";
    	if (!$kernel->runSQL($q))
            return 0;
    	$IDHost = mysql_insert_id();
    	return $IDHost;
    }

    function save_index($IDRobot, $IDUri)
    {
    	global $kernel;
        $q="INSERT INTO ".$kernel->pub_prefix_get()."_stat_index
            (IDIndex, IDRobot, IDUri, tstc)
            VALUES
            (0, '".intval($IDRobot)."', '".intval($IDUri)."', UNIX_TIMESTAMP())";
    	if( !$kernel->runSQL($q))
            return 0;
    	$IDIndex = mysql_insert_id();
    	return $IDIndex;
    }


    function visitor_info_get($session_id)
    {
        global $kernel;
        // Заполняем массив пустыми значениями
        $res_array = array();
        $res_array['word'] = "";
        $res_array['referer'] = "";
        $res_array['referer_domain'] = "";
        $res_array['word_id'] = "";
        $res_array['ip'] = "";
        $res_array['iplong'] = "";

        //@todo optimize sql queries
        $sql = "SELECT
                a.referer AS referer,
                a.referer_domain AS referer_domain,
                c.IDWord AS word_id,
                c.ip AS ip,
                c.iplong AS iplong
                FROM
                ".$kernel->pub_prefix_get()."_stat_referer a,
                ".$kernel->pub_prefix_get()."_stat_host c
                WHERE
                c.IDSess='".mysql_real_escape_string($session_id)."'
                AND c.IDReferer = a.IDReferer
                ORDER BY c.IDHost ASC
                LIMIT 1
                ";
//                ".PREFIX."_stat_word b,
        $result = $kernel->runSQL($sql);
        // Если есть информация о первом посещении, заменяем пустые значения на эту информацию
        if (mysql_num_rows($result))
        {
            $data = mysql_fetch_assoc($result);
            $res_array = $data;
            $res_array['word'] = "";
            // Если есть поисковая фраза, вписываем и ее
            if ($data['word_id'] != "0")
            {
                $sql = "SELECT
                        word
                        FROM
                        ".$kernel->pub_prefix_get()."_stat_word
                        WHERE
                        IDWord='".mysql_real_escape_string($data['word_id'])."'
                        ";
                $result = $kernel->runSQL($sql);
                if (mysql_num_rows($result))
                {
                    $data = mysql_fetch_assoc($result);
                    $res_array['word'] = $data['word'];
                }
            }
        }
        return $res_array;
    }


    function set_stat()
    {
    	$stat["ip"]				=	$_SERVER["REMOTE_ADDR"];									//
    	$stat["referer"] = '';

    	//$_SERVER['HTTP_REFERER']	=	"http://www.yandex.ru/yandpage?&q=202889655&p=2&ag=d&qs=text%3D%25CF%25D4%25CB%25D2%25D9%25D4%25CB%25C9%2B%25F3%2B%25EE%25EF%25F7%25F9%25ED%2B%25E7%25EF%25E4%25EF%25ED%2B%26stype%3Dwww";
    	//$_SERVER['HTTP_REFERER']	=	"http://www.yandex.ru/yandsearch?stype=www&nl=0&text=%C3%D3%D2%D2%C0";
    	//$_SERVER['HTTP_REFERER']	=	"http://www.google.com/search?client=opera&rls=ru&q=%D0%BF%D1%80%D0%BE%D0%BC%D1%8B%D1%88%D0%BB%D0%B5%D0%BD%D0%BD%D1%8B%D0%B9+%D0%B4%D0%B8%D0%B7%D0%B0%D0%B9%D0%BD+%D1%81%D0%BF%D0%B1&sourceid=opera&ie=utf-8&oe=utf-8";
    	//$_SERVER['HTTP_REFERER']	=	"http://www.yandex.ru/yandsearch?text=%D2%EE%EF+40+%E2+%D1%D8%C0+2006+%EF%EE+%E2%E5%F0%F1%E8%E8+%22%C1%E8%EB%EB%E1%EE%E0%F0%E4%22&stype=www";

        if (isset($_SERVER["HTTP_REFERER"]))
			$stat["referer"]		=	$_SERVER["HTTP_REFERER"];									//
		$stat["uri"]			=	mysql_real_escape_string($_SERVER["REQUEST_URI"]);									//

		$stat["agent"] = 'unknow';
		if (isset($_SERVER["HTTP_USER_AGENT"]))
		  $stat["agent"]			=	mysql_real_escape_string($_SERVER["HTTP_USER_AGENT"]);								//

		$parsed_referer			=	parse_url(mysql_real_escape_string($stat["referer"]));
		$stat["referer_domain"] = '';
		if (isset($parsed_referer["host"]))
		  $stat["referer_domain"]	=	$parsed_referer["host"];
		$list_domain			=	$this->get_list_domain();

		$stat["IDDomain"]		=	0;
		for( $i=0; $i<count($list_domain); $i++ )
		{
			if( preg_match("#".$list_domain[$i]["domain"]."#", $stat["referer_domain"], $matches) )
			    $stat["IDDomain"] =	$list_domain[$i]["IDDomain"];
		}

		$stat["IDRobot"]		=	$this->get_IDRobot_by_agent($stat["agent"]);				//
		$stat["IDSearch"]		=	$this->get_IDSearch_by_referer($stat["referer"]);

		$id_partner = $this->get_IDPartner($stat["uri"]);
	    if ($id_partner <= 0)
	       $id_partner = $this->get_IDPartner($stat["referer"]);

        $stat["IDPartner"] = $id_partner;
		$stat["IDSess"]			=	session_id();
		$stat["IDWord"]			=	0;
		$stat["word"]			=	"";

		if( $stat["IDSearch"] )
			$stat["word"]		=	$this->get_word_by_referer($stat["IDSearch"], $stat["referer"]);

		$_SESSION["IDHost"]		=		0;
		$stat["IDUri"]			=		$this->save_uri($stat["uri"]);
		if( $stat["IDRobot"] && $stat["IDUri"])			//this robot
			$this->save_index($stat["IDRobot"], $stat["IDUri"]);
		else 							//this user
		{
			if( $stat["referer"] )		//Если есть реферер
			{
				//Сохраняем реферер
				$stat["IDReferer"]		=	$this->save_referer($stat["IDDomain"], $stat["IDPartner"], $stat["IDSearch"], $stat["referer"], $stat["referer_domain"]);

				//Если пришел с поисковика то сохраняем ключевое слово.
				if( $stat["IDSearch"] && $stat["IDReferer"])
					$stat["IDWord"]			=	$this->save_word($stat["IDSearch"], $stat["IDReferer"], $stat["IDPartner"], $stat["word"]);

				//Все данные хоста готовы, сохраняем его
                if ($stat["IDUri"] && $stat["IDWord"] && $stat["IDReferer"])
                {
                    $stat["IDHost"]			=	$this->save_host($stat["IDPartner"], $stat["IDReferer"], $stat["IDUri"], $stat["IDSearch"], $stat["IDWord"], $stat["IDSess"], $stat["ip"]);
                    if ($stat["IDHost"])
                        $_SESSION["IDHost"]		=	$stat["IDHost"];
                }
			}
			else 						//Прямой заход
			{
                if ($stat["IDUri"])
                {
                    $stat["IDHost"]			=	$this->save_host($stat["IDPartner"], 0, $stat["IDUri"], 0, 0, $stat["IDSess"], $stat["ip"]);
                    if ($stat["IDHost"])
                        $_SESSION["IDHost"]		=	$stat["IDHost"];
                }
			}
		}
    }

    function get_partner_list()
    {
    	global $kernel;
    	$query = "SELECT * FROM ".$kernel->pub_prefix_get()."_stat_partner";
    	$result = $kernel->runSQL($query);
    	$partner_list = array();
    	$partner_list[0] = "[#statist_all_all_partnets#]";
        while( $qa = mysql_fetch_array($result) )
            $partner_list[$qa['IDPartner']] = $qa['partner'];
        mysql_free_result($result);
    	return $partner_list;
    }


    function get_form($stat_type, $label_form, $url, $partner = true, $data = true)
    {
        global $kernel;
        $action = $kernel->pub_httppost_get();
        $template = $kernel->pub_template_parse("admin/templates/default/statnew_global_param.html");
        $html = $template['begin'];
        $date_start = '';
        $date_end = '';
        $diapazon_block = $template['diapazon_block'];
        $diapazon_list = '';

        //Проверим, нужно ли добавить сюда выбор даты
        if ($data)
        {
            //Определим временные данные для использования в предустановленных периодах
            $arr_per = array();

            //Текущий день
            $tmp1 = date("d-m-Y").",".date("d-m-Y");
            $arr_per[$tmp1.",".$tmp1] = '[#statist_all_label_this_day#]';

            //Предыдущий день
            //$tmp1 = date("d-m-Y", mktime(0,0,0,date("m"),date("d")-1,date("Y"))).",".date("d-m-Y", mktime(0,0,0,date("m"),date("d")-1,date("Y")));
            $tmp1 = date("d-m-Y", strtotime("-1 day"));
            $arr_per[$tmp1.",".$tmp1] = '[#statist_all_label_last_day#]';

            //Эта неделя
            $tmp_time = date("w");
            if ($tmp_time == 0)
                $tmp_time=7;
            $tmp1 = date("d-m-Y",time()-(60*60*24*($tmp_time-1)));
            $tmp2 = date("d-m-Y");
            $arr_per[$tmp1.",".$tmp2] = '[#statist_all_label_this_week#]';

            //Предыдущая неделя
            $tmp_time = date("w");
            if ($tmp_time == 0)
                $tmp_time=7;
            $tmp2 = date("d-m-Y",time()-(60*60*24*($tmp_time)));
            $tmp1 = date("d-m-Y",time()-(60*60*24*($tmp_time+6)));
            $arr_per[$tmp1.",".$tmp2] = '[#statist_all_label_last_week#]';

            //Этот месяц
            $tmp1 = date("d-m-Y",mktime(0,0,0,date("m"),1,date("Y")));
            $tmp2 = date("d-m-Y");
            $arr_per[$tmp1.",".$tmp2] = '[#statist_all_label_this_month#]';

            //Прошедший месяц
            $tmp_time=date("d");
            $tmp_time=time()-(60*60*24*($tmp_time));
            $tmp_time2=date("t",$tmp_time);
            $tmp_time2=$tmp_time-(60*60*24*($tmp_time2-1));
            $tmp1 = date("d-m-Y",$tmp_time2);
            $tmp2 = date("d-m-Y",$tmp_time);
            $arr_per[$tmp1.",".$tmp2] = '[#statist_all_label_last_month#]';

            //Определим значения текущих дат,если они есть
            $date_start = isset($action["date_start"])?$action["date_start"]:$_SESSION["stat_date_start"];
            $date_end   = isset($action["date_end"])?$action["date_end"]:$_SESSION["stat_date_end"];
            //$str_period = $kernel->pub_array_convert_form($arr_per);

            //Обновим информацию о датах в сессии
            //$_SESSION["stat_date_start"] = isset($action["date_start"])?$action["date_start"]:$_SESSION["stat_date_start"];
            //$_SESSION["stat_date_end"]   = isset($action["date_end"])?$action["date_end"]:$_SESSION["stat_date_end"];
            $_SESSION["stat_date_start"] = $date_start;
            $_SESSION["stat_date_end"]   = $date_end;

            foreach( $arr_per as $ak=>$av)
            {
                $line = $template['diapazon_line'];
                $line = str_replace("%key%", $ak, $line);
                $line = str_replace("%val%", htmlspecialchars($av), $line);
                $diapazon_list.=$line;
            }
            //$diapazon_lines
            //$html_add =	str_replace ("[#array_period#]",     $str_period, $html_add);
        }

        //$html = str_replace("[#select_data#]", $html_add, $html);
        $html = str_replace("[#date_start_value#]", $date_start, $html);
        $html = str_replace("[#date_end_value#]", $date_end, $html);
        $diapazon_block = str_replace("[#diapazon_list#]", $diapazon_list, $diapazon_block);
        $html = str_replace("[#select_diapazon#]", $diapazon_block, $html);

        //Если нужно, то выведем интерфейс для выбора пратнера
        $html_add = '';
        if ($partner)
        {
	   		//$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;
            $html_add = $template['set_partner'];
            $partners =	$this->get_partner_list();
            $plines = '';
            foreach ($partners as $pk=>$pv)
            {
                $line = $template['set_partner_line'];
                $line = str_replace("%key%", htmlspecialchars($pk), $line);
                $line = str_replace("%val%", htmlspecialchars($pv), $line);
                $plines.=$line;
            }
            $html_add = str_replace ("[#partner_list#]", $plines, $html_add);
        }
        $html = str_replace("[#select_partners#]", $html_add, $html);


        //Теперь в зависимости от того, что это за отчет сделаем дополнительные параметры
        $html_add = '';
        switch ($stat_type)
        {
            //Отчет о хостах и хитах
            case 'host':
                //Определим галочки для отчетов
                $checked_hit        = isset($action["show_hit"])?"checked":(($_SERVER['REQUEST_METHOD']=="POST")?"":"checked");
                $checked_host       = isset($action["show_host"])?"checked":(($_SERVER['REQUEST_METHOD']=="POST")?"":"checked");
                $checked_f_people   = isset($action["f_people"])?"checked":(($_SERVER['REQUEST_METHOD']=="POST")?"":"checked");

                $_SESSION["stat_checked_hit"]		=		$checked_hit;
                $_SESSION["stat_checked_host"]		=		$checked_host;
                $_SESSION["stat_checked_f_people"]	=		$checked_f_people;

                $html_add = $template[$stat_type];
                $html_add = str_replace ("[#checked_hit#]",      $checked_hit,      $html_add);
                $html_add = str_replace ("[#checked_host#]",     $checked_host,     $html_add);
                $html_add = str_replace ("[#checked_f_people#]", $checked_f_people,	$html_add);

                break ;

            case 'word':
            	if (isset($_POST['show_diagram']))
            		$checked_diagram = 'checked';
            	else
            		$checked_diagram = '';
                $html_add = $template[$stat_type];
                $html_add =	str_replace ("[#checked_diagram#]",  $checked_diagram,  $html_add);
                break ;
        }
        $html = str_replace("[#dop_fields#]", $html_add, $html);

        //$html = str_replace("[#end_form#]", $template['end'], $html);

        $html = str_replace("%link_form%", $kernel->pub_redirect_for_form($url), $html);
        $html = str_replace("%name_otchet%", $label_form, $html);
        return $html;
    }



    /**
     * Конвретитурет дату в UNIX_TIME
     *
     * Функция проверяет правильность введения даты
     * и приводит её к виду необходимому для mySQL
     * т.е. в UNIXTIME
     * @param string $str_date
     * @param boolean $day_end
     * @return integer
     */
    function date_to_untixtime($str_date, $day_end = false)
    {
        //сначала проверим на общее соответствие
        $dataunix = 0;
        if ($day_end)
            $dataunix = mktime();

        $str_date = trim($str_date);
        $srt_preg="/[0-9][0-9][\\.\\-\\/][0-9][0-9][\\.\\-\\/][0-9][0-9][0-9][0-9]/";
        if (strlen($str_date) != 10)
            return $dataunix;

		if (preg_match($srt_preg, $str_date))
		{
        	//Строка в нужном нам формате
            $t_y = substr($str_date, -4);
            $t_m = substr($str_date, 3, 2);
            $t_d = substr($str_date, 0, 2);

            if ($day_end)
                $dataunix = mktime(23,59,59,$t_m,$t_d,$t_y);
            else
            	$dataunix = mktime(0,0,0,$t_m,$t_d,$t_y);
        }
	   return $dataunix;
    }


    function get_type_class_tr($num)
    {
        $num = intval($num);

        if ((ceil($num/2) - floor($num/2)) != 0)
            return 'class="admin_table_string"';
        else
            return "";
    }


    /**
     * Отвечает за формирвоание отчета по хостам и хитам
     *
     * @return string
     */
    function get_host()
    {
    	global $kernel;

    	$action = $kernel->pub_httppost_get();
        $html = '';
   		$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;

   		$date_start = isset($action["date_start"])?$action["date_start"]:"";
   		$date_end   = isset($action["date_end"])?$action["date_end"]:"";


        $_where		=	"";

   		if (!empty($action["f_people"]))
        {
            $_where	.=	" AND f_people=1 ";
            $checked_f_people = "true";
        }
        else
            $checked_f_people = "false";

   		//Приводим даты к формату UNIXTIME
   		$date_start_dmy = $this->date_to_untixtime($date_start);
        $date_end_dmy   = $this->date_to_untixtime($date_end, true);

   		//Определяем дополнительные условия для запроса

   		if ($IDPartner)
   			$_where .= " AND IDPartner=".mysql_real_escape_string($IDPartner)."";

        //Начнем выполнять запрос
   		$stat =	array();
   		$sql = "SELECT tstc, FROM_UNIXTIME(tstc, '%d.%m.%Y') as tstc_date,
   		               COUNT(DISTINCT IDSess) as host, COUNT(IDHost) as hit
   		        FROM ".$kernel->pub_prefix_get()."_stat_host
   		        WHERE tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." $_where
   		        GROUP BY tstc_date
   		        ORDER BY tstc";

        $result = $kernel->runSQL($sql);
		while ($qa = mysql_fetch_array($result))
		{
			$stat[] = array("date" => $qa["tstc_date"], "host" => $qa["host"], "hit" => $qa["hit"]);
		}

		mysql_free_result($result);


		$template = $kernel->pub_template_parse("admin/templates/default/statnew_host.html");

		if (empty($stat))
            return ($html.$template['data_empty']);

		//Запишем в сессию что бы можно было вывести рисунок
   		$_SESSION["stat"] = $stat;

		//Всё впорядке, можно формировать отчет с данными

		$html .= $template['begin'];

		//Выведем график

        require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open_flash_chart_object.php');
		$html .= str_replace("%link_image%", open_flash_chart_object_str(500, 400, $kernel->pub_redirect_for_form("get_host_data&partners=$IDPartner&date_start=$date_start&date_end=$date_end&f_people=$checked_f_people"), false, '../components/ofc/'), $template['image']);

        //Set table stat
        $html .= $template['table_begin'];
        for ($i=0; $i<count($stat); $i++)
	    {
	        $html_str = $template['table_tr'];
	        $html_str = str_replace("%set_class_tr%", $kernel->pub_table_tr_class($i), $html_str);
	        $html_str = str_replace("%date%", $stat[$i]["date"], $html_str);
	        $html_str = str_replace("%host%", $stat[$i]["host"], $html_str);
	        $html_str = str_replace("%hit%",  $stat[$i]["hit"],  $html_str);
	        $html .= $html_str;
        }

		$html .= $template['table_end'];
    	return $html;
    }


    /**
     * Выводит форму и формирует отчет "Ссылающиеся страницы"
     *
     * @return string
     */
    function get_referer()
    {
    	global $kernel;
        $action     = $kernel->pub_httppost_get();
   		$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;
        $date_start = isset($action["date_start"])?$action["date_start"]:"";
        $date_end   = isset($action["date_end"])?$action["date_end"]:"";

   		//Приводим даты к формату UNIXTIME
   		$date_start_dmy = $this->date_to_untixtime($date_start);
        $date_end_dmy   = $this->date_to_untixtime($date_end, true);
        $_where = "";
        if ($IDPartner)
        	$_where .= "AND IDPartner=".mysql_real_escape_string($IDPartner)."";

        //Формируем запрос по списку непосредственных страниц
        $stat = array();
        $sql = "SELECT COUNT(IDReferer) as creferer,
                       referer
                FROM ".$kernel->pub_prefix_get()."_stat_referer
                WHERE tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." AND IDDomain=0 AND IDSearch=0 $_where
                GROUP BY referer
                ORDER BY creferer DESC
                LIMIT 30";

        $result = $kernel->runSQL($sql);
        while( $qa = mysql_fetch_array($result))
            $stat[] = array("creferer" => $qa["creferer"], "referer" => $qa["referer"]);

        mysql_free_result($result);

        $template = $kernel->pub_template_parse("admin/templates/default/statnew_referer.html");

        //Если нечего выводить
        if (empty($stat))
            return $template['data_empty'];

        //А теперь узнаем список доменов
        $stat2 = array();
        $sql = "SELECT COUNT(IDReferer) as creferer,
                       referer_domain
                FROM ".$kernel->pub_prefix_get()."_stat_referer
                WHERE tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." AND IDDomain=0 AND IDSearch=0 $_where
                GROUP BY referer_domain
                ORDER BY creferer DESC
                LIMIT 30";

        $result = $kernel->runSQL($sql);
        while( $qa = mysql_fetch_array($result))
            $stat2[] = array("creferer" => $qa["creferer"], "referer_domain" => $qa["referer_domain"]);

        mysql_free_result($result);

        //Вот теперь можно выводить
        //Сначала сформируем табличку с доменами
        $dhtml = $template['domain_begin'];
        for ($i=0; $i<count($stat2); $i++ )
        {
	        $html_str = $template['domain_tr'];
	        $html_str = str_replace("%set_class_tr%", $kernel->pub_table_tr_class($i), $html_str);
	        $html_str = str_replace("%num%", ($i+1), $html_str);
	        $link = "http://".htmlspecialchars($stat2[$i]["referer_domain"], ENT_QUOTES);
	        $html_str = str_replace("%link%", $link, $html_str);
	        $html_str = str_replace("%name_link%", $stat2[$i]["referer_domain"], $html_str);
	        $html_str = str_replace("%count%",  $stat2[$i]["creferer"],  $html_str);

	        $dhtml .= $html_str;
        }
        $dhtml .= $template['domain_end'];


        //Теперь сформируем таблицу с непосредственно ссылками
        $rhtml = $template['ref_begin'];
        for ($i=0; $i<count($stat); $i++ )
        {
	        $html_str = $template['ref_tr'];
	        $html_str = str_replace("%set_class_tr%", $kernel->pub_table_tr_class($i), $html_str);
	        $html_str = str_replace("%num%", ($i+1), $html_str);
	        $link = htmlspecialchars($stat[$i]["referer"], ENT_QUOTES);
	        $html_str = str_replace("%link%", $link, $html_str);
	        $html_str = str_replace("%name_link%", $stat[$i]["referer"], $html_str);
	        $html_str = str_replace("%count%",  $stat[$i]["creferer"],  $html_str);

	        $rhtml .= $html_str;
        }
        $rhtml .= $template['ref_end'];

        //Теперь объеденим две таблицы и выведем на экран
        $html = $template['main'];
        $html = str_replace("%table_domain%", $dhtml, $html);
        $html = str_replace("%table_referer%", $rhtml, $html);

        return $html;
    }

    /**
     * Выводит форму и формирует отчет по популярным ключевым словам, по которым
     * люди приходят на сайт
     *
     * @return string
     */

    function get_word()
    {
    	global $kernel;
        $action       = $kernel->pub_httppost_get();
   		$IDPartner = isset($action["partners"])?(int)$action["partners"]:0;
        $date_start   = isset($action["date_start"])?$action["date_start"]:"";
        $date_end     = isset($action["date_end"])?$action["date_end"]:"";
        if ($kernel->pub_httpget_get('show_diagram') || (isset($action["show_diagram"]) && !empty($action["show_diagram"])))
        	$show_diagram = 1;
        else
        	$show_diagram = 0;
        if (empty($_GET['date_start_dmy']))
   		   $date_start_dmy = $this->date_to_untixtime($date_start);
        else
            $date_start_dmy = $_GET['date_start_dmy'];
        if (empty($_GET['date_end_dmy']))
            $date_end_dmy = $this->date_to_untixtime($date_end, true);
        else
            $date_end_dmy = $_GET['date_end_dmy'];

        $_where = "";
        if( $IDPartner )
            $_where .= " AND IDPartner=".mysql_real_escape_string($IDPartner)."";

         //Get stat
        $i 			=		0;
        $stat		=		array();

        $limit  = $kernel->pub_httpget_get('limit');
        if (empty($limit) || !is_numeric($limit))
        {
            if ($show_diagram)
                $limit = 4;
            else
            	$limit = 20;
        }
        $offset = $kernel->pub_httpget_get('offset');
        if (empty($offset) || !is_numeric($offset))
            $offset = 0;

        $query = 'SELECT '
        . ' COUNT(`'.$kernel->pub_prefix_get().'_stat_word`.`IDWord`) AS `cword`, '
        . ' `'.$kernel->pub_prefix_get().'_stat_word`.`word` '
        . ' FROM `'.$kernel->pub_prefix_get().'_stat_word` '
        . ' WHERE `'.$kernel->pub_prefix_get().'_stat_word`.`tstc` BETWEEN '.mysql_real_escape_string($date_start_dmy).' AND '.mysql_real_escape_string($date_end_dmy).' '.$_where
        . ' GROUP BY `'.$kernel->pub_prefix_get().'_stat_word`.`word` '
        . ' ORDER BY `cword` DESC '
        . ' LIMIT '.mysql_real_escape_string($limit).' OFFSET '.mysql_real_escape_string($offset);

        $result = $kernel->runSQL($query);
        while( $qa = mysql_fetch_array($result) )
        {
            $stat[$i]["word"]   = $qa["word"];
            $stat[$i]["count"]  = $qa["cword"];

            $max_count          = 0;
            $max_count_referer  = "";

            $sql = "SELECT COUNT(".$kernel->pub_prefix_get()."_stat_word.IDWord) as cword,
                            ".$kernel->pub_prefix_get()."_stat_search.search,
                            ".$kernel->pub_prefix_get()."_stat_referer.referer,
                            ".$kernel->pub_prefix_get()."_stat_search.IDSearch
                    FROM ".$kernel->pub_prefix_get()."_stat_word
                    LEFT JOIN ".$kernel->pub_prefix_get()."_stat_search USING (IDSearch)
                    LEFT JOIN ".$kernel->pub_prefix_get()."_stat_referer
                    ON (".$kernel->pub_prefix_get()."_stat_referer.IDReferer=".$kernel->pub_prefix_get()."_stat_word.IDReferer)
                    WHERE ".$kernel->pub_prefix_get()."_stat_word.word='".mysql_real_escape_string($stat[$i]["word"])."'
                    GROUP BY ".$kernel->pub_prefix_get()."_stat_word.IDSearch
                    ORDER BY ".$kernel->pub_prefix_get()."_stat_search.search";

			$result1 = $kernel->runSQL($sql);
			while ($qa1 = mysql_fetch_array($result1))
			{
				$stat[$i]["search"][] = array("search" => $qa1["search"], "count" => $qa1["cword"], "referer" => $qa1["referer"]);

				if( $max_count < $qa1["cword"] )
				{
					$max_count			=	$qa1["cword"];
					$max_count_referer	=	$qa1["referer"];
				}
			}
			mysql_free_result($result1);

			$stat[$i]["referer"] = $max_count_referer;
			$i++;
        }
        mysql_free_result($result);

        $template = $kernel->pub_template_parse("admin/templates/default/statnew_word.html");

        //Проверим, есть ли что выводить
        if (empty($stat))
		    return $template['data_empty'];

		$_SESSION["stat"] = $stat;


		//Начали вывод информации
        //Шапка таблицы
        $html = $template['begin'];
        $html .= $template['tab_begin'];
        if( $show_diagram )
            $html .= $template['tab_image'];

        $html .= $template['tab_end'];

        //формирование строчных частей
        for ($i=0; $i<count($stat); $i++ )
        {
	        $html_str = $template['tr_begin'];
	        if( $show_diagram )
	           $html_str .= $template['tr_image'];

	        $link = htmlspecialchars($stat[$i]["referer"], ENT_QUOTES);
	        $link_image = $kernel->pub_redirect_for_form("get_word_img_data&cur_id_key=".$i);
	        $html_str = str_replace("%set_class_tr%", $this->get_type_class_tr($i), $html_str);
	        if (isset($offset))
	           $html_str = str_replace("%num%", ($offset+$i+1), $html_str);
	        else
	           $html_str = str_replace("%num%", ($i+1), $html_str);

	        $html_str = str_replace("%link%", $link, $html_str);
	        $html_str = str_replace("%name_link%", $stat[$i]["word"], $html_str);
	        $html_str = str_replace("%count%",  $stat[$i]["count"],  $html_str);

            require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open_flash_chart_object.php');
    		$html_str = str_replace("%link_image%", open_flash_chart_object_str(300, 150, $kernel->pub_redirect_for_form($link_image), false, '../components/ofc/'), $html_str);
	        $html .= $html_str.$template['tr_end'];
        }

        $html .= $template['end'];

        $params = array(
            'date_start_dmy' => $date_start_dmy,
            'date_end_dmy' => $date_end_dmy
        );
        if ($show_diagram)
        	$params['show_diagram'] = 1;

        $html = str_replace('%pages%', $this->pages_build($template, $limit, $offset, $this->keywords_total_get($date_start_dmy, $date_end_dmy, $_where), 'get_word', $params), $html);
    	return $html;
    }

    function get_top_pages()
    {
        global $kernel;

    	$action = $kernel->pub_httppost_get();
        $templates = $kernel->pub_template_parse("admin/templates/default/statnew_show_top_pages.html");

        $date_start   = isset($action["date_start"])?$action["date_start"]:"";
        $date_end     = isset($action["date_end"])?$action["date_end"]:"";

        if (empty($_GET['date_start_dmy']))
   		   $date_start_dmy = $this->date_to_untixtime($date_start);
        else
            $date_start_dmy = $_GET['date_start_dmy'];
        if (empty($_GET['date_end_dmy']))
            $date_end_dmy = $this->date_to_untixtime($date_end, true);
        else
            $date_end_dmy = $_GET['date_end_dmy'];

        $limit  = $kernel->pub_httpget_get('limit');
        if (empty($limit) || !is_numeric($limit))
            $limit = 60;
        $offset = $kernel->pub_httpget_get('offset');
        if (empty($offset) || !is_numeric($offset))
            $offset = 0;

    	$query = 'SELECT COUNT( `IDUri` ) AS `visits` , `uri` '
        . ' FROM `'.$kernel->pub_prefix_get().'_stat_uri` '
        . ' WHERE `tstc` BETWEEN '.mysql_real_escape_string($date_start_dmy).' AND '.mysql_real_escape_string($date_end_dmy)
        . ' GROUP BY `uri` '
        . ' ORDER BY `visits` DESC '
        . ' LIMIT '.$limit.' OFFSET '.$offset;
        $result = $kernel->runSQL($query);

        $content = $templates['table'];

        $rows = array();
        $order = $offset;
        while ($data = mysql_fetch_assoc($result))
        {
        	$row = $templates['row'];

        	$path  = trim(parse_url($data['uri'], PHP_URL_PATH));
            preg_match('/^\/?([\w\d]+)\./', $path, $subpatterns);
            if (isset($subpatterns[1]))
                $page_id = $subpatterns[1];
            else
            	$page_id = 'index';

            if (!is_null($page_id))
            {
            	$caption = $kernel->pub_page_property_get($page_id, 'caption');
                $row = str_replace('%label%', $caption['value'], $row);
            }
            else
            	$row = str_replace('%label%', $data['uri'], $row);
        	$row = str_replace('%order%', ++$order, $row);
        	$row = str_replace('%class%', $kernel->pub_table_tr_class($order), $row);
        	$row = str_replace('%visits%', $data['visits'], $row);
        	$row = str_replace('%url%', $data['uri'], $row);
        	$rows[] = $row;
        }

        $content = str_replace('%rows%', implode("\n", $rows), $content);
        $params = array(
            'date_start_dmy' => $date_start_dmy,
            'date_end_dmy' => $date_end_dmy
        );
        $content = str_replace('%pages%', $this->pages_build($templates, $limit, $offset, $this->top_pages_total_get($date_start_dmy, $date_end_dmy), 'get_top_pages', $params), $content);
        return $content;
    }

    function top_pages_total_get($date_start_dmy = null, $date_end_dmy = null)
    {
    	global $kernel;
        //@todo use count(*) here!
    	if (is_numeric($date_start_dmy) && is_numeric($date_end_dmy))
        {
            $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_stat_uri` '
            . ' WHERE `'.$kernel->pub_prefix_get().'_stat_uri`.`tstc` BETWEEN '.mysql_real_escape_string($date_start_dmy).' AND '.mysql_real_escape_string($date_end_dmy).' '
            . ' GROUP BY `uri`';

    	}
        else
    	   $query = 'SELECT * FROM `'.$kernel->pub_prefix_get().'_stat_uri` GROUP BY `uri`';
    	$result = $kernel->runSQL($query);
    	return mysql_num_rows($result);
    }

    function pages_build($templates, $limit, $offset, $total, $action, $params = array())
    {
        $pages     = array();
        $aditional = array();
        if (!empty($params))
        {
            foreach ($params as $param => $value)
            {
                $aditional[] = $param.'='.$value;
            }
        }

        for ($i = 0; $i < $total; $i += $limit)
        {
            if ($i == $offset)
                $page = $templates['page_active'];
            else
                $page = $templates['page_passive'];
            $url = '/admin/index.php?action=set_left_menu&leftmenu='.$action.'&limit='.$limit.'&offset='.$i.(isset($_GET['id'])?'&id='.$_GET['id']:'').(!empty($aditional)?'&'.implode('&', $aditional):'');
            $page = str_replace('%url%', $url, $page);
            $page = str_replace('%page%', (($i / $limit) + 1), $page);
            $pages[] = $page;
        }
        if (count($pages) <= 1)
            return '';
        $pages = implode($templates['page_delimeter'], $pages);

        return $pages;
    }

    function keywords_total_get($date_start_dmy, $date_end_dmy, $_where)
    {
    	global $kernel;
        $query = 'SELECT '
        . ' COUNT(`'.$kernel->pub_prefix_get().'_stat_word`.`IDWord`) AS `cword`, '
        . ' `'.$kernel->pub_prefix_get().'_stat_word`.`word` '
        . ' FROM `'.$kernel->pub_prefix_get().'_stat_word` '
        . ' WHERE `'.$kernel->pub_prefix_get().'_stat_word`.`tstc` BETWEEN '.mysql_real_escape_string($date_start_dmy).' AND '.mysql_real_escape_string($date_end_dmy).' '.$_where
        . ' GROUP BY `'.$kernel->pub_prefix_get().'_stat_word`.`word` '
        . ' ORDER BY `cword` DESC';
    	$result = $kernel->runSQL($query);
    	$total = mysql_num_rows($result);
        //@todo use count(*)
    	return $total;
    }


    /**
	 * Выводит форму и формирует отчет по прямым заходом на сайт
     *
     * @return string
     */
    function get_ip()
    {
    	global $kernel;
    	$action = $kernel->pub_httppost_get();
        $date_start   = isset($action["date_start"])?$action["date_start"]:"";
        $date_end     = isset($action["date_end"])?$action["date_end"]:"";
   		$date_start_dmy = $this->date_to_untixtime($date_start);
        $date_end_dmy   = $this->date_to_untixtime($date_end, true);
        $_where		=	"";
        $stat		=		array();
        $sql = "SELECT COUNT(".$kernel->pub_prefix_get()."_stat_host.IDHost) as cip,
                        ".$kernel->pub_prefix_get()."_stat_host.ip
                FROM ".$kernel->pub_prefix_get()."_stat_host
                WHERE ".$kernel->pub_prefix_get()."_stat_host.tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." AND IDReferer=0 $_where
                GROUP BY ".$kernel->pub_prefix_get()."_stat_host.iplong
                ORDER BY cip DESC";

        $result = $kernel->runSQL($sql);
        while( $qa = mysql_fetch_array($result) )
            $stat[]		=	array("ip" => "$qa[ip]", "count" => "$qa[cip]");

        mysql_free_result($result);

        $template = $kernel->pub_template_parse("admin/templates/default/statnew_ip.html");

        //Проверим, есть ли что выводить
        if (empty($stat))
            return $template['data_empty'];

        //Шапка таблицы
        $html = $template['begin'];

        //формирование строчных частей
        for ($i=0; $i<count($stat); $i++ )
        {
	        $html_str = $template['tr'];
	        $link = "http://www.whois.sc/".htmlspecialchars($stat[$i]["ip"], ENT_QUOTES);
	        $html_str = str_replace("%set_class_tr%", $this->get_type_class_tr($i), $html_str);
	        $html_str = str_replace("%num%", ($i+1), $html_str);
	        $html_str = str_replace("%link%", $link, $html_str);
	        $html_str = str_replace("%name_link%", $stat[$i]["ip"], $html_str);
	        $html_str = str_replace("%count%",  $stat[$i]["count"],  $html_str);
	        $html .= $html_str;
        }
        $html .= $template['end'];
    	return $html;
    }

    /**
	 * Выводит форму и формирует отчет по индексированию сайта роботами
     *
     * @return string
     */

    function get_index()
    {
    	global $kernel;

    	$html = '';
        $action       = $kernel->pub_httppost_get();

        $date_start   = isset($action["date_start"])?$action["date_start"]:"";
        $date_end     = isset($action["date_end"])?$action["date_end"]:"";


   		$date_start_dmy = $this->date_to_untixtime($date_start);
        $date_end_dmy   = $this->date_to_untixtime($date_end, true);

        //Get stat
        $stat   = array();
        $robots = array();

        $sql = "SELECT FROM_UNIXTIME(".$kernel->pub_prefix_get()."_stat_index.tstc, '%d-%m-%Y') as tstc_date,
                       COUNT(".$kernel->pub_prefix_get()."_stat_index.IDIndex) as cindex,
                       ".$kernel->pub_prefix_get()."_stat_index.IDRobot,
                       ".$kernel->pub_prefix_get()."_stat_robot.robot
                FROM ".$kernel->pub_prefix_get()."_stat_index
                LEFT JOIN ".$kernel->pub_prefix_get()."_stat_robot USING (IDRobot)
                WHERE ".$kernel->pub_prefix_get()."_stat_index.tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)."
                GROUP BY tstc_date, ".$kernel->pub_prefix_get()."_stat_index.IDRobot
                ORDER BY tstc_date";

        $result = $kernel->runSQL($sql);
        $tmp_array = array();
        while( $qa = mysql_fetch_array($result))
        {
            $data    = $qa["tstc_date"];
            $cindex  = $qa["cindex"];
            $IDRobot = $qa["IDRobot"];

        	$stat["$data"]["$IDRobot"] = $cindex;
        	if (!isset($tmp_array[$IDRobot]))
        	{
        	   $robots[] = array("IDRobot" => "$IDRobot", "robot"=>"$qa[robot]");
        	   $tmp_array[$IDRobot] = $qa['robot'];
        	}
        }
        mysql_free_result($result);


        $template = $kernel->pub_template_parse("admin/templates/default/statnew_index.html");
        //Проверим, есть ли что выводить
        if (empty($stat))
            return $template['data_empty'];

        $_SESSION["stat"]	=	$stat;
        $_SESSION["robots"]	=	$robots;

        //Начинаем вывод статистики по роботам
		$html .= $template['begin'];

		//Выведем график
        require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open_flash_chart_object.php');
		$html .= str_replace("%link_image%", open_flash_chart_object_str(500, 400, $kernel->pub_redirect_for_form("get_index_img"), false, '../components/ofc/'), $template['image']);

        //Сформируем шапку таблицы
        $html .= $template['th_begin'];
        foreach ($robots as $val)
        {
            $html_str = $template['th_tr'];
            $html_str = str_replace("%label_robor%", $val["robot"], $html_str);
            $html .= $html_str;
        }
        $html .= $template['th_end'];


        //Теперь надо выводить строки по дням
        $i = 0;
        foreach ($stat as $show_data => $val )
        {
            $html_str = $template['tr_begin'];
	        $html_str = str_replace("%set_class_tr%", $this->get_type_class_tr($i), $html_str);
        	$html_str = str_replace("%date%", $show_data, $html_str);

        	//Теперь провреим, будем формировать колокни роботов, если на них
        	//есть данные
        	foreach ($robots as $val_rob)
        	{
        	    $link = '';
        	    $count = '';
        	    $IDRobot = $val_rob["IDRobot"];
        	    if (isset($val[$IDRobot]))
        	    {
        	        $robot = $val_rob["robot"];
            	    $link = $kernel->pub_redirect_for_form("show_indexed_page&IDRobot=$IDRobot&robot=$robot&indexed_date=$show_data");
            	    $count = $val[$IDRobot];
        	    }
                $tmp_html = $template['tr_tr'];
	            $tmp_html = str_replace("%link%",  $link,  $tmp_html);
	            $tmp_html = str_replace("%count%", $count, $tmp_html);
	            $html_str .= $tmp_html;
        	}
        	$html_str .= $template['tr_end'];

        	$html .= $html_str;
        	$i++;
        }
        $html .= $template['end'];
    	return $html;
    }

    function show_indexed_page()
    {
    	global $kernel;

    	$html = '';
    	$action		=	$kernel->pub_httpget_get();
    	$IDRobot		=	isset($action["IDRobot"])?$action["IDRobot"]:0;
    	$indexed_date	=	isset($action["indexed_date"])?$action["indexed_date"]:0;
   		$date_start_dmy = $this->date_to_untixtime(date("d-m-Y"));
        $date_end_dmy   = $this->date_to_untixtime(date("d-m-Y"), true);
    	if( $indexed_date)
		{
   		    $date_start_dmy = $this->date_to_untixtime($indexed_date);
            $date_end_dmy   = $this->date_to_untixtime($indexed_date, true);
       	}

    	$template = $kernel->pub_template_parse("admin/templates/default/statnew_index.html");
		$html .= $template['begin_robot'];
        $sql = "SELECT COUNT(".$kernel->pub_prefix_get()."_stat_uri.uri) as curi,
                       ".$kernel->pub_prefix_get()."_stat_uri.uri,
                       ".$kernel->pub_prefix_get()."_stat_robot.robot
                FROM ".$kernel->pub_prefix_get()."_stat_index
                LEFT JOIN ".$kernel->pub_prefix_get()."_stat_uri USING (IDUri)
                LEFT JOIN ".$kernel->pub_prefix_get()."_stat_robot ON (".$kernel->pub_prefix_get()."_stat_robot.IDRobot=".$kernel->pub_prefix_get()."_stat_index.IDRobot)
                WHERE ".$kernel->pub_prefix_get()."_stat_index.tstc BETWEEN ".mysql_real_escape_string($date_start_dmy)." AND ".mysql_real_escape_string($date_end_dmy)." AND ".$kernel->pub_prefix_get()."_stat_index.IDRobot=".mysql_real_escape_string($IDRobot)."
                GROUP BY ".$kernel->pub_prefix_get()."_stat_uri.uri
                ORDER BY ".$kernel->pub_prefix_get()."_stat_index.IDIndex";

        $result = $kernel->runSQL($sql);
        $i = 0;
        while ($qa = mysql_fetch_array($result) )
        {
	        $html_str = $template['tr_robot'];
	        $link = htmlspecialchars($qa['uri'], ENT_QUOTES);
	        $html_str = str_replace("%set_class_tr%", $this->get_type_class_tr($i), $html_str);
	        $html_str = str_replace("%num%",          ($i+1), $html_str);
	        $html_str = str_replace("%link%",         $link, $html_str);
	        $html_str = str_replace("%name_link%",    $qa['uri'], $html_str);
	        $html_str = str_replace("%count%",        $qa["curi"],  $html_str);
	        $html .= $html_str;
            $i++;
        }
        mysql_free_result($result);
        $html .= $template['end_robot'];
        return $html;
    }


    /**
     * Отображает форму для управления доменами, являющимися доменами сайта
     *
     * @return string
     */
    function show_domain($form = true)
    {
    	global $kernel;
        $template = $kernel->pub_template_parse("admin/templates/default/statnew_domain.html");
    	//Таблица существующих доменов
    	$query = "SELECT ".$kernel->pub_prefix_get()."_stat_domain.* FROM ".$kernel->pub_prefix_get()."_stat_domain ORDER BY domain";
    	$q = $kernel->runSQL($query);
    	$domain_list = "";
    	$html_table = $template['table_damain'];
    	while ($qa = mysql_fetch_array($q))
    	{
    	    $str = $template['one_damain'];
    	    $str = str_replace("[#name#]", $qa['domain'],$str);
    	    $str = str_replace("[#id#]", $qa['IDDomain'],$str);
    	    $str = str_replace("[#alert_del#]",'[#statist_domain_alert_del_start#] &quot;'.$qa['domain'].'&quot; [#statist_domain_alert_del_end#]',$str);
    	    $domain_list .= $str;
    	}
    	$html_table = str_replace("[#domain_list#]", $domain_list, $html_table);
    	$html = $html_table;
        if ($form)
        {
            $html = $template['begin'];
            $html = str_replace("[#table_list#]", $html_table, $html);
    	    $html = str_replace("%link_form%", $kernel->pub_redirect_for_form('add_domain'), $html);
        }
    	return $html;
    }

    /**
     * Выводит форму и управляет партнерами, используемыми в статистике
     *
     * @param integer $IDPartner
     * @return string
     */
    function show_partner($IDPartner)
    {
    	global $kernel;
    	$action = $kernel->pub_httppost_get();
    	if(isset($action["add_partner"]) )
    	{
    		if( $action["partner"] )
    		{
                if (mb_strlen($action['partner'])>64)
                    $action['partner']=mb_substr($action['partner'],0,64);
                if( !$kernel->runSQL("INSERT INTO ".$kernel->pub_prefix_get()."_stat_partner (IDPartner, partner, tstc) VALUES (0, '".mysql_real_escape_string($action['partner'])."', UNIX_TIMESTAMP())") )
                    echo "<BR>".mysql_error()."<BR>";
                else
                	$kernel->pub_redirect_refresh_reload('show_partner');
    		}
    	}

    	if ( isset($action["del_partner"]) )
    	{
    		if( !$kernel->runSQL("DELETE FROM ".$kernel->pub_prefix_get()."_stat_partner WHERE IDPartner='".mysql_real_escape_string($action['IDPartner_select'])."'") )
                echo "<BR>".mysql_error()."<BR>";
    		else
                $kernel->pub_redirect_refresh_reload('show_partner');
    	}
    	if( isset($action["add_preg_partner"]) )
    	{
            if (mb_strlen($action['preg_partner'])>64)
                $action['preg_partner']=mb_substr($action['preg_partner'],0,64);
    		if( !$kernel->runSQL("INSERT INTO ".$kernel->pub_prefix_get()."_stat_partner_eregs (IDPEregs, IDPartner, preg_partner) VALUES (0, ".intval($action['IDPartner_select']).", '".mysql_real_escape_string($action['preg_partner'])."')") )
    		    echo "<BR>".mysql_error()."<BR>";
    		else
                $kernel->pub_redirect_refresh_reload('show_partner');
    	}

    	if (isset($action["del_preg_partner"]) && (isset($action["IDPEregs"])))
    	{
    		if( !$kernel->runSQL("DELETE FROM ".$kernel->pub_prefix_get()."_stat_partner_eregs WHERE IDPartner=".mysql_real_escape_string($action['IDPartner_select'])." AND IDPEregs=".mysql_real_escape_string($action['IDPEregs'])."") )
    		    echo "<BR>".mysql_error()."<BR>";
    		else
                $kernel->pub_redirect_refresh_reload('show_partner');
    	}

    	if ($kernel->pub_httpget_get('partner_id'))
        	$IDPartner = $kernel->pub_httpget_get('partner_id');

    	$partner_list	=	"";
    	if( !$q = $kernel->runSQL("SELECT ".$kernel->pub_prefix_get()."_stat_partner.* FROM ".$kernel->pub_prefix_get()."_stat_partner ORDER BY partner") )
            echo "<BR>".mysql_error()."<BR>";
        while( $qa = mysql_fetch_array($q))
        {
            if( !$IDPartner)
                $IDPartner	=	$qa["IDPartner"];
            $partner_list	.=	"<OPTION value='$qa[IDPartner]' ".(($qa["IDPartner"] == $IDPartner)?"selected":"").">$qa[partner]</OPTION>\n";
        }
    	mysql_free_result($q);

    	$preg_partner_list	=	"";
    	if( !$q = $kernel->runSQL("SELECT ".$kernel->pub_prefix_get()."_stat_partner_eregs.* FROM ".$kernel->pub_prefix_get()."_stat_partner_eregs WHERE ".$kernel->pub_prefix_get()."_stat_partner_eregs.IDPartner=".mysql_real_escape_string($IDPartner)." ORDER BY preg_partner") )
            echo "<BR>".mysql_error()."<BR>";
        while( $qa = mysql_fetch_array($q))
        {
            $preg_partner_list	.=	"<OPTION value='$qa[IDPEregs]'>$qa[preg_partner]</OPTION>\n";
        }
    	mysql_free_result($q);

    	$form		=	 	file_get_contents("admin/templates/default/statnew_partner.html");
    	$form		=		str_replace('%form_url%', $kernel->pub_redirect_for_form('show_partner&IDPartner=[%IDPartner%]'), $form);
    	$form		=		str_replace("[%IDPartner%]",	 		"$IDPartner", $form);
    	$form		=		str_replace("[%partner_list%]",	 	"$partner_list", $form);
    	$form		=		str_replace("[%preg_partner_list%]",	"$preg_partner_list", $form);
    	return $form;
    }


    function get_chart_data()
    {
        global $kernel;

        $query = 'SELECT '
        . ' FROM_UNIXTIME('.$kernel->pub_prefix_get().'_stat_index.tstc, "%d-%m-%Y") as tstc_date, '
        . ' COUNT('.$kernel->pub_prefix_get().'_stat_index.IDIndex) as cindex, '
        . ' '.$kernel->pub_prefix_get().'_stat_index.IDRobot, '
        . ' '.$kernel->pub_prefix_get().'_stat_robot.robot '
        . ' FROM '.$kernel->pub_prefix_get().'_stat_index '
        . ' LEFT JOIN '.$kernel->pub_prefix_get().'_stat_robot USING (IDRobot) '
        . ' WHERE '.$kernel->pub_prefix_get().'_stat_index.tstc '
        . ' BETWEEN '.mysql_real_escape_string($this->date_to_untixtime($_SESSION['stat_date_start'])).' AND '.mysql_real_escape_string($this->date_to_untixtime($_SESSION['stat_date_end'], true)).' '
        . ' GROUP BY tstc_date, '.$kernel->pub_prefix_get().'_stat_index.IDRobot '
        . ' ORDER BY tstc_date';

        $result = $kernel->runSQL($query);

        $data = array();
        while ($row = mysql_fetch_assoc($result))
        {
            $data[$row['IDRobot']]['name'] = $row['robot'];
            $data[$row['IDRobot']]['data'][$row['tstc_date']] = $row['cindex'];
        }

        require_once(dirname(dirname(__FILE__)).'/components/ofc/php-ofc-library/open-flash-chart.php' );
        $g = new graph();

        if (($this->date_to_untixtime($_SESSION['stat_date_end'], true) - $this->date_to_untixtime($_SESSION['stat_date_start'])) >= 60*60*24*2 )
        {
            $g->bg_colour = '#FFFFFF';
            foreach ($data as $value)
            {
                $g->set_data(array_merge($this->date_list($this->date_to_untixtime($_SESSION['stat_date_start']), $this->date_to_untixtime($_SESSION['stat_date_end'])), $value['data']));
                $g->line( 2, '0x'.rand(000000, 999999), $value['name'], 10 );
            }

            $g->set_x_label_style( 10, '0x000000', 0, ceil(count($this->date_list($this->date_to_untixtime($_SESSION['stat_date_start']), $this->date_to_untixtime($_SESSION['stat_date_end']))) / 5) );
            $g->set_tool_tip( '#key#: #val#<br>#x_label# #x_legend#' );
            $g->set_y_max( 200 );
            $g->y_label_steps( 4 );
        }
        else
        {
            $max = array();
            foreach ($data as $value)
            {
                $bar = new bar( 50, '#'.rand(0,999999) );
                $bar->key( $value['name'], 10 );
                $bar->data = array_merge($this->date_list($this->date_to_untixtime($_SESSION['stat_date_start']), $this->date_to_untixtime($_SESSION['stat_date_end'])), $value['data']);
                $g->data_sets[] = $bar;
                $max = array_merge($max, array_values($value['data']));
            }

            $g->set_x_label_style( 10, '#9933CC', 0, 2 );
            $g->set_x_axis_steps( 2 );

            $g->set_y_max( max($max) + 5 );
            $g->y_label_steps( 2 );
        }

        $g->bg_colour = '#FFFFFF';
        $g->x_axis_colour( '#E0EDFD', '#E0EDFD' );
        $g->y_axis_colour( '#E0EDFD', '#E0EDFD' );

        $g->set_x_labels( array_keys($this->date_list($this->date_to_untixtime($_SESSION['stat_date_start']), $this->date_to_untixtime($_SESSION['stat_date_end']))) );

        return $g->render();
    }

    function date_list($start, $stop)
    {
        $array = array();
        while ($start <= ($stop+86399))
        {
            $array[date('d-m-Y', $start)] = 0;
            $start = $start + 86400;
        }
        return $array;
    }
}
?>