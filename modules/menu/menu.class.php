<?php

/**
 * Модуль "меню"
 *
 * Модуль "меню" предназначен для реализации всего одной функции,
 * а именно построения меню сайта, для того что бы пользователи сайта
 * могли осуществлять по нему (по сайту) навигацию.
 * @copyright ArtProm (с) 2001-2008
 * @version 1.0
 */

class menu
{
    /**
     * Переменная с распаршенным шаблоном
     *
     * @var array
     * @access private
     */
	private $template_array = array();

	/**
	 * Текущая дорога
	 *
	 * @var array
	 * @access private
	 */
	private $curent_way = array();

	/**
	 * Конструктор класса
	 *
	 * @return menu
	 * @access private
	 */
	function menu ()
    {
		global $kernel;

    	//Сформируем дорогу сайта.
    	$arr_way = $kernel->pub_waysite_get();

        // Удаляем дополнительные элементы пути, чтобы не мешали
        if (isset($arr_way['additional_way']))
            unset($arr_way['additional_way']);

		$this->curent_way = $arr_way;

    }


    /**
     * Формирует динамическое меню сайта
     *
     * Сформированное меню зависит от того, на какой странице
     * находится пользователь сайта, или другими словами на
     * какой странице будет вызываться данный метод
     *
     * @access public
     * @param string  $p_id_page   ID страницы, от которой строим меню
     * @param integer $start_level Уровень, с которого выводим меню
     * @param integer $count_level Количество уровней, которое будет отображаться в меню
     * @param string  $template    Файл шаблона
     * @return HTML
     */
    function pub_show_menu($p_id_page, $start_level, $count_level, $template)
    {
    	global $kernel;

    	//Узнали текущую карту сайта в виде дерева
    	$tree_map = $kernel->pub_mapsite_cashe_create(1,$p_id_page);

    	//Определили используемый шаблон
        $template = trim($template);
        if (!empty($template) && file_exists($template))
        	$this->parse_template($template);
        else
            return 'В параметрах действия не определён шаблон, либо такой файл не существует';

        //В этом варианте меню нам нужно выводить только ту ветку,
        //в которой сейчас находиться пользователь с учётом страницы,
        //от которой строиться меню и уровня
        $start_level_coutn = false;
        foreach ($this->curent_way as $key => $val)
    	{
    		//Значит это та страница, от которой будет строиться меню, и нужно от неё отсчитать
    		// уровень $start_level
    		if ($key == $p_id_page)
    			$start_level_coutn = true;

    		if ($start_level_coutn)
    			$start_level--;

    		if ((isset($tree_map[$key])) && ($start_level >= 0))
    			$tree_map = $tree_map[$key]['include'];

    	}

    	//Если $start_level остался больше нуля, значит меню выводить не надо
    	//так как пользователь не дошел до нужной глубины структуры
        $menu = '';
        if ($start_level <= 0)
        	$menu = $this->recurs_menu($tree_map, $count_level);

		return $menu;
    }

    /**
     * Формирует статическое меню
     *
     * Меню, сформированное этим методом всегда выводиться на сайте, в независимости
     * от того на какой странице сейчас находиться посетитель сайта
     *
     * @access public
     * @param string  $p_id_page  Идентификатор страницы начала построения меню
     * @param integer $level_show Количество вложенных уровней, отображаемых в меню
     * @param string  $template   Файл шаблона
     * @return HTML
     */
    function pub_show_menu_static($p_id_page, $level_show, $template)
    {
    	global $kernel;

    	//Узнали текущую карту сайта в виде дерева
    	$tree_map = $kernel->pub_mapsite_cashe_create(1,$p_id_page);

    	//Теперь распарсим шаблон
        $template = trim($template);
        if (!empty($template) && file_exists($template))
        	$this->parse_template($template);
        else
            return 'В параметрах действия не определён шаблон, либо такой файл не существует';

		//Выведем меню с заданной глубиной уровней
		$html = '';
        $html = $this->recurs_menu_static($tree_map, $level_show);
    	return $html;
    }

    private function prepare_menu_item($tmpl, $id, $caption, $level)
    {
		$tmpl = str_ireplace("%text%" , $caption   , $tmpl);
		$tmpl = str_ireplace("%link%" , "/$id", $tmpl);
        $tmpl = str_ireplace("%id%"   , $id        , $tmpl);
        $tmpl = str_ireplace("%level%", $level     , $tmpl);
        return $tmpl;
    }


