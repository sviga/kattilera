<?php

/**
 * Управляет отображением и редактированием свойств и ссылок конкртеной страницы
 * @name properties_page
 * @copyright  ArtProm (с) 2002-2006
 * @version 1.0
 */

class properties_page
{
	var $id_curent_page;
	var $page_is_main = false;
	var $action_form_width = "150";
	var $allow_change = true;
	var $allow_edit_content = true;


    function properties_page($id)
    {
        global $kernel;

        $this->id_curent_page = trim($id);
        $query = 'SELECT id, parent_id
            	  FROM `'.$kernel->pub_prefix_get().'_structure`
                  WHERE id = "'.$this->id_curent_page.'"
                 ';

        $result = $kernel->runSQL($query);
        $row = mysql_fetch_assoc($result);
        if (empty($row['parent_id']))
            $this->page_is_main = true;

	    // Если не рутовый админ
        if (!$kernel->priv_admin_is_root())
        {
            // Запрещаем изменения
            $this->allow_change = false;
            // Если нет прав на редактирование контента
            if (!$kernel->priv_admin_access_for_group_get("kernel_content", "kernel"))
            {
                // ...Запрещаем это делать
                $this->allow_edit_content = false;
            }
        }

    }

	/**
	 * Формирует массив свойств, который всегда задан. Свойства модулей беруться реальные
	 * (т.е. с учетом наследования, что бы пользователь всегда видел значение текужего совйства
	 * на конкретной странице
	 *
	 * @return array
	 */
	function get_properties()
    {
    	global $kernel;

		$prop = $kernel->priv_page_properties_get($this->id_curent_page);

		//Даже если этих свойств ещё нет у страницы, по каким либо причинам -
		// то они сразу появяться после сохранения
        $prop['caption'] 		= $kernel->pub_str_prepare_get($kernel->priv_value_check($prop,'caption','none'));
		$prop['title_other'] 	= $kernel->pub_str_prepare_get($kernel->priv_value_check($prop,'title_other',false));
		$prop['name_title'] 	= $kernel->pub_str_prepare_get($kernel->priv_value_check($prop,'name_title',''));
		$prop['link_other_page']= $kernel->pub_str_prepare_get($kernel->priv_value_check($prop,'link_other_page',''));
		$prop['only_auth'] 	    = $kernel->priv_value_check($prop,'only_auth', 0);

		if ($prop['only_auth'])
			$prop['only_auth'] = true;
		else
			$prop['only_auth'] = false;

		if ((isset($prop['template'])) && (empty($prop['template'])))
			unset($prop['template']);

        return $prop;
    }


