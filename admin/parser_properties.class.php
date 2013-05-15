<?php

/**
 * Класс предназанчен для обработки классов вида properties_*
 *
 * @name parse_properties
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */
class parse_properties
{
	private $curent_mudul;			//id модуля, если свойства парсяться для модуля
	private $curent_modul_name;  	//название модуля, если свойства парсяться для страницы сайта, т.к. надо выводить
	                                //к какому модулю относиться то, или иное свойство.
	private $curent_metod;			//id метода, если свойства парсяться для метода модуля, или для страницы сайта
	private $curent_page;			//id страницы сайта, чьи свойства будем выводить
	private $for_page = false;		//Признак того, что свойства парсятся для страницы сайта

	private $nasledovanie = false;  //Признак того, что наследование должно быть включено

	private $macros_value = array();
	private $template = array();
	private $max_label = 28;        //максимальная длинна символов в заголовке, остальные ображутся


	function parse_properties()
	{
	    global $kernel;

	    //Сразу подключим шаблон
	    $this->template = $kernel->pub_template_parse("admin/templates/default/parser_properties.html");
	}


    /** Устанавливает модуль, чьи парметры будем вытаскивать
    * @param string $id_modul
    * @param string $parent
    **/
	function set_modul($id_modul, $parent = "")
	{
		$this->curent_mudul = $id_modul;
		if (!empty($parent))
			$this->nasledovanie = true;

	}


    function set_modul_caption($name)
    {
        $this->curent_modul_name = $name;
    }

	function max_chars_label($val)
	{
	    $val = intval($val);
	    if ($val > 0)
	       $this->max_label = $val;
	}


    /**
     * Устанавливает metod, чьи параметры будем вытаскивать
     * @param String $id_metod
     */
	function set_metod($id_metod = "")
	{
		$this->curent_metod = $id_metod;
		$this->nasledovanie = false;
	}

	/**
	 * Устанавливает признак того, что парситься страница
	 *
	 * @param string $id_page Идентификатор страницы, чьи свойства надо взять
	 * @param boolean $page_main
	 */
	function set_page($id_page, $page_main = false)
	{
		$this->for_page = true;
		$this->curent_page = $id_page;
		$this->nasledovanie = !$page_main;
	}

    /**
    * Устанавливает массив значений для метода get_default, в случае если работа идет с макросом
    * @param array $value
    */
	function set_value_default($value)
	{
		if (is_array($value))
			$this->macros_value = $value;
	}



	/**
	 * Возвращает масив с текущим значением данного свойства
	 *
	 * Значения берется в зависимости от того, чьё свойтво обрабатывается
	 * @param string $name Имя свойства
	 * @return array
	 */
	function get_default($name)
	{
		global $kernel;

		if ((!empty($this->curent_mudul)) && (!$this->for_page))//Параметры модуля
			$ret_array = $kernel->pub_modul_properties_get($name, $this->curent_mudul,true);
		elseif ($this->for_page)//Параметры страницы, которые добавляет модуль
			$ret_array = $kernel->pub_page_property_get($this->curent_page, $name, true);
		else
		{
			//Параметры макроса
			$ret_array['isset'] = false;
			$ret_array['value'] = "";
            //$kernel->debug($this->macros_value, true);
			if (!empty($this->macros_value))
			{
				$ret_array['isset'] = isset($this->macros_value[$name]);
				if ($ret_array['isset'])
				{
					//нужно убрать кавычки, обрамляющие значение
					$temp_str = $this->macros_value[$name];
					if (mb_substr($temp_str,0,1) == '"')
						$temp_str = mb_substr($temp_str,1);

					if (mb_substr($temp_str,-1) == '"')
						$temp_str = mb_substr($temp_str,0,(mb_strlen($temp_str)-1));

					$ret_array['value'] = trim($temp_str);
				}
			}
		}
		return $ret_array;
	}

	//********************************************************************************

