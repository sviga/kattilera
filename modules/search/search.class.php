<?php
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";

class search extends BaseModule
{
	public $admin_template_path = "modules/search/templates_admin";
	public $path_templates = "modules/search/templates_user"; //Путь к шаблонам модуля

	public $section_id;

    /** @var Indexator */
	public $indexator;

    private $offset_param_name="so";


	function search()
    {
		require_once("include/indexator.class.php");
		require_once("include/searcher.class.php");

		require_once("include/urlparser.class.php");
		require_once("include/webcontentparser.class.php");
		require_once("include/htmlparser.class.php");


		require_once("include/searchdb.class.php");

		require_once("include/lingua_stem_ru.class.php");

		require_once("include/pdfparser/pdfobject.class.php");
		require_once("include/pdfparser/type1encoding/win-1251.inc.php");
		require_once("include/pdfparser/dictionaryparser.class.php");
		require_once("include/pdfparser/kvadrpdfobject.class.php");
		require_once("include/pdfparser/pdfparser.class.php");
		require_once("include/pdfparser/spacepdfobject.class.php");
		require_once("include/pdfparser/ugolpdfobject.class.php");
        require_once("cook/includes/downloader.class.php");
        require_once("cook/includes/curldownloader.class.php");
        require_once("cook/includes/downloaderresult.class.php");
        require_once("cook/includes/responsecontent.class.php");
        require_once("cook/includes/responseheaders.class.php");
		$this->indexator 	= new Indexator();
    }



    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************