    /**
     * Формирует HTML код для ограниченного редактирования свойств страницы
     *
     * @return string
     */
//    function show_properties_limited()
//    {
//    	global $kernel;
//
//    	//Возьмём свойства непосредственно самой страницы
//    	$template = $kernel->pub_template_parse('admin/templates/page_properties_limited.html');
//    	$html = $template['main'];
//        $page_properties = $this->get_properties();
//        $html = str_replace("[#default_name_page#]", htmlspecialchars($page_properties['caption']), $html);
//        $html = str_replace("[#default_link_page#]", $page_properties['link_other_page'], $html);
//        //$kernel->debug($page_properties);
//
//        //флаг на отличие титла страницы от её названия
//        $flag_title = " checked";
//        if ($page_properties['title_other'])
//        	$flag_title = "";
//        $html = str_replace("[#curent_flag_title#]",$flag_title,$html);
//
//
//        //Флаг на наследования шаблона страницы
//        $flag_template = " checked";
//        if ($this->page_is_main)
//        	$flag_template = " disabled";
//        else
//        {
//        	if (isset($page_properties['template']))
//        		$flag_template = "";
//        }
//		$html = str_replace("[#curent_flag_page_template#]", $flag_template, $html);
//
//
//        //Собственно название титла
//		$html = str_replace("[#default_name_page_title#]",htmlspecialchars($page_properties['name_title']),$html);
//
//		//id страницы
//		$html = str_replace("[#default_name_page_id#]", $this->id_curent_page, $html);
//
//        //Установка шаблона страницы (шаблон страницы должны найти в любом случае, что бы отобразить
//        //его, даже если он установлен у родителя
//        $array_template = $kernel->priv_templates_get($kernel->priv_path_page_template_get());
//        $array_prop = $kernel->pub_page_property_get($this->id_curent_page, 'template');
//        //$kernel->debug($this->id_curent_page);
//        //$kernel->debug($array_prop);
//        $html_tmp = $this->create_html_option($array_template,$array_prop['value']);
//		$html = str_replace("[#option_file_templates#]",$html_tmp,$html);
//
//		if ($this->allow_edit_content)
//		{
//		    $edit_content_html = $this->show_edit_content_limited();
//		    $html = str_replace("[#page_prop_edit_content#]", $edit_content_html, $html);
//		}
//		else
//		    $html = str_replace("[#page_prop_edit_content#]", "", $html);
//
////$kernel->debug($html);
//        return $html;
//    }


//    function show_edit_content_limited()
//    {
//        global $kernel;
//        $html = "";
//
//    	$out = $kernel->pub_page_property_get($this->id_curent_page,'template');
//    	$array_div_action = ''; //Массив скрытых дивов с HTML-лем для выбора конкретного макроса
//
//    	if ($out['isset'])
//    	{
//    	    // Берем файл шаблона
//    		$html_template = file_get_contents($out['value']);
//    		// Добавляем метки из файлов контента
//    		$html_template = $this->generate_html_template($html_template);
////    		$kernel->debug($html_template);
//    		$curent_link = $kernel->priv_page_textlabels_get($html_template);
////    		$kernel->debug($curent_link);
//    		$curent_link[0] = array_unique($curent_link[0]);
//    		// Переопределяем индексы массива
//    		foreach ($curent_link[0] AS $value)
//    		  $arr[] = $value;
//    		$curent_link[0] = $arr;
//    		unset($arr);
//    		$curent_link[1] = array_unique($curent_link[1]);
//    		foreach ($curent_link[1] AS $value)
//    		  $arr[] = $value;
//    		$curent_link[1] = $arr;
//
//    		//узнаем текщие значения ссылок
//    		$link_in_page = $kernel->priv_page_serialize_get($this->id_curent_page);
////    		$kernel->debug($link_in_page);
//
//    		//Узнаем значения ссылок с учетом наследования
//    		$link_in_page_real = $curent_link[1];
////    		$kernel->debug($link_in_page_real);
//
//			$link_in_page_real = array_flip($link_in_page_real);
//    		$link_in_page_real = $kernel->priv_page_real_link_get($link_in_page_real, $this->id_curent_page);
////    		$kernel->debug($link_in_page_real);
//
//    		$modul = new manager_modules();
//    		$all_modul = $modul->return_modules();
//			//Теперь, так как у ядра есть ряд предустановленных макросов, то необходимо в модули добавить ядро,
//			//что бы всё работало по единому шаблону
//			$all_modul['kernel']['id_parent'] = 'kernel';
//			$all_modul['kernel']['caption'] = '[#structure_label_kernelaction#]';
//			$all_modul['kernel']['count_macros'] = 2;
//
//    		//Теперь собственно нужно сформировать форму для выбора привязок к сылкам
//    		$html2 = "";
//            $html2 .= '<tr><td colspan="3" height="20" valign="baseline" align="center"><b>Редактируемый контент</b></td></tr>';
//
//
//    		foreach ($curent_link[1] as $key => $val)
//    		{
//    			//Текущий значения нужно взять из $link_in_page_real так как пользователь
//    			//должен видеть даже в затемненом виде что будет вызвано для этой ссылки на этой странице
//    			$id_modul = $link_in_page_real[$val]['id_mod'];
//    			$id_action = $link_in_page_real[$val]['id_action'];
////                $kernel->debug($link_in_page_real[$val]);
//    			if ((($id_modul == "kernel") && ($link_in_page_real[$val]['run']['name'] == "priv_html_editor_start")) || ($id_modul == ""))
//    			{
//        			//А вот если у этой конкретной страницы не указана завязка на эту ссылку - то значит
//        			//она наследуется и возможность выбора нужно отключить
//        			$curent_nasledovanie = '';
//        			$curent_visuble = 'false';
//    				$curent_display_edit = "block";
//    				$curent_display_not_edit = "none";
//        			if ((!$this->page_is_main) && (!isset($link_in_page[$val])))
//        			{
//        				$curent_nasledovanie = ' checked';
//        				$curent_visuble = " disabled";
//        				$curent_display_edit = "none";
//        				$curent_display_not_edit = "block";
//        			}
////    			    $kernel->debug("test");
////        				$kernel->debug($curent_nasledovanie);
////        				$kernel->debug($curent_visuble);
////        			    $kernel->debug($this->page_is_main);
//        			    $html2 .= '<tr class="text">';
//            			if (!$this->page_is_main)
//            			{
//            				$html2 .= '<td><input id="chek_nas_link_'.$key.'" type="checkbox" onClick="set_link_nasled(\''.$key.'\')" '.$curent_nasledovanie.'></td>';
//            				$html2 .= '<input type="hidden" class="text" id="nasleduem_'.$key.'" name="link['.$val.']" value="1"'.$curent_visuble.'>';
//            			}
//        			    $html2 .= '<td height="20" valign="baseline" align="right">';
//        			    $html2 .= $val;
//        			    $html2 .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
//        			    $html2 .= '</td><td height="20" valign="baseline" align="left">';
//        			    $html2 .= '<div id="div_edit_content_'.$key.'" onclick="run_edit_content(\'content/pages/'.$kernel->pub_page_current_get().'_'.$val.'.html\')" class="onclick_link" style="display:'.$curent_display_edit.'">Редактировать</div>';
//        			    $html2 .= '<div id="div_not_edit_content_'.$key.'" class="text" style="display:'.$curent_display_not_edit.'">Редактировать</div>';
//                        //$html2 .= '<img src="images/b_edit.png" width="16" height="16" border="0"  onclick="run_edit_content(\'content/pages/index_'.$val.'.html\')"/>';
//                        $html2 .= '</td>';
//                        $html2 .= '</tr>';
//    			}
//    		}
////            $html .= "</table>";
//    		$html = str_replace("[#list_all_link#]",$html2,$html);
//        	$html = str_replace("%%action_form_width%%", $this->action_form_width, $html);
//    		//$kernel->debug($curent_link[1]);
//    	}
//        return $html2;
//    }


