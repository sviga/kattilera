<?PHP

/**
 * Основной управляющий класс модуля «карта сайта»
 *
 * Модуль «карта сайта» предназначен для построения так называемой
 * ссылочной карты сайта. Это страница на которой полностью отображена
 * вся структура сайта.
 *
 * Часто картой сайта пользуются посетители сайта, но гораздо более важное
 * значение она имеет для равномерной индексации поисковыми
 * системами страниц сайта
 * @copyright ArtProm (с) 2001-2007
 * @version 1.0
 */

class mapsite
{
	var $template_array = array(); //Содержит распаршенный шаблон
	var $one_admin = true;		  //Содержит признак того, что админка у модуля одна,
                                  //в не зависимости от количества дочерних модулей
	var $path_templates = "modules/mapsite/templates_user"; //Путь, к шаблонам модуля (туда будут скопированны шаблоны при инсталяции

	function mapsite ()
    {

    }


    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************

    /**
     * Из данного метода создаётся действие для вывода карты сайта
     *
     * @param string $p_id_page Задаётся id страницы, с которой будет начинаться вывод карты
     * @return HTML
     */
    function pub_show_mapsite($p_id_page, $template)
    {
    	global $kernel;


    	$pages = $kernel->pub_mapsite_cashe_create(1, $p_id_page);

    	//поищем установленные модули "каталог товаров" и "вопрос-ответ"...
        $modules = $kernel->pub_modules_get();
        $faq_module = false;
        $catalog_modules = array();
        foreach ($modules as $module_name=>$module_vars)
        {
            if (preg_match("/^faq\\d+$/", $module_name))
                $faq_module = $module_name;
            if (preg_match("/^catalog\\d+$/", $module_name))
            {
                $vals = $this->get_module_action_values($module_name, "pub_catalog_show_cats");
                if (!empty($vals['catalog_items_pagename']))
                {
                    $catalog_modules[$vals['catalog_items_pagename']] = $module_name;
                }
            }
        }
        if ($faq_module)
        {
            $vals = $this->get_module_action_values($faq_module, "pub_show_partitions");
            if (!empty($vals['faq_answers_page']))
            {
                $partitions = $this->faq_get_partitions($vals['faq_answers_page']);
                if (count($partitions)>0)
                    $pages = $this->attach_more_pages($pages, $vals['faq_answers_page'], $partitions);
            }
        }



        if (count($catalog_modules)>0)
        {
            foreach ($catalog_modules as $cpagename=>$cmoduleid)
            {
                $catalogModuleCats = $this->catalog_get_categories_tree(0, $cmoduleid, $cpagename);
                if (count($catalogModuleCats)>0)
                    $pages = $this->attach_more_pages($pages, $cpagename, $catalogModuleCats);
            }
        }


        if (empty($template))
            return '[#module_mapsite_errore2#]';

        if (!file_exists($template))
            return '[#module_mapsite_errore1#] "<i>'.trim().'</i> "';

    	$this->parse_template($template);

        //Начнем формировать HTML код карты сайта, используя
        //рекурсивную функцию
        $html = $this->recurs($pages);


        return $html;
    }

    /**
     * Рекурсивно обходит дерево страниц сайта и добавляет дополнительное дерево
     *
     * @param array $pages  оригинальный массив страниц
     * @param string $attach_in_page id-шник страницы, к которой надо добавить
     * @param array $more_pages дополнительное дерево страниц
     * @return array
     */
    function attach_more_pages($pages, $attach_in_page, $more_pages)
    {
        foreach ($pages as $key => $val)
        {
            if (!isset($val['include']))
                $inc = array();
            else
                $inc = $val['include'];
            if ($key == $attach_in_page)
            {//нужная нам страница
                $inc = $inc+$more_pages;
                $pages[$key]['include'] = $inc;
                return $pages;
            }
            else
            {
                $pages[$key]['include'] = $this->attach_more_pages($inc, $attach_in_page, $more_pages);
            }
        }
        return $pages;
    }

    /**
     * Рекурсивно создаёт дерево категорий каталога товаров в нужном нам виде
     *
     * @param integer $node_id id-шник родительской категории
     * @param integer $module_id id-шник модуля
     * @param string $pagename страница
     * @return array
     */
    function catalog_get_categories_tree($node_id, $module_id, $pagename)
    {
    	global $kernel;
		$data  = array();
		$sql   = 'SELECT * FROM `'.PREFIX.'_catalog_'.$module_id.'_cats` WHERE `parent_id` = '.$node_id;
		$query = $kernel->runSQL($sql);
		if (mysql_num_rows($query) > 0)
		{
		    while ($row = mysql_fetch_assoc($query))
		    {
		        $array = array(
                    'caption' 	=> $row['name'],
		            'parent_id' => $node_id,
		            'curent'    => false,
                );

                $children = $this->catalog_get_categories_tree($row['id'], $module_id, $pagename);

                $array['include'] = $children;
		        $data[$pagename.".html?cid=".$row['id']] = $array;

		    }
		}
		mysql_free_result($query);
		return $data;
    }