	/**
	 * Возвращает html-код в зависимости от типа свойства
	 *
	 * @param array $in
	 * @return string
	 */
	function create_html($in)
	{
        $html='';
		if (is_array($in))
		{
			if ($this->for_page)
				$in['name'] = trim($this->curent_mudul).'_'.$in['name'];
			switch ($in['type'])
			{
                case "select":
                    $html = $this->html_select($in);
                    break;

				case "file":
					$html = $this->html_file($in);
					break;

				case "check":
					$html = $this->html_check($in);
					break;

				case "text":
					$html = $this->html_text($in);
					break;

				case "data":
					$html = $this->html_data($in);
					break;

				case "page":
					$html = $this->html_page($in);
					break;


			}
		}
		//Заменим число максимльных сиволов заголовка
		$html = str_replace('%max_label%' , $this->max_label      , $html);
		$html = str_replace('%max_label2%', ($this->max_label - 3), $html);

		return $html;

	}


    /**
     * Возвращает значение параметра, после выбора в форме редактирования действия
     *
     * Если этого параметра нет, то возвращается False. Если есть, но пустое значение
     * то возвращается именно пустое значение, так как методу нужно передать все значения
     * @param array $value Массив со всеми свойтсвами из формы
     * @param array $dat Стандартный масив со свойством
     * @return mixed
     */
	function return_normal_value($value, $dat)
	{
		$ret = '"'.'"';
		$name = $dat['name'];
		if ($dat['type'] == "check")
		{
            if (!isset($value[$name]) || $value[$name]=="false")
                $ret = false;//"false";
            else
                $ret = true;//"true";
		}
        else
		{
			if (isset($value[$name]))
				//$ret = '"'.trim($value[$name]['value']).'"';
				//Раньше было так, так как тут ещё была отметка о наследовании
				//$ret = trim($value[$name]['value']);
				//Теперь просто - ключ - значение
				$ret = trim($value[$name]);
		}
		return $ret;
	}


	function flag_nasled_create($value_default)
	{
		$ret['flag_disabled'] = 'disabled="disabled"';
		$ret['flag_checked']   = '';
		if ($this->nasledovanie)
		    $ret['flag_disabled'] = '';

        if ((isset($value_default['naslednoe'])) && $value_default['naslednoe'])
            $ret['flag_checked']  = 'checked="checked"';

	    return $ret;
	}


	function name_prop_return($name)
	{
        if (!empty($this->curent_modul_name))
        {
            $html = $this->template['html_modul_name'];
            $html = str_replace("%name%", $name, $html);
            $html = str_replace("%namem%", $this->curent_modul_name, $html);

            $name = $html;
        }

        return $name;

	}


	function template_element_html($name, $arr, $value = '')
	{
	    $value_default = $this->get_default($arr['name']);

	    $flag = $this->flag_nasled_create($value_default);

        $html = $this->template['html_main'];
        $html = str_replace("%element%" , $this->template[$name.'_html']          , $html);
        $html = str_replace("%name%"    , $arr['name']                            , $html);
        $html = str_replace("%disabled%", $flag['flag_disabled']                  , $html);
		$html = str_replace("%checked%" , $flag['flag_checked']                   , $html);
        $html = str_replace("%caption%" , $this->name_prop_return($arr['caption']), $html);
        $html = str_replace("%value%"   , $value                                  , $html);

        $descr = '';
        if (isset($arr['description']) && !empty($arr['description']))
            $descr = $arr['description'];
        elseif (isset($arr['description_user_func']) &&
                !empty($arr['description_user_func'])  &&
                is_array($arr['description_user_func']) )
        {
            $user_func_arr = $arr['description_user_func'];
            if (is_array($user_func_arr['name']))
            {//значит это массив [0]=>Имя_класса, [1]=>Имя_метода
                if (!class_exists($user_func_arr['name'][0]))
                {//класс не объявлен - надо его заинклюдить
                    if (file_exists($user_func_arr['filepath']))
                    {
                        require_once $user_func_arr['filepath'];
                        if (class_exists($user_func_arr['name'][0]))
                            $descr = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                        else
                            print "<b>Error - class ".$user_func_arr['name'][0]." not exist</b>";
                    }
                    else
                        print "<b>Error - file ".$user_func_arr['filepath']." not found</b>";
                }
                else
                    $descr = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
            }
            else
            {//это функция
                if (function_exists($user_func_arr['name']))
                    $descr = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                else
                {
                    if (file_exists($user_func_arr['filepath']))
                    {
                        require_once $user_func_arr['filepath'];
                        if (function_exists($user_func_arr['name']))
                            $descr = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                        else
                            print "<b>Error - function ".$user_func_arr['name']." not exist</b>";
                    }
                    else
                        print "<b>Error - file ".$user_func_arr['filepath']." not found</b>";
                }
                //

            }
        }

        $html = str_replace("%description%", $descr, $html);
        return $html;

	}