	/**
	 * Заменяет метки с действиями "редактор контента" на содержимое HTML файла
	 *
	 * @param string $html_template
	 * @param array $metki_exist Массив с метками, которые не нужно обрабатывать, так как они уже есть
	 * @return string
	 */
	function generate_html_template($html_template, $metki_exist = array())
	{
	    global $kernel;
		$curent_link = $kernel->priv_page_textlabels_get($html_template);
		$curent_link[0] = array_unique($curent_link[0]);
		$curent_link[1] = array_unique($curent_link[1]);

		//Узнаем значения ссылок с учетом наследования
		$link_in_page_real = $curent_link[1];

		$link_in_page_real = array_flip($link_in_page_real);
		$link_in_page_real = $kernel->priv_page_real_link_get($link_in_page_real, $this->id_curent_page);

		foreach ($link_in_page_real AS $metka => $massiv)
		{
		    if (isset($metki_exist[$metka]))
                continue;
		    if (($link_in_page_real[$metka]['id_mod'] == "kernel") && ($link_in_page_real[$metka]['run']['name'] == "priv_html_editor_start") && (is_file($kernel->priv_path_pages_content_get().'/'.$link_in_page_real[$metka]['page'].'_'.$kernel->pub_translit_string($metka).'.html')))
		    {
		        if ($metka_content = file_get_contents($kernel->priv_path_pages_content_get().'/'.$link_in_page_real[$metka]['page'].'_'.$kernel->pub_translit_string($metka).'.html'))
		            $metka_content = $this->generate_html_template($metka_content, $link_in_page_real);

		        $html_template = str_replace("[#".$metka."#]", "[#".$metka."#]".$metka_content, $html_template);
		    }
		}
		return $html_template;
	}

//	function generate_page_labels()
//	{
//		global $kernel;
//
//		// Узнаем данные о шаблоне текущей страницы
//		$template = $kernel->pub_page_property_get($this->id_curent_page,'template');
//		if ($template['isset'])
//		{
//            // Возьмем файл шаблона и добавим метки из файлов контента
//            $template_html = file_get_contents($template['value']);
//            $template_html = $this->generate_html_template($template_html);
//
//            // Найдем метки в шаблоне и оставим только уникальные
//    		$template_labels = $kernel->priv_page_textlabels_get($html_template);
//
//    		$template_labels[0] = array_unique($template_labels[0]);
//    		$arr = array();
//    		foreach ($template_labels[0] AS $value)
//    		{
//                $arr[] = $value;
//    		}
//    		$template_labels[0] = $arr;
//
//    		$template_labels[1] = array_unique($template_labels[1]);
//    		$arr = array();
//    		foreach ($template_labels[1] AS $value)
//    		{
//                $arr[] = $value;
//    		}
//    		$template_labels[1] = $arr;
//
//    		// Узнаем текщие значения меток
//    		$link_in_page = $kernel->priv_page_serialize_get($this->id_curent_page);
//    		// Узнаем значения меток с учетом наследования
//    		$link_in_page_real = $curent_link[1];
//
//		}
//	}