	/**
	 * Формирует меню сайта по заданному массиву карты
	 *
	 * Рекурсивная функция, используемая для построения динамического
	 * меню
	 * @access private
	 * @param array   $data       Массив страниц для вывода
	 * @param integer $level_stop Количество выводимых уровней
	 * @param integer $level      Текущий уровень выводимого меню
	 * @return HTML
	 */
	function recurs_menu($data, $level_stop, $level = 0)
	{
		global $kernel;

		//Проверка на уровень остановки
		if (!empty($level_stop))
			$level_stop = intval($level_stop);
		else
			$level_stop = 1;

		//Сначала, создадим экземпляры в каждом шаблоне, для этого уровня...
		foreach ($this->template_array as $key => $val)
			if (!isset($val[$level]))
				$this->template_array[$key][$level] = $this->template_array[$key][count($this->template_array[$key])-1];

		//Собственно начнём вывод меню этого уровня
		$arr = array();
		$first = true;
		$last_activ = false;
		//$around_activ = false;
		$name_begin = 'begin';
		//$i=0;
		$first_visible_item = false;
		$last_visible_item = false;
		foreach ($data as $key => $val)
		{
			$id = $val['id'];
			$caption = $val['caption'];

			//Возьмем свойство видимости из свойств страницы
			$prop = $kernel->pub_page_property_get($key,'visible');
			$visible = true;
			if (($prop['isset']) && ($prop['value'] == "false"))
				$visible = false;
			if ($visible)
			{
				if ($val['curent'])
				{
					//значит это текущая страница, на которой находится пользователь
					if ($first)
						$name_begin = 'beginactiv';

					$tmpl = $this->template_array['activelink'][$level];
					$last_activ = true;
					$around_activ = true;
				}
				elseif (isset($this->curent_way[$id]))
				{
					//Значит эта страница находится в активном дереве
					$tmpl = $this->template_array['passiveactive'][$level];
					$around_activ = $last_activ;
					$last_activ = false;
				}
				else
				{
					//просто ссылка
					$tmpl = $this->template_array['link'][$level];
					$around_activ = $last_activ;
					$last_activ = false;
				}
				if ($first)
				    $first_visible_item = $val;
				$last_visible_item = $val;

				//Заменим собственно значения в шаблоне
                $tmpl = $this->prepare_menu_item($tmpl, $id, $caption, $level);

                //Ограничения на разрешение вывода меню в макросе
                if ((($level+1) < $level_stop) && ($level_stop > 0))
                	//ограничение по текущей страницы или страницы находящейся в дороге
                	if (($val['curent']) || (isset($this->curent_way[$id])))
                	{
                		//а есть ли вообще что выводить
						if ((isset($val['include'])) && (count($val['include']) > 0))
							$tmpl .= $this->recurs_menu($val['include'], $level_stop, $level+1);
                	}


				//Разделитель нужно добавить сразу
				if (!$first)
				{
					if ($last_activ)
						$arr[] = $this->template_array['delimiteractivstart'][$level];
					elseif ($around_activ)
						$arr[] = $this->template_array['delimiteractivend'][$level];
					else
						$arr[] = $this->template_array['delimiter'][$level];
				}

				$arr[] = $tmpl;
				$first = false;
			}
		}
		if (empty($arr))
			return "";

		//Процесс последнего объединения
		$header = $this->template_array[$name_begin][$level];
		if (stripos($header, "%link%")!==false || stripos($header, "%text%")!==false)
		{
		    if (count($data)>1)
		    {
    		    $first = $first_visible_item;//array_shift($data);
    		    $header = $this->prepare_menu_item($header, $first['id'], $first['caption'], $level);
    		    array_shift($arr);
		    }
		}
		$html = $header;


		if ($last_activ)
			$footer = $this->template_array['endactiv'][$level];
		else
			$footer = $this->template_array['end'][$level];

		if (stripos($footer, "%link%")!==false || stripos($footer, "%text%")!==false)
		{
		    if (count($data)>1)
		    {
		        $last = $last_visible_item;//array_pop($data);
		        $footer = $this->prepare_menu_item($footer, $last['id'], $last['caption'], $level);
		        array_pop($arr);
		    }
		}

		$html .= join("", $arr);
		$html .= $footer;
		return $html;
	}