    function array_out_create($name, $html, $code, $naslednoe, $value, $type = 'ext')
    {
        //Подготовим массив к возвращению
		$out = array();

        $html = preg_replace("/(\r|\n)/s", "", $html);

		$out['code_html']   = $html;       //Обычный код свойтва, стандартными средствами HTML
		$out['code_js']     = $code;                 //Код, создающий объекты и события
		$out['name']        = 'ppv_'.$name; //имя объекта с самим свойством
		$out['name_nasled'] = 'ppf_'.$name; //Имя объекта галочки с наследованием
		$out['naslednoe']   = $naslednoe; //флаг того,есть наследование или нет
		$out['value']       = htmlspecialchars($value);        //Само значение
		$out['type']        = $type;
        return $out;
    }



    private function get_call_user_func_result($name, $param)
    {
        if ($param)
            return call_user_func($name, $param);
        else
            return call_user_func($name);
    }


	//********************************************************************************
	/**
	 * Возвращает код, для создания объекта формы
	 *
	 * Возвращаемый массив содержит два значения. В первом объекты JavaSrcipt, для
	 * создания объекта. Во втором кусок для объекта формы, создающий колонки и сами
	 * элементы управления
	 * @param array $array
	 * @return array
	 */
	function html_select($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
		$naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);
        $value = $value_default['value'];
		//Теперь надо узнать, значение свойства отнаследовано или нет
		//и установить соответсвующие флаги


        //Можно формировать сам код свойства. Сначала зачитаем HTML для него
        $html = $this->template_element_html('select', $array);

        //А теперь код скрипта
        $code = $this->template['select_code'];
        $code = str_replace("%name%",  $array['name'], $code);
		$code = str_replace("%value%", $value, $code);