    function page_prop_json($page_properties, $modules_propertes)
    {
        global $kernel;

        //Надо закодировать русские названия
        $page_properties['id_curent_page']   = $this->id_curent_page;
        $page_properties['link_for_preview'] = "/".$this->id_curent_page.".html";
        $page_properties['page_is_main']     = $this->page_is_main;

        //определим значение шаблона и отнаследовано оно или нет
        $page_properties['template_naslednoe'] = false;
        $array_prop = $kernel->pub_page_property_get($this->id_curent_page, 'template');
        if (isset($array_prop['naslednoe']) && $array_prop['naslednoe'])
            $page_properties['template_naslednoe'] = true;

        $page_properties['page_template'] = $array_prop['value'];


        //Теперь свойства модулей к странице, для этго возьмём из массива $modules_propertes
        //только часть информации. Кроме того, все значения надо будет закодировать, что бы
        //там могли передаваться русские значения
        $page_properties['page_prop'] = array();
        $i = 0;
        foreach ($modules_propertes as $val_tmp)
        {
            foreach ($val_tmp as $value)
            {
                $page_properties['page_prop'][$i]['name']        = $value['name'];
                $page_properties['page_prop'][$i]['name_nasled'] = $value['name_nasled'];
                $page_properties['page_prop'][$i]['naslednoe']   = $value['naslednoe'];
                $page_properties['page_prop'][$i]['value']       = $value['value'];
                $page_properties['page_prop'][$i]['type']        = $value['type'];
                $i++;
            }
        }
        return $kernel->pub_json_encode($page_properties);
    }



