<?php

/**
 * Модуль "Обратная Связь"
 *
 * @author Александр Ильин mecommayou@gmail.com , s@nchez
 * @copyright ООО "АртПром" 2011
 * @name feedback
 * @version 2.0
 *
 * Модуль  обратной  связи.  Отбражает форму обратной связи (@form) и
 * осуществляет формирование и отпрвку письма менеджеру. Доступно два
 * варианта   отправки   писем:  html  (@email_html)   и   plain text
 * (@email_text), выбрать можно в настройках действия.  Так же указы-
 * ваеться  желаемый  шаблон,  email  менеджера, имя менеджера и тема
 * письма.
 *
 * В  случае успешной  отправки сообщения  пользователю отображаеться
 * блок @processing_succses,  если отправить  сообщения  не удалось -
 * @processing_fail
 *
 * = ФОРМА ОБРАТНОЙ СВЯЗИ ДОЛЖНА СООТВЕТСТВОВАТЬ СЛЕДУЮЩИМ ПРАВИЛАМ =
 *
 * 1. Содержать   ТОЛЬКО   html   элементы  input  типов: "checkbox",
 *    "password", "text" и html элемент select
 *
 * 2. Элемент input типа "checkbox" должен иметь значение "&checked&"
 *
 * 3. Все элементы формы должны иметь имена  соответствующие шаблону:
 *    "values[ID_ЭЛЕМЕНТА]". Каждый элемент должен иметь уникальный
 *    ID.
 *
 * 4. Блоки @email_text и @email_html должны сожержать метки соответ-
 *    ствующие шаблону - "%ID_ЭЛЕМЕНТА%",  на их места будут подстав-
 *    ленны соответствующие данные введенные пользователем.  Для эле-
 *    ментов типа  "checkbox"  необходимо определить значения языовых
 *    переменных:  @feedback_property_field_no  -  для НЕотмеченного,
 *    @feedback_property_field_yes - для отмеченного.
 *
 */
class feedback
{
    /**
     * Содержит массив распаршенных шаблонов
     *
     * @var array
     */
    private $templates = array();

    /**
     * Действие по умолчанию
     *
     * @var string
     */
    private $action_default;

    /**
     * Название перемнной в GET запросе определяющей действие
     *
     * @var string
     */
    //private $action_name = 'view';



    /**
     * Возвращает урл, на который произойдёт возврат
     * @return string
     */
    private function get_return_url()
    {
        $url = $_SERVER['REQUEST_URI'];
        $url = preg_replace('~feedback(\d+)-(\d+)=form_processing~U', "", $url);
        if (strpos($url, "?") === false)
            $url.="?";
        elseif (substr($url,-1)!="&")
            $url.="&";
        return $url;
    }

    /**
     * Публичный метод для отображения формы обратной связи
     *
     * @param string $template Путь к шаблону формы
     * @param string $email Email для отправки соощения
     * @param string $type Тип письма (html | text)
     * @param string $name Имя менеджера магазина
     * @param string $theme Тема письма
     * @return string
     */
    public function pub_show_form($template, $email, $type, $name, $theme)
    {
        global $kernel;

        $this->set_action_default('form_show');
        $this->set_templates($kernel->pub_template_parse($template));
        switch ($this->get_action_value())
        {
            // Отобразим форму обратной связи (Действие по умолчанию)
        	default:
        	case 'form_show':
                $file = basename($template);
        	    $settings = $this->pub_get_js('modules/feedback/templates_user/'.$file.'.ini');
        	    $content = $this->get_template_block('form');
        	    $content = str_replace('%form_action%', $this->get_return_url().$this->get_action_name().'=form_processing', $content);
        	    $content = $content.$settings;
        		break;

            // Обработаем данные введенные пользователем
        	case 'form_processing':
        	    $input_values = $kernel->pub_httppost_get('values');
                if (isset($input_values['message']))
                    $input_values['message']=nl2br(htmlspecialchars($input_values['message']));
                if ($type=='html')
                    $message = $this->get_template_block('email_html');
                else
                    $message = $this->get_template_block('email_text');

        	    $message = str_replace(array_map(array('feedback', 'array_map_marks'), array_keys($input_values)), $input_values, $message);
        	    $message = preg_replace('/\%[a-zA-Z0-9]+\%/', '[#feedback_property_field_no#]', $message);
        	    $message = preg_replace('/\&[a-zA-Z0-9]+\&/', '[#feedback_property_field_yes#]', $message);
        	    $message = $kernel->priv_page_textlabels_replace($message);

        	    $sended = $kernel->pub_mail(array($email), array($name), 'noreply@'.$_SERVER['HTTP_HOST'], 'Module: FeedBack', $theme, $message);
                $rurl=$this->get_return_url().$this->get_action_name().'=';
        	    if ($sended > 0)
                    $rurl.='processing_success';
        	    else
                    $rurl.='processing_fail';
                $kernel->pub_redirect_refresh_global($rurl);
        	    break;

        	// Выведем собщение об успешной отправке данных
        	case 'processing_success':
        	    $content = $this->get_template_block('processing_succses');
        	    break;

            // Выведем собщение невозможности отправки
        	case 'processing_fail':
        	    $content = $this->get_template_block('processing_fail');
        	    break;
        }

        return isset($content)?$content:null;
    }