        $data = array();
	    if (isset($array['data_user_func']) &&
                !empty($array['data_user_func'])  &&
                is_array($array['data_user_func']) )
        {
            $user_func_arr = $array['data_user_func'];
            if (is_array($user_func_arr['name']))
            {//значит это массив [0]=>Имя_класса, [1]=>Имя_метода
                if (!class_exists($user_func_arr['name'][0]))
                {//класс не объявлен - надо его заинклюдить
                    if (file_exists($user_func_arr['filepath']))
                    {
                        require_once $user_func_arr['filepath'];
                        if (class_exists($user_func_arr['name'][0]))
                            $data = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                        else
                            print "<b>Error - class ".$user_func_arr['name'][0]." not exist</b>";
                    }
                    else
                        print "<b>Error - file ".$user_func_arr['filepath']." not found</b>";
                }
                else
                    $data = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
            }
            else
            {//это функция
                if (function_exists($user_func_arr['name']))
                    $data = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                else
                {
                    if (file_exists($user_func_arr['filepath']))
                    {
                        require_once $user_func_arr['filepath'];
                        if (function_exists($user_func_arr['name']))
                            $data = $this->get_call_user_func_result($user_func_arr['name'], $user_func_arr['param']);
                        else
                            print "<b>Error - function ".$user_func_arr['name']." not exist</b>";
                    }
                    else
                        print "<b>Error - file ".$user_func_arr['filepath']." not found</b>";
                }
            }
        }
        else
            $data = $array['data'];
        $store_options='';
        foreach($data as $k=>$v)
        {
            if ($k==$value)
                $store_options.='<option value="'.htmlspecialchars($k).'" selected>';
            else
                $store_options.='<option value="'.htmlspecialchars($k).'">';
            $store_options.=htmlspecialchars($v).'</option>';
        }
        $html = str_replace("%store_options%",$store_options, $html);
        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value);
	}

	//********************************************************************************
	/**
	 * Возвращает код, для создания объекта формы
	 *
	 * Возвращаемый массив содержит два значения. В первом объекты JavaSrcipt, для
	 * создания объекта. Во втором кусок для объекта формы, создающий колонки и сами
	 * элементы управления
	 * @param Array $array
	 * @return array
	 */
	function html_file($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
		$naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);
        $value = $value_default['value'];

        //Подготовим массив, со списком файлов для выбора
        //Потом убрать в отдельную функцию
        $arr_files  = array();
	    $array_mask = explode(",", $array['mask']);
	    if (count($array_mask) <= 0)
	       $array_mask[] = $array['mask'];
	    $array_mask = array_flip($array_mask);

		$d = dir($array['patch']);
		while (false !== ($entry = $d->read())) {
			$link = $array['patch'].'/'.$entry;
			if (is_file($link))
			{
				if (!empty($array_mask))
				{
					$file_name = explode(".",$entry);
					if (count($file_name) > 1 )
						$file_name = $file_name[(count($file_name)-1)];
					else
						$file_name = '';

					if (empty($file_name))
						continue;

					if (!isset($array_mask[$file_name]))
						continue;
				}

                $arr_files[$link] = $entry;
			}
		}
		$d->close();

        //Можно формировать сам код свойства. Сначала зачитаем HTML для него
        $html = $this->template_element_html('file', $array);

        //А теперь код скрипта
        $code = $this->template['file_code'];
        $code = str_replace("%name%",  $array['name'], $code);
		$code = str_replace("%value%", $value, $code);

        $store_options='';
        foreach($arr_files as $k=>$v)
        {
            if ($k==$value)
                $store_options.='<option value="'.htmlspecialchars($k).'" selected>'.htmlspecialchars($v).'</option>';
            else
                $store_options.='<option value="'.htmlspecialchars($k).'">'.htmlspecialchars($v).'</option>';
        }
        $html = str_replace("%store_options%",$store_options, $html);
        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value);
	}


	//********************************************************************************
	/**
	 * Создает HTML код для свойства типа "check" (галочка)
	 *
	 * @param Array $array
	 * @return array
	 */
	function html_check($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
		$naslednoe     = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);
        $value         = $value_default['value'];

        //Подготовим массив, со списком файлов для выбора
        //Потом убрать в отдельную функцию
        $value_hide = 'false';
        if ($value)
        {
            $value      = 'checked="checked"';
            $value_hide = 'true';
        }
        else
            $value = '';


        //Можно формировать сам код свойства. Сначала зачитаем HTML для него
        $html = $this->template_element_html('checkbox', $array, $value);
        $html = str_replace("%checked_input%", $value_hide, $html);

        //А теперь код скрипта
        $code = $this->template['checkbox_code'];
        $code = str_replace("%name%",  $array['name'], $code);

        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value, 'check');
	}

	/**
	 * Создает HTML код для свойства строка обыконовенная
	 *
	 * @param Array $array
	 * @return array
	 */
	function html_text($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
        $value = $value_default['value'];

        $naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);

        //Можно формировать сам код свойства
        //Необходимо создать чекбокс для выбора наследовать значение или нет
		$html = $this->template_element_html('text', $array, $value);

		$code = $this->template['text_code'];
        $code = str_replace("%name%",  $array['name'], $code);

		//Подготовим массив к возвращению
        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value, 'input');
	}

	//******************************************************************************************
	/**
	 * Создает HTML код для свойства типа "дата" (поле с кнопкой выбора через календарь)
	 *
	 * @param Array $array
	 * @return array
	 */
	function html_data($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
		$value = $value_default['value'];

		$naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);

        //Можно формировать сам код свойства
        //Необходимо создать чекбокс для выбора наследовать значение или нет
		$html = $this->template_element_html('date', $array);

		$code = $this->template['date_code'];
        $code = str_replace("%name%",  $array['name'], $code);
        $code = str_replace("%value%", $value        , $code);

        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value);

	}

    /**
     * Создает HTML код для свойства типа textarea
     *
     * @param array $array
     * @return array
     */
    function html_textarea($array)
    {
        // Узнаем текущие значения свойства
        $value_default = $this->get_default($array['name']);
        $value = $value_default['value'];

        $naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);

        //Можно формировать сам код свойства
        //Необходимо создать чекбокс для выбора наследовать значение или нет
        $html = $this->template_element_html('textarea', $array, $value);

        $code = $this->template['textarea_code'];
        $code = str_replace("%name%",  $array['name'], $code);

        //Подготовим массив к возвращению
        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value, 'textarea');
    }

	/**
	 * Создает HTML код для свойства типа "страницы" (поле с кнопкой выбора через структуру сайта)
	 *
	 * @param Array $array
	 * @return array
	 */
	function html_page($array)
	{
		// Узнаем текущие значения свойства
		$value_default = $this->get_default($array['name']);
		$value = $value_default['value'];
		$naslednoe = ((isset($value_default['naslednoe'])) && $value_default['naslednoe']);

        //Можно формировать сам код свойства
        //Необходимо создать чекбокс для выбора наследовать значение или нет
		$html = $this->template_element_html('page', $array,$value);

		$code = $this->template['page_code'];
        $code = str_replace("%name%",  $array['name'], $code);
        $code = str_replace("%value%", $value        , $code);

        return $this->array_out_create($array['name'], $html, $code, $naslednoe, $value);
	}


	/**
	 * Возвращет HTML код для пострения структуры сайта
	 * Используется в свойстве для выбора страницы сайта
     * @return string
	 */
    function get_structure()
    {
        global $kernel;

        $show_tree = $kernel->pub_httpget_get('action_tree');

        //Значит нужно сформировать код дерева, и вывести его
        switch ($show_tree)
        {
            //Формируем непосредственно данные
            default:
                //Действие structura_tree будет вызвано в том менеджере, который
                //явялется текущим, при выполнение запроса get_structure()

                $ms = new manager_structue();
                $tree = new data_tree('Структура','index',$ms->get_all_nodes('index'));
                $tree->set_action_get_data('action=select_page&action_tree=get');
                $tree->set_action_click_node('action=select_page&action_tree=click');
                $tree->set_direct_action();
                $tree->set_drag_and_drop(false);


                //Необходимо заключит построеное дерево в див, так и задать его имя
                $html = $tree->get_tree();
                $html = '<div id="[#name_div_content#]">'.$html.'</div>';
                $html = str_replace("[#name_div_content#]", "test_tree", $html);
                break;

            //Значит это уже запрос на саму структуру
            case 'get':
                $node = $kernel->pub_httppost_get('node');
                if (empty($node))
                    $node = 'index';

                $mod  = new manager_structue();
                $html = $kernel->pub_json_encode($mod->get_all_nodes($node));
                break;
            case 'click':
                $html='';
                break;
        }

        return $html;
    }
}