    function page_metka()
    {
        global $kernel;
        $metki = array();
    	$page_tpl_prop = $kernel->pub_page_property_get($this->id_curent_page,'template');
        // Берем файл шаблона
		$html_template = @file_get_contents($page_tpl_prop['value']);
		// Добавляем метки из файлов контента
		$html_template = $this->generate_html_template($html_template);
		$curent_link = $kernel->priv_page_textlabels_get($html_template);
    	//$curent_link[0] = array_unique($curent_link[0]);
    	$curent_link = array_unique($curent_link[1]);
    	$curent_link = array_values($curent_link);

    	//Узнаем значения ссылок с учетом наследования
    	$link_in_page_real = $curent_link;
		$link_in_page_real = array_flip($link_in_page_real);

		//1ый проход
    	$link_in_page_real = $kernel->priv_page_real_link_get($link_in_page_real, $this->id_curent_page);
 	    $page_modules = array(); //модули, которые используются в метках на странице
    	$manager_modules = new manager_modules();
    	foreach ($link_in_page_real as $metka_params) //$metka_name=>
    	{
    	    if ($metka_params['id_mod']!="kernel")
    	    {
    	        $minfo = $manager_modules->return_info_modules($metka_params['id_mod']);
    	        if (!$minfo)
    	            continue;
    	        while (!empty($minfo['parent_id']))
    	        {
    	            $minfo = $manager_modules->return_info_modules($minfo['parent_id']);
    	        }
    	        $page_modules[] = $minfo['id'];//$metka_params['id_mod'];
    	    }
    	}
    	$page_modules = array_unique($page_modules);
    	$modules_utpl_metki = array(); //метки из templates_user используемых модулей
    	foreach ($page_modules as $pmodule)
    	{

    	    $module_tpl_path = "modules/".$pmodule."/templates_user/";
    	    $module_utemplates = array_keys($kernel->pub_files_list_get($module_tpl_path));
    	    foreach ($module_utemplates as $module_utemplate)
    	    {
    	        $utpl = @file_get_contents($module_utemplate);
    	        $text_labels = $kernel->priv_page_textlabels_get($utpl);
    	        foreach ($text_labels[1] as $text_label)
    	        {
    	            $modules_utpl_metki[]=$text_label;
    	        }
    	    }
    	}
    	$modules_utpl_metki = array_unique($modules_utpl_metki);
    	foreach ($modules_utpl_metki as $modules_utpl_metka)
    	{
    	    if (!isset($link_in_page_real[$modules_utpl_metka]))
    	    {//чтобы не перезаписать метки из дизайна и контента
    	        $link_in_page_real[$modules_utpl_metka] = 1;
    	        $curent_link[] = $modules_utpl_metka;
    	    }
    	}

		//2ой проход, уже с метками модулей
    	$link_in_page_real = $kernel->priv_page_real_link_get($link_in_page_real, $this->id_curent_page);
        $is_root = $kernel->priv_admin_is_root();
        $curr=0;
		//Начнём перебирать метки и строить для каждой интерфейс
		foreach ($curent_link as $value)
		{
            //если не рут админ, то даже не показываем ему ***_admin метки
            if (!$is_root && preg_match('~_admin$~',$value))
                continue;
            $id_action = trim($link_in_page_real[$value]['id_action']);
		    $id_page   = trim($link_in_page_real[$value]['page']);
		    $full_name_file = '/'.$this->id_curent_page.'_'.$value.'.html';
		    //Проверим наследуется это значение или нет
            $metki[$curr]['name'] = $value;
            //$metki[$num]['is4module'] = false;
            $metki[$curr]['name_falg'] = "flag_metka_".$curr;
            $metki[$curr]['naslednoe'] = !($id_page == trim($this->id_curent_page) || (!isset($link_in_page_real[$value]['page'])));
            $metki[$curr]['id_action'] = $id_action;
            $metki[$curr]['main_page'] = $this->page_is_main;
            $metki[$curr]['file_edit'] = $full_name_file;
            $metki[$curr]['postprocessors'] = $link_in_page_real[$value]['postprocessors'];
            $curr++;
		}
        return $kernel->pub_json_encode($metki);
    }