    /**
     * Функция по файлу настроек формирует код jscript для формы
     *
     * @param string $filename
     * @return string
     */
    function pub_get_js($filename)
    {
        $settings = parse_ini_file($filename, true);
        $lines = array();
        foreach ($settings as $element => $config) {

            $line = $this->get_template_block('jscript_line_1');
            $line = str_replace('%element%', $element, $line);
            $lines[] = $line;

            foreach ($config as $name => $value) {

                $line = $this->get_template_block('jscript_line_2');
                $line = str_replace('%element%', $element, $line);
                $line = str_replace('%name%', $name, $line);
                $line = str_replace('%value%', $value, $line);
                $lines[] = $line;

            }
        }

        return $this->get_template_block('jscript_start').implode("\n", $lines).$this->get_template_block('jscript_end');
    }

    /**
     * Вспомогательная
     *
     * @param string $element
     * @return string
     */
    private  function array_map_marks($element)
    {
        return '%'.$element.'%';
    }

    /**
     * Возвращает указанный блок шаблона
     *
     * @param string $block_name Имя блока
     * @return mixed
     */
    private function get_template_block($block_name)
    {
        return ((isset($this->templates[$block_name]))?(trim($this->templates[$block_name])):(null));
    }

    /**
     * Устанавливает шаблоны
     *
     * @param array $templates Массив распаршенных шаблонов
     */
    private function set_templates($templates)
    {
        $this->templates = $templates;
    }

    /**
     * Возвращает название перемнной в GET запросе определяющей действие
     *
     * @return string
     */
    private function get_action_name()
    {
        global $kernel;
    	return $kernel->pub_module_id_get()."-".$kernel->get_current_actionid();
    }

    /**
     * Возвращает значение указанного действия, если установленно или значение по умолчанию
     * @return string
     */
    private function get_action_value()
    {
        global $kernel;
        $action_name=$this->get_action_name();
        if ($kernel->pub_httpget_get($action_name))
            return $kernel->pub_httpget_get($action_name);
        //elseif ($kernel->pub_httppost_get('values'))
        //    return "form_processing";
        else
            return $this->get_action_default();
    }

    /**
     * Возвращает значение действия по умолчанию
     *
     * @return string
     */
    private function get_action_default()
    {
        return $this->action_default;
    }