/**
 *  Абстрактный класс свойства, от которого наследуются все остальные
 */
abstract class properties_abstact
{

    /**
     * Уникальное ID свойства (желательно без подчеркивания).
     *
     * @var string
     * @access private
     */
    protected  $id = '';		// Уникальное название параметра (желательно без подчеркивания)

	/**
	 * Заголовок свойства
	 *
	 * @var string
	 * @access private
	 */
	protected $caption = '';		// Название параметра


	/**
	 * Описание свойства
	 *
	 * @var string
	 * @access private
	 */
	protected $description = '';


	/**
	 * Название функции (метода класса), которая вернёт
	 * Описание свойства через call_user_func
	 *
	 * @var string
	 * @access private
	 */
	protected $description_user_func = array();

	/**
	 * Тип
	 *
	 * @var string
	 * @access private
	 */
	protected  $type;


	/**
	 * Значение по умолчанию
	 *
	 * @var string
	 * @access private
	 */
	protected $default	= '';


	/**
	 * Устанваливает значение свойства по умолчанию
	 *
	 * @param string $value
	 * @access public
	 * @return void
	 */
	public function set_default($value)
	{
		$this->default = trim($value);
	}

	/**
	 * Устанавливает id свойства
	 *
	 * Через это ID в дальнейшем будет идти обращение к значению данного свойства
	 * @param string $id
     * @access public
	 * @return void
	 */
	public function set_id($id)
	{
		$this->id = $id;
	}