    /**
     * Формирует и выводит на экран HTML код для редаткирования свойств страницы и ссылок
     *
     * @return string
     */
    function show()
    {
        global $kernel;

        // Узнаем свойства страницы
        $page_properties = $this->get_properties();

        $modules = new manager_modules();
        $modules_propertes = $modules->return_page_properties_all_modules($this->page_is_main);
        $type_run = $kernel->pub_httpget_get('type');
        $templates = $kernel->pub_template_parse('admin/templates/default/admin_structure.html');

        switch ($type_run)
        {
            //Временный код для перестройки формы со свойствами страницы
            case 'get_main_param':
                $html = $this->page_prop_json($page_properties, $modules_propertes);
                break;

            //Массив с метками страницы
            case 'get_metka':
                //Код соединять не будем, что бы выполнять по элементно это
                $html = $this->page_metka();
                break;

            default:
                //Это выводим при первом клике по ноде
                // Распарсим файл с шаблонами


                //Выведем основную часть, с общими свойствами страницы
                $html = $templates['body'];
                $html = str_replace('%page_name%',      addslashes($page_properties['caption'])                 , $html);
                $html = str_replace('%page_title%',     addslashes($page_properties['name_title'])             , $html);
                $html = str_replace('%page_url%',       $page_properties['link_other_page']         , $html);
                $html = str_replace('%page_id%',        $this->id_curent_page                       , $html);
                if ($page_properties['only_auth'] === true)
                	$html = str_replace('%only_auth%',      'checked'               , $html);
                else
                	$html = str_replace('%only_auth%',      ''               , $html);

                $html = str_replace('%link_page_view%', "/".$this->id_curent_page.".html"           , $html);

                //Сохранение всей формы
                $html = str_replace('%url_action%',     $kernel->pub_redirect_for_form('save'), $html);
                //Сохранение нового шаблона, сюда же нужно добавить ссылку текущего варианта левого
                //меню, для того, что бы правильно построить переход для получения данных о ссылках
                $html = str_replace('%chnage_template%',$kernel->pub_redirect_for_form('save_template'), $html);


                //Теперь заполним шаблон

                //Определим, нужна ли галка на наследование (для главной страницы она не нужна)
                //он будет использоваться и дальше
                $flag_nasledovaniya_enabled = "false";
                if ($this->page_is_main)
                    $flag_nasledovaniya_enabled = "true";

                $html = str_replace('%flag_template%', $flag_nasledovaniya_enabled, $html);

                //определим значение шаблона и отнаследовано оно или нет
                $array_prop = $kernel->pub_page_property_get($this->id_curent_page, 'template');
                if (isset($array_prop['naslednoe']) && $array_prop['naslednoe'])
                    $html = str_replace('%flag_template_chek%', "true", $html);
                else
                    $html = str_replace('%flag_template_chek%', "false", $html);

                //Теперь узнаем перечень всех доступных шаблонов
                $array_template = $kernel->priv_templates_get($kernel->priv_path_page_template_get());

                $html = str_replace('%store_templates%', $kernel->pub_array_convert_form($array_template), $html);

        		//Теперь нужно добавить свойства, которые каждый модуль желает добавить к страницам
                $properties_page_h = array();
                $properties_page_c = array();
                $properties_labels = array();
                $properties_selects = array();

                //Получим сразу код, для добавления в форму
                foreach ($modules_propertes as $val_tmp)
                {
                    foreach ($val_tmp as $value)
                    {
                        $properties_page_h[]  = $value['code_html'];
                        $properties_page_c[]  = $value['code_js'];
                        $properties_labels[]  = $value['name_nasled'];
                        $properties_selects[] = $value['name'];
                    }
                }

                //Полученый код вставим в блок @properties_page_fieldset и дальше уже в саму форму 
                if (!empty($properties_page_h))
                {
                    //HTML код, из которого убраны все переносы строк, иначе могут быть ошибки
                    $properties_page_h = implode("", $properties_page_h);
                    $html = str_replace('%str_prop_modul%',$properties_page_h, $html);
                    //Код, для создания объектов
                    $html = str_replace('%str_prop_modul_code%', implode("\n", $properties_page_c), $html);
                }
                else
                {
                    $html = str_replace('%str_prop_modul_code%', '', $html);
                    $html = str_replace('%str_prop_modul%', '', $html);
                }

                if (count($properties_labels))
                    $arr=array_combine($properties_labels,$properties_selects);
                else
                    $arr=array();
                $html = str_replace('%prop_modul_array%', $kernel->pub_array_convert_form($arr), $html);


                //Создаем массив достпуных объектов
                $modul = new manager_modules();
                $all_modul = $modul->return_modules();

                //Теперь, так как у ядра есть ряд предустановленных макросов, то необходимо в модули добавить ядро,
                //что бы всё работало по единому шаблону
                $all_modul['kernel']['id_parent'] = 'kernel';
                $all_modul['kernel']['caption'] = '[#structure_label_kernelaction#]';
                $all_modul['kernel']['count_macros'] = 2;

                //Сразу подготовим необходимые данные по модулям и их действиям
                $tmp = array();

                foreach ($all_modul as $key => $val)
                {
                    if (!empty($val['id_parent']))
                    {
                        $macroses = $modul->list_array_macros($key);
                        if (empty($macroses))
                        	continue;

                        $tmp[] = array($val['caption'], '', '');
                        foreach ($macroses as $macros)
                            $tmp[] = array('',$macros['id'],$macros['caption']);

                    }
                }

                //Создаём массив со всеми действиями, доступны для привязки к модулям
                //[["","","Действие не выбранно"],["Карта сайта","",""],["","8","Показать карту"],["Дорога","",""],["","9","Вывести дорогу"],["","10","Вывести страницу 2-ого уровня"],["Меню","",""],["","13","Вывести верхнее меню"],["","17","Вывести левое меню"],["Основные новости","",""],["","16","Вывести архив"],["","15","Вывести ленту"],["Поиск","",""],["","35","Вывести результаты поиска"],["","34","Вывести форму поиска (для главной)"],["Обратная связь","",""],["","24","Отобразить форму"],["Основные Комментарии","",""],["","40","Комментарии по-умолчанию"],["Вопросы и Ответы","",""],["","25","Показать список вопросов-ответов"],["","26","Показать список разделов вопросов-ответов"],["Каталог товаров","",""],["","37","Вывести содержимое корзины"],["","38","Вывести стикер корзины"],["","32","Название элемента"],["","39","Отобразить форму заказа"],["","30","Список категорий"],["","31","Список товаров"],["","36","Сформировать заголовок"],["Фотогалерея","",""],["","41","Показать все фото"],["","33","Сформировать список товаров"],["Ядро","",""],["","2","Вернуть заголовок страницы"],["","1","Редактор контента"]]
                $html = str_replace('%all_modules%', '[["","","[#structure_action_blank#]"],'.$kernel->pub_array_convert_form_rec($tmp).']', $html);
                $html = str_replace('%post_processors%',$kernel->pub_json_encode($kernel->get_postprocessors()),$html);
                //Все метки будут строиться ява скриптом, что бы всё было едино образно
                $html = str_replace('%link_show_metki%', $kernel->pub_section_leftmenu_get(), $html);
    		break;
        }
        return $html;
	}