	/**
	 * Формирует меню сайта по заданному массиву карты
	 *
	 * Рекурсивная функция, используемая для построения
	 * статического меню
	 * @access private
	 * @param array   $data       Массив страниц для вывода
	 * @param integer $level_stop Количество выводимых уровней
	 * @param integer $level      Текущий уровень выводимого меню
	 * @return HTML
	 */
	function recurs_menu_static($data, $level_stop, $level = 0)
	{
		global $kernel;

		//Проверка значения на уровень
		if (!empty($level_stop))
			$level_stop = intval($level_stop);
		else
			$level_stop = 1;

		//Сначала, создадим экземпляры в каждом шаблоне, для этого уровня...
		foreach ($this->template_array as $key=>$val)
			if (!isset($val[$level]))
				$this->template_array[$key][$level] = $this->template_array[$key][count($this->template_array[$key])-1];

		//Собственно начнём вывод меню этого уровня
		$arr = array();
		$first = true;
		$last_activ = false;
		$around_activ = false;
		$name_begin = 'begin';
		$first_visible_item = false;
		$last_visible_item = false;
		foreach ($data as $key => $val)
		{
			$id = $val['id'];
			$caption = $val['caption'];

			//Возьмем свойство видимости из свойств страницы
			$prop = $kernel->pub_page_property_get($key,'visible');
			$visible = true;
			if (($prop['isset']) && ($prop['value'] == "false"))
				$visible = false;

			if ($visible)
			{
				if ($val['curent'])
				{
					//значит это текущая страница, на которой находиться пользователь
					if ($first)
						$name_begin = 'beginactiv';

					$tmpl = "";
					$tmpl .= $this->template_array['activelink'][$level];
					$last_activ = true;
					$around_activ = true;
				}
				elseif (isset($this->curent_way[$id]))
				{
					//Значит эта страница находится в активном дереве
					$tmpl = $this->template_array['passiveactive'][$level];
					$around_activ = $last_activ;
					$last_activ = false;
				}
				else
				{
					//просто ссылка
					$tmpl = $this->template_array['link'][$level];
					$around_activ = $last_activ;
					$last_activ = false;
				}
				if ($first)
				    $first_visible_item = $val;
				$last_visible_item = $val;

				//Заменим собственно значения в шаблоне
				$tmpl = $this->prepare_menu_item($tmpl, $id, $caption, $level);

                //Ограничения на разрешение вывода меню в макросе
                if ((($level+1) < $level_stop) && ($level_stop > 0))
                {
				    if ((isset($val['include'])) && (count($val['include']) > 0))
					   $tmpl .= $this->recurs_menu_static($val['include'], $level_stop, $level+1);
                }

				//Разделитель нужно добавить сразу
				if (!$first)
				{
					if ($last_activ)
						$arr[] = $this->template_array['delimiteractivstart'][$level];
					elseif ($around_activ)
						$arr[] = $this->template_array['delimiteractivend'][$level];
					else
						$arr[] = $this->template_array['delimiter'][$level];
				}
				$arr[] = $tmpl;
				$first = false;
			}
		}

		if (empty($arr))
			return "";

		$header = $this->template_array[$name_begin][$level];
		if (stripos($header, "%link%")!==false || stripos($header, "%text%")!==false)
		{
		    if (count($data)>1)
		    {
    		    $first = $first_visible_item;//array_shift($data);
    		    $header = $this->prepare_menu_item($header, $first['id'], $first['caption'], $level);
    		    array_shift($arr);
		    }
		}
		$html = $header;

		if ($last_activ)
			$footer = $this->template_array['endactiv'][$level];
		else
			$footer = $this->template_array['end'][$level];

		if (stripos($footer, "%link%")!==false || stripos($footer, "%text%")!==false)
		{
		    if (count($data)>1)
		    {
		        $last = $last_visible_item;//array_pop($data);
		        $footer = $this->prepare_menu_item($footer, $last['id'], $last['caption'], $level);
		        array_pop($arr);
		    }
		}

		$html .= join("", $arr);
		$html .= $footer;
		return $html;
	}

    /**
    * Парсит шаблон для построения меню
    *
    * Для распаршивания шаблона используется функция ядра
    * а так же проверка на наличие обязательных блоков
    * и подмену несуществующих блоков
    * @access private
    * @param string $filename Имя файла шаблона
    * @return void
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

		if (!isset($arr['beginactiv']))
			$arr['beginactiv'] = $arr['begin'];

		if (!isset($arr['delimiter']))
			$arr['delimiter'][0] = "";

		if (!isset($arr['delimiteractivstart']))
			$arr['delimiteractivstart'] = $arr['delimiter'];

		if (!isset($arr['delimiteractivend']))
			$arr['delimiteractivend'] = $arr['delimiter'];


		if (!isset($arr['passiveactive']))
			$arr['passiveactive'] = $arr['link'];

		if (!isset($arr['activelink']))
			$arr['activelink'] = $arr['link'];

		if (!isset($arr['end']))
			$arr['end'][0] = "";

		if (!isset($arr['endactiv']))
			$arr['endactiv'] = $arr['end'];

		$this->template_array = $arr;
	}


	/**
	 * Предопределенный метод, используется для вызова административного интерфейса модуля
	 */
	function start_admin()
	{
		//global $kernel;

	}


}
?>