	/**
	 * Устанавливает название свойства
	 *
	 * По этому навзванию администратор или пользователь сайта
	 * будет определять назанчение даннного свойства
	 * @param string $caption
	 * @access public
	 * @return void
	 */
	public function set_caption($caption)
	{
		$this->caption = $caption;
	}

	/**
	 * Устанавливает описание свойства
	 *
	 * @param string $description
	 * @access public
	 * @return void
	 */
	public function set_description($description)
	{
		$this->description = $description;
	}


    /**
     * Устанавливает название функции (метода класса), которая вернёт
     * описание свойства через call_user_func
     * @param string $filepath имя файла (с путём от корня) где объявлена функция или метод
     * @param mixed $name имя функции (строка) или массив ('имя_класса', 'имя_метода')
     * @param bool $param параметр (необязательно) для вызова функции
     * @access public
     * @return void
     */
	public function set_description_user_func($filepath, $name, $param=false)
	{
		$this->description_user_func = array('filepath'=>$filepath, 'name'=>$name, 'param'=>$param);
	}

	/**
	 * Возвращает значения свойства в виде массива
	 *
	 * @return array
	 */
	public function get_array()
	{
		$param = array();
		$param["name"]		= $this->id;
		$param["caption"]	= $this->caption;
		$param["type"]		= $this->type;
		$param["default"]	= $this->default;
        $param['description']           = $this->description;
        $param['description_user_func'] = $this->description_user_func;
		return $param;
	}
    //public abstract function create_html();
}

/**
 * Создает свойтсва типа "файл"
 *
 * Формируется поле для возможности выбора одного из файла, находящегося в заданном каталоге
 * @name properties_file
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */
class properties_file extends properties_abstact
{

	/**
	 * Путь к папке с файлами
	 *
	 * @var string
	 * @access private
	 */
	private $patch		= '/';		// путь от корня сайта, где брать файлы

	/**
	 * Маска обрабатываемых файлов
	 *
	 * @var string
	 * @access private
	 */
	private $mask		= ''; 		// Маска допустимых файлов (расширений),


    function __construct()
    {
        $this->type='file';
    }
	/**
	 * Устанавливает путь к файлам
	 *
	 * Устанавливает путь от корня сайта к папке, от куда производится
	 * считывание файлов для выбора в качестве значения свойства
	 * @param string $patch
	 * @access public
	 * @return void
	 */
	public function set_patch($patch)
	{
		$this->patch = $patch;
	}


	/**
	 * Задаёт маску выбираемых файлов
	 *
	 * Определяет расширения файлов поподающих в список для выбора
	 * в качестве значения свойства. Маска задаётся стандартным видом: *.*,*.html,*.html
	 * @param string $mask Если нужно передать несколько расширений, указываются через запятую
	 * @access public
	 * @return void
	 */
	public function set_mask($mask)
	{
		$this->mask = $mask;
	}

	/**
	 * Возвращает значения свойства в виде массива
	 *
	 * @access private
	 * @return array
	 */
	public function get_array()
	{
        $param = parent::get_array();
		$param["patch"]		= $this->patch;
		$param["mask"]		= $this->mask;
		return $param;
	}

}