    /**
     * Получает свойства страницы из POST-а и подготавливает данные для сохранения
     * @param array $data
     * @return Void
     */
    function save_properties($data)
    {
    	global $kernel;

        $array_properties = $this->get_properties();

        // Название страницы
        if ((isset($data['page_name'])) && !empty($data['page_name']))
        	$caption = $kernel->pub_str_prepare_set($data['page_name']);
        else
        	$caption = '';

		// Ссылка на другую страницу
		if ((isset($data['page_url'])) && !empty($data['page_url']))
        	$array_properties['link_other_page'] = $kernel->pub_str_prepare_set($data['page_url']);
        else
			unset($array_properties['link_other_page']);

        // Флаг что тайтл другой
        if (isset($data['flag_name_page_title']))
			$array_properties['title_other'] = false;
        else
        	$array_properties['title_other'] = true;

        //Флаг доступа к странице только авотризированных пользователей
        if (isset($data['only_auth']))
			$array_properties['only_auth'] = true;
        else
        	$array_properties['only_auth'] = false;


        // Заголовок страницы
        if ((isset($data['page_title'])) )
        	$array_properties['name_title'] = $kernel->pub_str_prepare_set($data['page_title']);

        // Шаблон страницы
        if ((isset($data['page_template'])) && (!empty($data['page_template'])) && !isset($data['flag_template']))
        	$array_properties['template'] = $kernel->pub_str_prepare_set($data['page_template']);
        else
        	unset($array_properties['template']);
        if ( isset($data['page_id'])
            && $this->id_curent_page != $data['page_id']
            && $this->id_curent_page != "index" //не меняем index
            && $kernel->is_valid_sitepage_id($data['page_id'])
            )
        {
        	//Значит существет новый id, который нужно назначить странице, помимо этого,
        	//нужно поменять все ссылки, которые есть на этот id в mySql базе, а также
        	//имена файлов с контентом
        	if ($kernel->priv_page_id_replace($this->id_curent_page, trim($data['page_id'])))
        		$kernel->priv_page_properties_set(trim($data['page_id']), $array_properties, $caption);
        }
        else
        {
            $kernel->priv_page_properties_set($this->id_curent_page, $array_properties, $caption);
        }
    }