    /**
     * Устанавливает действие по умолчанию
     *
     * @param string $value Имя GET параметра определяющего действие
     */
    private function set_action_default($value)
    {
        $this->action_default = $value;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean true
     */
	public function interface_get_menu($menu)
	{
        $menu->set_menu_block('[#feedback_menu_label#]');
        $menu->set_menu("[#feedback_menu_edit_ini#]","edit_ini");

        $menu->set_menu_default('edit_ini');
	    return true;
	}

	/**
	 * Функция для отображаения административного интерфейса
	 *
	 * @return null
	 */
    public function start_admin()
    {
        global $kernel;

        $ini_blank = 'modules/feedback/templates_user/blank.ini';

        $content = '';
        switch ($kernel->pub_section_leftmenu_get())
        {
        	case 'edit_ini':
        	    if (!file_exists($ini_blank))
        	       $kernel->pub_file_save($ini_blank, '');

        	    $content = $this->priv_show_edit_ini($ini_blank);
        		break;

        	case 'save_cfg':
        	    $this->save_ini_file($kernel->pub_httppost_get(), $ini_blank);
                $content = $kernel->pub_json_encode(array('success'=>true,'result_message'=>'','redirect'=>'edit_ini'));
        	    break;

        	case 'add_cfg':
        	    $id_new = $kernel->pub_httppost_get('field_id');
        	    if (!empty($id_new))
        	    {
        	       $str = file_get_contents($ini_blank);
        	       $str .= "\n[".$id_new.']';
        	       $kernel->pub_file_save($ini_blank, $str);
        	    }
        	    $content = $kernel->pub_json_encode(array('success'=>true,'result_message'=>'','redirect'=>'edit_ini'));
        		break;

        	case 'delete':
        	    $settings = parse_ini_file($ini_blank, true);
        	    unset($settings[$kernel->pub_httpget_get('id')]);
        	    $this->save_ini_file($settings, $ini_blank);
        	    $kernel->pub_redirect_refresh('edit_ini');
        	    break;

        	case 'add_template':

        	    $new_name = $kernel->pub_httpget_get('filenew_name');
        	    if (preg_match("|^([a-zA-Z_0-9]+)$|",$new_name))
        	    {
        	       $this->priv_template_create(strtolower($new_name), $ini_blank);
        	    }
        	    $kernel->pub_redirect_refresh_reload('edit_ini');
        	    break;

        }

        return $content;
    }

    function save_ini_file($array, $filename)
    {
        global $kernel;
        $config = array();
        foreach ($array as $name => $properties)
        {
            $config[] = '['.$name.']';
            foreach ($properties as $property => $value)
            {
                $value = trim($value);
                if ($value != '')
                    $config[] = $property.' = "'.$value.'"';
            }
        }
        $kernel->pub_file_save($filename, implode("\n", $config));
    }

    function priv_show_edit_ini($filename)
    {
    	global $kernel;

    	$templates = $kernel->pub_template_parse('modules/feedback/templates_admin/edit_ini.html');
        $settings = parse_ini_file($filename, true);
        $lines = array();

        $types = array(
            'text'=>'[#feedback_field_type_blank1#]',
            'checkbox'=>'[#feedback_field_type_blank2#]',
            'textarea'=>'[#feedback_field_type_blank3#]'
        );
        $regexp_types = array(
            'numeric'=>'[#feedback_field_regexp_blank1#]',
            'email'=>'[#feedback_field_regexp_blank2#]',
            'string'=>'[#feedback_field_regexp_blank3#]',
            'text'=>'[#feedback_field_regexp_blank4#]'
        );
        foreach ($settings as $name => $properties)
        {
            $line = $templates['line'];
            $line = str_replace('%legend%', $name, $line);

            //Вставка заголовка поля
            if (isset($properties['label']))
                $line = str_replace('%label%', $properties['label'], $line);
            else
                $line = str_replace('%label%', '', $line);

            $type_lines = '';
            if (!isset($properties['type']))
                $properties['type']='';
            foreach ($types as $tk=>$tv)
            {
                if ($properties['type']==$tk)
                    $type_lines.='<option value="'.$tk.'" selected>'.$tv.'</option>';
                else
                    $type_lines.='<option value="'.$tk.'">'.$tv.'</option>';
            }
            $line = str_replace('%types%',$type_lines, $line);

            $regexp_lines = '';
            if (!isset($properties['regexp']))
                $properties['regexp']='';
            foreach ($regexp_types as $tk=>$tv)
            {
                if ($properties['regexp']==$tk)
                    $regexp_lines.='<option value="'.$tk.'" selected>'.$tv.'</option>';
                else
                    $regexp_lines.='<option value="'.$tk.'">'.$tv.'</option>';
            }
            $line = str_replace('%regexp_types%',$regexp_lines, $line);


            //Вставка обязательности заполнения
            if ((isset($properties['allowBlank'])) && (intval($properties['allowBlank']) == 1))
            {
                $line = str_replace('%allow_value%', "checked", $line);
                //$line = str_replace('%regexp_disabe%', "false", $line);
            }
            else
            {
                $line = str_replace('%allow_value%', '', $line);
                //$line = str_replace('%regexp_disabe%', "false", $line);
            }

            $lines[] = $line;
        }



        $content = $templates['form'];
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('save_cfg'), $content);
        $content = str_replace('%form_action_2%', $kernel->pub_redirect_for_form('add_cfg'), $content);
        $content = str_replace('%form_action_3%', $kernel->pub_redirect_for_form('add_template&filenew_name='), $content);
        $content = str_replace('%lines%', implode("\n", $lines), $content);

        $but_dis = "";
        if (count($settings) == 0)
            $but_dis = "disabled";

        $content = str_replace('%but1disabled%', $but_dis, $content);
        $content = str_replace('%but2disabled%', $but_dis, $content);

        return $content;
    }
    /**
     * Создание файла шаблона
     *
     * По файлу настроек создаётся файл шаблона, который затем и может быть о
     * тредактирован по необходимости
     * @param string $filename Имя вновь создаваемого файла
     * @param string $file_ini путь ini-файлу
     */
    private function priv_template_create($filename, $file_ini)
    {
        global $kernel;

        //Распарсим файл настроек
        $settings = parse_ini_file($file_ini, true);

        //Создание основного блока формы @form
        $templates_blank = $kernel->pub_template_parse('modules/feedback/templates_admin/blank_template.html');

        $html = $templates_blank['blank_form'];
        $html = "<!-- @form -->\n".$html;


        //Теперь необходимо добавить разные блоки, в зависимости от того,
        //какой тип имеет поле в форме
        $html_res = '';
        foreach ($settings as $id_feild => $properties)
        {
            $line = '';
            //Определим тип поля и получем нужный шаблон.
            switch ($properties['type'])
            {
                case "text":
                    $line = $templates_blank['blank_text'];
                    break;
                case "checkbox":
                    $line = $templates_blank['blank_checkbox'];
                    break;
                case "textarea":
                    $line = $templates_blank['blank_textarea'];
                    break;
            }
            $line = str_replace('%id%',      $id_feild,            $line);
            $line = str_replace('%caption%', $properties['label'], $line);
            $html_res .= $line;
        }

        $html = str_replace('%lines%', $html_res, $html);

        //Теперь добавим блок с шаблоном письма, отправляемого
        //менеджеру в формате HTML
        $html .= "\n<!-- @email_html -->\n".$templates_blank['blank_email_html'];
        $html_res = '';
        foreach ($settings as $id_feild => $properties)
        {
            $line = $templates_blank['blank_email_html_line'];
            $line = str_replace('%id%'     , "%".$id_feild."%"       , $line);
            $line = str_replace('%caption%', $properties['label'].":", $line);
            $html_res .= $line;
        }
        $html = str_replace('%lines%', $html_res, $html);

        //Всё тоже самое только для письма отправляемого в
        //обычном текстовом формате
        $html .= "\n<!-- @email_text -->\n".$templates_blank['blank_email_text'];
        $html_res = '';
        foreach ($settings as $id_feild => $properties)
        {
            $line = $templates_blank['blank_email_text_line'];
            $line = str_replace('%id%'     , "%".$id_feild."%"       , $line);
            $line = str_replace('%caption%', $properties['label'].":", $line);
            $html_res .= $line;
        }
        $html = str_replace('%lines%', $html_res, $html);

        //Добавим два блока с сообщениями о успешной отправке и ошибке
        //в отправке формы
        $html .= "\n<!-- @processing_succses -->\n".$templates_blank['blank_processing_succses'];
        $html .= "\n<!-- @processing_fail -->\n".$templates_blank['blank_processing_fail'];

        //и последнее, добавим блоки для переменных jscript
        $html .= "\n<!-- @jscript_start -->\n".$templates_blank['blank_jscript_start'];
        $html .= "\n<!-- @jscript_line_1 -->\n".$templates_blank['blank_jscript_line_1'];
        $html .= "\n<!-- @jscript_line_2 -->\n".$templates_blank['blank_jscript_line_2'];
        $html .= "\n<!-- @jscript_end -->\n".$templates_blank['blank_jscript_end'];

        //Записываем файл шаблона
        $kernel->pub_file_save('modules/feedback/templates_user/'.$filename.'.html', $html);

        //Кроме того, нужно переписать с таким же именем файл настроек (ini) что бы форма потом его
        //использоваала
        $kernel->pub_file_save('modules/feedback/templates_user/'.$filename.'.html.ini', file_get_contents($file_ini));

    }

}