    /**
     * Возвращает список разделов модуля FAQ в нужном нам виде
     *
     * @param string $pagename имя страницы вывода вопросов-ответов
     * @return array
     */
    function faq_get_partitions($pagename)
    {
    	global $kernel;
		$data  = array();
		$sql   = 'SELECT * FROM `'.PREFIX.'_faq_partitions`';
		$query = $kernel->runSQL($sql);
		if (mysql_num_rows($query) > 0)
		{
		    while ($row = mysql_fetch_assoc($query))
		    {
		        $array = array(
                    'caption' 	=> $row['name'],
		            'parent_id' => '',
		            'curent'    => false,
		        	'include'   => array(),
                );
		        $data[$pagename.".html?a=2&b=".$row['id']] = $array;
		    }
		}
		mysql_free_result($query);
		return $data;
    }


    /**
     * Возвращает список имя=>значение параметров действия модуля
     *
     * @param integer $module_id id-шник модуля
     * @param string $action_str название действия модуля
     * @return array
     */
    function get_module_action_values($module_id, $action_str)
    {
        $mod = new manager_modules();
        $actions = $mod->list_array_macros($module_id);
        foreach ($actions as $action)
        {
            if ($action['link_str']==$action_str)
            {
                return unserialize($action['param_array']);
            }
        }
        return false;
    }

//***********************************************************************
//	Наборы внутренних методов модуля
//**********************************************************************


	/**
	 * Рекурсивная функция, для преобразования массива с картой в HTML форму в соответсвтии с
	 * шаблоном
	 * @param Array $pages Содержит страницы определнного уровня
	 * @param Int $level Задаёт уровень прохождения по массиву
	 * @return HTML
	 */
	function recurs($pages, $level=-1)
	{
		global $kernel;
		$level++;

		//Если в шаблоне нет необходимого нам уровня, то создадим его из предыдущего
		foreach ($this->template_array as $key=>$val)
			if (!isset($val[$level]))
				$this->template_array[$key][$level] = $this->template_array[$key][count($this->template_array[$key])-1];

		//Начнем вывод
		$map_arr = Array();
		foreach ($pages as $key => $val)
		{

			$html = "";
			$id = $key;
			$caption = $val['caption'];

			//Возьмем свойство видимости из свойств страницы
			$arr = $kernel->pub_page_property_get($key,'visible');
			$visible = true;
			if (($arr['isset']) && ($arr['value'] == "false"))
				$visible = false;

			if ($visible)
			{
				if ($val['curent'])
					$tmpl = $this->template_array['activelink'][$level];
				else
					$tmpl = $this->template_array['link'][$level];

				$tmpl = preg_replace("/%text%/i", $caption, $tmpl);
				//$tmpl = preg_replace("/%link%/i", $id.".html", $tmpl);
				if (strpos($id, ".html?")===false)
				    $tmpl = preg_replace("/%link%/i", $id.".html", $tmpl);
				else
				    $tmpl = preg_replace("/%link%/i", $id, $tmpl);

				$html .= $tmpl;
				if ((isset($val['include'])) && (!empty($val['include'])))
					$html .= $this->recurs($val['include'],$level);
			}
			else
			{
				if ((isset($val['include'])) && (!empty($val['include'])))
				{
            		$html .= $this->template_array['end'][$level];
					$html .= $this->recurs($val['include'],$level);
            		$html .= $this->template_array['begin'][$level];
				}
			}
			$map_arr[] = $html;
		}

		if (empty($map_arr))
			return "";

		$html = $this->template_array['begin'][$level];
		$html .= join($this->template_array['delimiter'][$level], $map_arr);
		$html .= $this->template_array['end'][$level];

		return $html;

	}

    //********************************************************************************
	/**
    * Разбирает шаблон, создает $this->template_array
    * @return void
    * @param String $filename Путь к файлу шаблонов
    */
	function parse_template($filename)
	{
		global $kernel;

		$arr = array();

		//Парсим шаблон с учётом нулевого уровня
		$arr = $kernel->pub_template_parse($filename, true);

		//Теперь проверим блоки на их наличие и значение по умолчанию
		if (!isset($arr['begin']))
			$arr['begin'][0] = "";

		if (!isset($arr['delimiter']))
			$arr['delimiter'][0] = "";

		if (!isset($arr['passiveactive']))
			$arr['passiveactive'] = $arr['link'];

		if (!isset($arr['activelink']))
			$arr['activelink'] = $arr['link'];

		if (!isset($arr['end']))
			$arr['end'][0] = "";


		$this->template_array = $arr;

	}


//***********************************************************************
//	Наборы методов, для работы с админкой модуля
//**********************************************************************


	/**
	 * Предопределйнный метод, используется для вызова административного интерфейса модуля
	 * У данного модуля админка одна, для всех экземпляров, так как в админке надо только
	 * Редактировать шаблоны
	 */
	function start_admin()
	{
		global $kernel;

		$html = 0;

		return $html;

	}

}


?>