    /**
     * Получает свойства страницы прописанные модулями из POST-а и подготавливает данные для сохранения
     * @param array $values
     * @param array $inheritance
     * @return Void
     */
    function save_properties_addon($values, $inheritance)
    {
		global $kernel;
        $array_properties = $this->get_properties();
        //Теперь нужно сохранить свойства страницы, которые установил каждый модуль
        //сначала узнаем массив всех этих свойств
		$tmp_modul = new manager_modules();
		$all_prop = $tmp_modul->return_all_properties_page_all_modules();
        if (!empty($all_prop))
		{
        	foreach ($all_prop as $id_prop)
        	{
        	    //Если стоит галочка наследования, то в скрытом инпуте будет 'on'
        	    //и мы просто удалим это свойство из массива
        		if (isset($inheritance['ppf_'.$id_prop]))
                    unset($array_properties[$id_prop]);
                elseif (isset($values[$id_prop]))
        			$array_properties[$id_prop] = $values[$id_prop];
        	}
		}
        $kernel->priv_page_properties_set($this->id_curent_page, $array_properties, $array_properties['caption']);
    }


    /** Возвращает подготовленный массив вызова заданного макроса модуля
     * @param integer $id_str_macros
     * @return array
     */
    function return_full_link($id_str_macros)
    {
		global $kernel;

		$ret_str['id_mod'] = '';
		$ret_str['id_action'] = '';
		$ret_str['run'] = '';

		if ($id_str_macros > 0)
		{
            $row=$kernel->db_get_record_simple('_action',"id='".$id_str_macros."'");
       		if ($row)
			{
				$ret_str['id_mod'] = trim($row['id_module']);
				$ret_str['id_action'] = trim($row['id']);
				$run = array();
				$run['name'] = $row['link_str'];
				$run['param'] = $row['param_array'];
				$ret_str['run'] = $run;
			}
	    }
    	return $ret_str;
    }

    /** Сохраняет поле serialized для страницы
    *   @param  array $modules
    *   @param  array $inheritance
    *   @param  array $postprocessors
    */
    function save_serialized($modules, $inheritance, $postprocessors)
    {
    	global $kernel;

    	if (!empty($inheritance) && is_array($inheritance))
    	{
        	foreach ($inheritance as $key => $value)
        	{
        	    //if (!empty($value))
        	       unset($modules[$key]);
        	}
    	}
        $is_root = $kernel->priv_admin_is_root();
    	$link_array = $modules;
    	$serialize = array();
        $system_postprocessors = $kernel->get_postprocessors();
        foreach ($link_array as $key => $val)
        {
            //если это не рут-админ, а метка вида ***_admin - не обрабатываем её
            if (!$is_root && preg_match('~_admin$~',$key))
                continue;
            if (is_numeric($val) || empty($val))
                $serialize[$key] = $this->return_full_link($val);
            $serialize[$key]['postprocessors']=array();
            if (array_key_exists($key,$postprocessors))
            {//для этой страницы есть постпроцессоры
                foreach($postprocessors[$key] as $pp)
                {
                    if (array_key_exists($pp,$system_postprocessors))
                        $serialize[$key]['postprocessors'][]=$pp;
                }
            }
        }

        if (!$is_root)
        {//если не рут-админ, апдейт выборочный (подставляем старые значения, где их нет в готовом массиве)
            $prev_rec = $kernel->db_get_record_simple('_structure','id="'.$this->id_curent_page.'"');
            if (!$prev_rec)
                return;
            $prev_ser = unserialize($prev_rec['serialize']);
            if (is_array($prev_ser))
            {
                foreach ($prev_ser as $k=>$v)
                {
                    if (!isset($serialize[$k]))
                        $serialize[$k]=$v;
                }
            }
        }

    	//Теперь можно прописать весь serialize сразу в таблицу, так как
		//в нем указаны только те ссылки, которые поставлены в соответствие
		$query = 'UPDATE `'.$kernel->pub_prefix_get().'_structure`
		          SET serialize = "'.mysql_real_escape_string(serialize($serialize)).'"
		          WHERE id = "'.$this->id_curent_page.'"';

		$kernel->runSQL($query);
    }
}