    function pub_show_only_form($template)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($template));
        $html = $this->get_template_block('form');
        //Узнаем на какой странице форма поиска.
        $prop = $kernel->pub_modul_properties_get('page_search');
        $prop = $prop['value'];
        $html = str_replace('%action%', '/'.$prop, $html);
        return $html;
    }


    /**
     * Публичный метод для осуществелния поиска и просмотра результатов
     *
     * @param string $template_search Шаблон совершенного поиска
     * @return string
     */
	function pub_show_search_results($template_search)
    {
        global $kernel;
        //Определим сначала, есть ли поисковое слово во всех местах
        $search_text = $kernel->pub_httpget_get('search');
        if (empty($search_text))
        	$search_text = $kernel->pub_httppost_get('search');

        //Странице перекидывания возьмём из настроек
        $page_search = $kernel->pub_modul_properties_get('page_search');
        if (!empty($page_search['value']))
            $page_search = "/".$page_search['value'];
        else
            $page_search = "";

		$offset = intval($kernel->pub_httpget_get($this->offset_param_name));

		$parameters = $this->get_search_parameters();

        $perPage=$parameters['results_per_page'];
		/*************** Собственно поиск ******************/
		/* @var $searcher Searcher */
		$searcher = new Searcher();

		$searcher->set_results_per_page($perPage);
		$searcher->set_doc_format($parameters['doc_format']);
		$searcher->set_operation($parameters['operation']);

		$results = $searcher->search($search_text, $offset);

        $this->set_templates($kernel->pub_template_parse($template_search));
		//Теперь будем формировать результат

        //Сформируем HTML формы поиска
        $html_form_serch = $this->get_template_block('search_form');
        $html_form_serch = str_replace('%action%', $page_search, $html_form_serch);


        //Теперь узнаем есть ли результат поиска и сформируем подготовим его для вывода
        $html_result = $this->get_template_block('noresult');
		if (!empty($results))
		{
			$html_result = $this->get_template_block('search_results');
    		$num = 0;
    		$result_parts = array();
    		$result_template = $this->get_template_block('search_results_rows');
    		foreach ($results as $result)
    		{
    			$num++;
    			$result_html = $result_template;
    			$result_html = preg_replace("'%link%'i"    , $result['url']    , $result_html);
    			$result_html = preg_replace("'%title%'i"   , $result['title']  , $result_html);
    			$result_html = preg_replace("'%snipped%'i" , $result['snipped'], $result_html);
    			$result_html = preg_replace("'%num%'i"     , $result['num']    , $result_html);
    			$result_parts[] = $result_html;
    		}
		    $html_result = str_replace("%rows%", join($this->get_template_block('search_results_split'), $result_parts), $html_result);
		}


		//Теперь пришло время всё собрать воедино
		$html = $this->get_template_block('form');

		$html = str_replace("%search_results%", $html_result     , $html);
		$html = str_replace("%search_form%"   , $html_form_serch , $html);

        $total = $searcher->number_of_results;
        $purl = $kernel->pub_page_current_get()."?search=".urlencode($search_text)."&".$this->offset_param_name."=";
		$html = str_replace("%pages%", $this->build_pages_nav($total,$offset,$perPage,$purl,0) , $html);

		//В итоговом шаблоне могла остаться переменная с поисковым словом, заменим её
        $html = str_replace("%search_text%", htmlspecialchars($search_text), $html);
		return $html;
    }


    //***********************************************************************
    //	Наборы внутренних методов модуля
    //**********************************************************************


    function get_search_parameters()
    {
        $parameters =
            array(
                'operation' => 'or',
                'results_per_page' => 10,
                'doc_format' => 'any'
            );

        if (isset($_GET['operation']) && $_GET['operation'] == 'and')
            $parameters['operation'] = 'and';

        if (isset($_GET['results_per_page']))
        {
            $results_per_page = intval($_GET['results_per_page']);
            if ($results_per_page > 0 && $results_per_page <= 100)
                $parameters['results_per_page'] = $results_per_page;
        }

        if (isset($_GET['doc_format']))
            $parameters['doc_format'] = $_GET['doc_format'];
        return $parameters;
    }


    //***********************************************************************
    //	Наборы методов для работы с админкой модуля
    //**********************************************************************


    /**
     * @param pub_interface $menu
     * @return bool
     */
	function interface_get_menu($menu)
	{
        $menu->set_menu_block('[#serch_admin_leftmenu_caption#]');
        $menu->set_menu("[#search_admin_index#]","index");
        $menu->set_menu("[#search_ignored_menuitem#]","ignored");
        $menu->set_menu_default('index');
	    return true;
	}

	/**
	 * Предопределйнный метод, используется для вызова административного интерфейса модуля
	 */
	function start_admin()
	{
		global $kernel;

        //Узнаем, нужно ли менять объём памяти и если надо - поменяем
        //И поставим отметку о том что поменяли
        $php_mem = $kernel->pub_modul_properties_get('php_mem');
        $php_mem = intval($php_mem['value']);
        $php_mem_curent = intval(ini_get('memory_limit'));
        $php_mem_change = false;
        if (($php_mem > 0) && ($php_mem_curent < $php_mem))
        {
            ini_set('memory_limit', $php_mem.'M');
            if (intval(ini_get('memory_limit')) == $php_mem)
                $php_mem_change = true;
        }
        $html='';
        $action = $kernel->pub_section_leftmenu_get();
	    switch ($action)
	    {
	        case "start_index":

                $dir = getcwd();
				chdir("modules/search");
				//Перед индексацией, надо авторизироваться на сайте, что бы был доступен весь контент.

				//Если указаны параметры авторизации
				$tmp = $kernel->pub_modul_properties_get('user_name');
				$user_name = $tmp['value'];
				$tmp = $kernel->pub_modul_properties_get('user_pass');
				$user_pass = $tmp['value'];
				//Надо авторизироваться для этого пользователя
				$cookie_header = false;
				if ((!empty($user_name)) && (!empty($user_pass)))
				{
				    //Так как индексатор работает по другому, то нам надо авторизироваться
				    //на сайте и получить куку сессии
                    $curl_downloader = new CurlDownloader();
                    $headers = array
                    (
                        'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; ru; rv:1.8.1.4) Gecko/20070515 Firefox/2.0.0.4'
                    );
                    $curl_downloader->set_headers($headers);
                    /* @var $result downloaderresult */
                    $result = $curl_downloader->post("http://www.santafox.ru/", 'login='.$user_name.'&pass='.$user_pass);
                    $cookies = $result->responseheaders->get_simple_cookies();
                    reset($cookies);
                    list($k, $v) = each($cookies);
                    $cookie_header = "Cookie: $k=$v";
				    //Теперь $cookie_header - то что нужно добовлять к запросу

				}
				//И индексировать начнём со страницы именного этого пиоска
                $page_search = $kernel->pub_modul_properties_get('page_search');
                if (!empty($page_search['value']))
                    $page_search = $page_search['value'];
                else
                    $page_search = "";

                $first_time = $kernel->pub_httpget_get("firsttime");
                if (!empty($first_time))
                    $this->indexator->clear_index_data();
		        $html = $this->indexator->index_site('http://'.$_SERVER['HTTP_HOST']."/".$page_search, $cookie_header);
				chdir($dir);

	           break;

	        case 'index':
				$html = file_get_contents("$this->admin_template_path/search.html");
				$html = str_replace('%form_action%', $kernel->pub_redirect_for_form('index'), $html);
				$html = str_replace('%index_page%', searchdb::count_pages(), $html);
				$html = str_replace('%index_word%', searchdb::count_words(), $html);
			    $html = str_replace('%php_mem%', ini_get('memory_limit'), $html);
			    if (intval(ini_get('max_execution_time')) > 0)
                    $html = str_replace('%script_life%', ini_get('max_execution_time').' s', $html);
                else
			        $html = str_replace('%script_life%', '&infin;', $html);

			    if ($php_mem_change)
                    $html = str_replace('%is_change%', '[#search_admin_table_php_mem_change#]', $html);
                else
                    $html = str_replace('%is_change%', '', $html);

	            break;

	        case 'ignored_delete':
        	    $searchdb = new searchdb($kernel->pub_prefix_get()."_".$kernel->pub_module_id_get());
        	    $searchdb->delete_ignored_string($kernel->pub_httpget_get("id"));
        	    $kernel->pub_redirect_refresh("ignored");
	            break;
	        case 'ignored_add':
        	    $searchdb = new searchdb($kernel->pub_prefix_get()."_".$kernel->pub_module_id_get());
        	    $searchdb->add_ignored_string($kernel->pub_httppost_get("istring"));
        	    return $kernel->pub_httppost_response("[#search_ignored_added_msg#]","ignored");
	            break;

	        case 'ignored':
	            $ptemplate = $kernel->pub_template_parse($this->admin_template_path."/ignored.html");
	            $html = $ptemplate['table_header'];
	            $istrings = searchdb::get_ignored_strings();
	            foreach ($istrings as $istring)
	            {
	                $line = $ptemplate['table_line'];
	                $line = str_replace("%word%", $istring['word'], $line);
	                $line = str_replace("%id%", $istring['id'], $line);
	                $html .= $line;
	            }
	            $html .= $ptemplate['table_footer'];
	            $html = str_replace('%form_action%', $kernel->pub_redirect_for_form('ignored_add'), $html);
	            break;

	    }
	    return $html;

	}
}