/**
 * Создает свойство типа "список значений"
 *
 * Формируется поле для выбора одного из значений, когда
 * значения выбирается из выпадающего списка
 * @name properties_select
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class properties_select extends properties_abstact
{

	/**
	 * Массив выбираемых значений
	 *
	 * Где ключ - значение, а знaчение массива - представление значения
	 * @var array
	 * @access private
	 */
	private $data		= array();


	/**
	 *  Информация о пользовательской функции, которая вернёт массив key=>value значений
	 *
	 *
	 * @var array
	 * @access private
	 */
	private $data_user_func		= array();

    function __construct()
    {
        $this->type='select';
    }

	/**
	 * Устанавливает название функции (метода класса), которая вернёт
	 * массив списка значений key=>value
	 *
	 * @param string $filepath имя файла (с путём от корня) где объявлена функция или метод
	 * @param mixed $name имя функции (строка) или массив ('имя_класса', 'имя_метода')
	 * @param mixed $param параметр (необязательно) для вызова функции
	 * @access public
	 * @return void
	 */
	function set_data_user_func($filepath, $name, $param=false)
	{
		$this->data_user_func = array('filepath'=>$filepath, 'name'=>$name, 'param'=>$param);
	}


	/**
	 * Устанавливает массив возможных значений свойства
	 *
	 * @param array $data Ключ - варинат значения параметра, значениее - представление для пользователя
	 * @access public
	 * @return void
	 */
	function set_data($data)
	{
		$this->data = $data;
	}

	/**
	 * Возвращает значения свойства в виде массива
	 *
	 * @access private
	 * @return array
	 */
	function get_array()
	{
        $param = parent::get_array();
		$param["data"]		= $this->data;
        $param['data_user_func'] = $this->data_user_func;
		return $param;
	}
}


/**
 * Создает свойство типа "галочка"
 *
 * Формируется поле для возможности поставить "галочку".
 * @name properties_checkbox
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class properties_checkbox extends properties_abstact
{

    function __construct()
    {
        $this->type='check';
    }

}

/**
 * Создает свойство типа "страница сайта"
 *
 * Формируется поле для возможности выбора страницы сайта
 * находящейся в структуре сайта
 * @name properties_pagesite
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class properties_pagesite extends properties_abstact
{

    function __construct()
    {
        $this->type='page';
    }
}


/**
 * Создает свойство типа "строка"
 *
 * @name properties_string
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */
class properties_string extends properties_abstact
{


	/**
	 * Максимальная длинна вводимой строки
	 *
	 * @var int
	 * @access private
	 */
	private $max	    = '255';

	/**
	 * Размер отображаемого поля для ввода значения
	 *
	 * @var int
	 * @access private
	 */
	private $size	    = 5;


    function __construct()
    {
        $this->type='text';
    }

	/**
	 * Возвращает значения свойства в виде массива
	 *
	 * @access private
	 * @return array
	 */
	function get_array()
	{
        $param = parent::get_array();
        $param["max"] = $this->max;
        if ($this->size > mb_strlen($param["default"]))
            $size = $this->size;
        else
            $size = mb_strlen($param["default"]);
		$param["size"]	    = $size;
		return $param;
	}
}

/**
 * Создает свойство типа "дата"
 *
 * Формируется поле для возможности указания даты. Значение даты может быть введено как
 * в ручную, так и через форму каллендаря.
 * @name properties_date
 * @copyright ArtProm (с) 2001-2011
 * @version 2.0
 */

class properties_date extends properties_abstact
{

    function __construct()
    {
        $this->type='data';
    }

}



class properties_textarea extends properties_abstact
{
	/**
	 * Максимальная длина вводимой строки
	 *
	 * @var int
	 * @access private
	 */
	private $max	    = '5000';

    function __construct()
    {
        $this->type='textarea';
    }
	/**
	 * Возвращает значения свойства в виде массива
	 *
	 * @access private
	 * @return array
	 */
	function get_array()
	{
        $param = parent::get_array();
		$param["max"] = $this->max;
		return $param;
	}
}
?>