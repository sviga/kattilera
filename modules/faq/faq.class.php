<?php

/**
 * Основной управляющий класс модуля «Вопросы и Ответы»
 *
 * Модуль предназначен для орагиназции FAQ и возможности пользователей
 * задавать новые вопросы
 * @copyright ArtProm (с) 2001-2011
 * @author Александр Ильин [Comma] mecomayou@mail.ru , s@nchez s@nchez.me
 * @version 2.0
 */

class faq
{
    var $template_array = array();                      // Содержит распаршенный шаблон
    var $one_admin = false;                             // Одна админка
    var $path_templates = "modules/faq/templates_user"; // Путь, к шаблонам модуля



    /**
     * Публичный метод для отображения списка разделов
     *
     */
    function pub_show_partitions($items_pagename='', $template='')
    {
        global $kernel;
        if (empty($template))
        {
            $template = $kernel->pub_modul_properties_get('template');
            if (!$template['isset'])
               return '[#faq_modul_errore1#]';
            $template = $template['value'];
        }
        $this->parse_template($template);


        $page = $items_pagename;
        if (empty($page))
            $page = $kernel->pub_page_current_get();

        // Данные из MySQL о категриях
        $partitions = $this->get_partitions();

        // Формирование контента
        $lines = '';
        foreach ($partitions as $data)
        {
            $link = $page.'.html?a=2&b='.$data['id'];
            $line = $this->template_array['partition_list_line'];
            $line = str_replace("%name%",$data['name'],$line);
            $line = str_replace("%link%", $link, $line);
            $lines .= $line;
        }

        $html  = $this->template_array['partition_list_begin'];
        $html .= $lines;
        $html .= $this->template_array['partition_list_end'];

        return $html;
    }


    /**
     * Метод управления выводом FAQ
     *
     * @param boolean $form false - не выводить форму вопроса для посетителя; true - выводить
     * @param string $template
     * @param integer $limit
     * @param string $page
     * @return string
     */
    function pub_faq($form = false, $template='', $limit=0, $page='')
    {
    	global $kernel;
    	$get_values = $kernel->pub_httpget_get();
        if (empty($page))
            $page = $kernel->pub_page_current_get();
        if (empty($template))
        {
            $template = $kernel->pub_modul_properties_get('template');
            if (!$template['isset'])
                return '[#faq_modul_errore1#]';
            $template = $template['value'];

        }
        $this->parse_template($template);


    	if (!isset($get_values['a']))
    	    $get_values['a'] = 1;
        else
            $get_values['a'] = intval($get_values['a']);

    	if (!isset($get_values['b']))
    	    $get_values['b'] = 0;
        else
            $get_values['b'] = intval($get_values['b']);

    	switch ($get_values['a'])
    	{
            /*
    	    //Отобразим список разделов
    	    case 1:
    	        $html = $this->create_form_partition();
    			break;

             */

    	    //Отобразим список вопросов в разделе
            default:
    		case 2:

                $html = $this->create_form_questions($get_values['b'],$page,$limit);
                break;

            //отобразим конкретный вопрос и ответ на него
    		case 3:
                $html = $this->create_form_question($get_values, $page);
    			break;

    	}

        if ($form)
            $html .= $this->pub_form($template);

    	return $html;
    }

    /**
     * Возвращает форму для задания вопроса
     *
     * @param string $template
     * @return string
     */
    function pub_form($template='')
    {
        global $kernel;

        if (empty($template))
        {
            $template = $kernel->pub_modul_properties_get('template');
            if (!$template['isset'])
                return '[#faq_modul_errore1#]';
            $template = $template['value'];
        }

        $this->parse_template($template);

        $moduleid = $kernel->pub_module_id_get();
        $postvars = $kernel->pub_httppost_get();
        $user = htmlspecialchars($kernel->pub_httppost_get('faq_user_name',false));
        $mail = htmlspecialchars($kernel->pub_httppost_get('faq_user_email',false));
        $quest = nl2br(htmlspecialchars($kernel->pub_httppost_get('faq_user_question',false)));

        if (isset($postvars['faq_user_button']) && !empty($user) && !empty($quest))
        {
            $sql = 'INSERT INTO `'.$kernel->pub_prefix_get().'_'.$moduleid.'_content` (
            `id`,`pid`, `description`, `answer`, `user`, `email`,`added`)
            VALUES (
                NULL,0,
            \''.mysql_real_escape_string($quest).'\',
            NULL,
            \''.mysql_real_escape_string($user).'\',
            \''.mysql_real_escape_string($mail).'\',
            "'.date("Y-m-d H:i:s").'");';
            $kernel->runSQL($sql);
            $adminEmail = $kernel->pub_modul_properties_get('email');
            if ($adminEmail['isset'])
            {//не пустой email админа
                $emails = explode(",",$adminEmail['value']);
                $mail_body = $this->template_array['email2admin'];
                $mail_body = str_replace("%question%",$quest, $mail_body);
                $mail_body = str_replace("%user%",$user, $mail_body);
                $mail_body = str_replace("%email%",$mail, $mail_body);
                $subj = $kernel->pub_modul_properties_get('new_question_email_subj');
                if ($subj['isset'])
                    $subj = $subj['value'];
                else
                    $subj = 'Новый вопрос';
                foreach ($emails as $email)
                {
                    $email = trim($email);
                    $kernel->pub_mail(array($email), array($email), $email, 'admin', $subj, $mail_body, false);
                }
            }

            $html = $this->template_array['form_submit_ok'];
        }
        else
            $html = $this->template_array['form'];;

    	return $html;
    }



    function get_partitions()
    {
        global $kernel;
        return $kernel->db_get_list_simple("_".$kernel->pub_module_id_get()."_partitions","true");
    }

    /**
     * Формирует список разделов
     *
     * @return string
     */
    function create_form_partition()
    {
        global $kernel;

        $page = $kernel->pub_page_current_get();
        // Данные из MySQL о категриях
        $partitions = $this->get_partitions();
        // Формирование контента
        $lines = '';
        foreach ($partitions as $data)
        {
            $link = $page.'.html?a=2&amp;b='.$data['id'];
            $line = $this->template_array['partition_list_line'];
            $line = str_replace("%name%",$data['name'],$line);
            $line = str_replace("%link%", $link, $line);
            $lines .= $line;
        }

        $html  = $this->template_array['partition_list_begin'];
        $html .= $lines;
        $html .= $this->template_array['partition_list_end'];

        return $html;
    }

    function get_partition_questions($pid, $limit=0,$only_actual=false)
    {
        global $kernel;
        $qlimit = null;
        if ($pid==0)
        {
            if ($only_actual)
                $cond = "`answer` IS NOT NULL";
            else
                $cond = "true";
            $cond.=" ORDER BY `added` DESC";
            if ($limit>0)
                $qlimit = $limit;
        }
        else
        {
            $cond = "`pid`='".$pid."' ORDER BY `added` DESC";
            if ($only_actual)
                $cond = "`answer` IS NOT NULL AND ".$cond;
        }
        return $kernel->db_get_list_simple("_".$kernel->pub_module_id_get()."_content",$cond,"*",0, $qlimit);
    }

    /**
     * Формирует форму с вопросами выбранного раздела
     *
     * @param integer $pid
     * @param string $page
     * @param integer $limit
     * @return string
     */

    function create_form_questions($pid, $page, $limit)
    {
        global $kernel;
        $questions = $this->get_partition_questions($pid,$limit,true);
        // Формирование конетента
        $lines = '';
        foreach ($questions as $data)
        {
            $link = $page.'.html?a=3&b='.$data['id'];
            $line = $this->template_array['partition_content_line'];
            $line = str_replace("%description%",$data['description'],$line);
            $line = str_replace("%answer%",$data['answer'],$line);
            $line = str_replace("%user%",$data['user'],$line);
            $line = str_replace("%email%",$data['email'],$line);
            $line = str_replace("%added%",$data['added'],$line);
            $line = str_replace("%question%",$data['question'],$line);
            $line = str_replace("%link%", $link, $line);
            $lines .= $line;
        }

        $html = $this->template_array['partition_content_begin'];
        $html .= $lines;
        $html .= $this->template_array['partition_content_end'];

        $partition = $this->get_partition($pid);
        if ($partition)
        {
            //Добавим раздел в дорогу сайта
            $kernel->pub_waysite_set(array('url' => $page.".html", 'caption' => $partition['name']));
            //Добавим в тайтл раздел
            $kernel->pub_page_title_add($partition['name']);
        }
        return $html;
    }

    function get_question($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_".$kernel->pub_module_id_get()."_content","id=".$id);
    }

    function get_partition($id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_".$kernel->pub_module_id_get()."_partitions","id=".$id);
    }

    /**
     * Формирует форму с конкретным вопросом и ответом на него
     *
     * @param array $get_values массив переменных _GET
     * @param string $page
     * @return string
     */
    function create_form_question($get_values, $page='')
    {
        global $kernel;
        $data = $this->get_question(intval($get_values['b']));
        if (!$data)
            $kernel->pub_redirect_refresh_global("/".$page.".html");

        // Формирование контента
        $line = $this->template_array['answer_table'];
        $line = str_replace("%question%", $data['question'],$line);
        $line = str_replace("%description%", $data['description'],$line);
        $line = str_replace("%answer%", $data['answer'],$line);
        $line = str_replace("%user%", $data['user'],$line);
        $line = str_replace("%email%", $data['email'],$line);
        $line = str_replace("%added%", $data['added'],$line);
        $line = str_replace("%page%", $page,$line);
        $line = str_replace("%id%", $data['pid'],$line);


        $partition = $this->get_partition(intval($data['pid']));

        $kernel->pub_waysite_set(array('url' => $page.'.html?a=2&b='.$data['pid'], 'caption' => $partition['name']));
        $kernel->pub_waysite_set(array('url' => $page.'.html?a=3&b='.$data['id'], 'caption' => $data['question']));

        //Добавим информацию в тайтл
        $kernel->pub_page_title_add($partition['name']);
        $kernel->pub_page_title_add($data['question']);

        return $line;
    }


    // Наборы внутренних методов модуля
    /**
    * Разбирает шаблон, создает $this->template_array
    * @return void
    * @param String $filename Путь к файлу шаблонов
    */
    function parse_template($filename)
    {
        global $kernel;

        $this->template_array = $kernel->pub_template_parse($filename);
    }

    function priv_show_date_picker()
    {
        $this->parse_template('modules/faq/templates_admin/date_picker.html');
        $content = $this->template_array['date_picker'];
        return $content;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления построением меню
     * @return boolean
     */
	public function interface_get_menu($menu)
	{
        $menu->set_menu_block('[#news_menu_label#]');
        $menu->set_menu("[#faq_menu_list#]","show_list");
        $menu->set_menu("[#faq_menu_users_questions#]","users_questions");
        $menu->set_menu("[#faq_menu_partitions#]","partitions");
        $menu->set_menu_default('users_questions');
        $menu->set_menu_block('[#faq_date_block#]');
        $menu->set_menu_plain($this->priv_show_date_picker());
        $this->priv_show_date_picker();
	    return true;
	}


    /**
     * Предопределйнный метод, используется для вызова административного интерфейса модуля
     *
     * @return string
     */
    function start_admin()
    {
        global $kernel;

        $get_values = $kernel->pub_httpget_get();
        $moduleid = $kernel->pub_module_id_get();
        
        $template = "modules/faq/templates_admin/container.html";
        $this->parse_template($template);
        
        $container_begin = $this->template_array['container_begin'];
        $container_end = $this->template_array['container_end'];
        
        $html = '';
        $view = $kernel->pub_section_leftmenu_get();
        switch ($view)
        {
            case "partitions":
                $template = "modules/faq/templates_admin/partitions.html";
                $this->parse_template($template);
                $partitions = $this->get_partitions();
                $lines = '';
                foreach ($partitions as $value)
                {
                    $line = $this->template_array['row'];
                    $line = str_replace("%pid%"        , $value['id']                     , $line);
                    $line = str_replace("%name%"        , $value['name']                     , $line);
                    $line = str_replace("%action_edit%" , "partition_edit&id=".$value['id']  , $line);
                    $line = str_replace("%action_delet%", "partition_delete&id=".$value['id'], $line);
                    $line = str_replace("%action_addco%", "add_quest&partition_id=".$value['id'], $line);
                    $lines.=$line;
                }
                $html = $container_begin;
                $html .= $this->template_array['table'];
                $html = str_replace('%rows%', $lines, $html);
                $html .=$this->priv_form_add_partition();
                $html .= $container_end;
                break;
            case "faq_import_csv":
                $partid = intval($_POST['csvpart']);
                if (is_uploaded_file($_FILES['csvfile']["tmp_name"]) && $partid>0)
                {

                    $handle = fopen($_FILES["csvfile"]["tmp_name"], "r");
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE)
                    {
                        if (count($data)!=3)
                            continue;
                        $sql = 'INSERT INTO `'.$kernel->pub_prefix_get().'_'.$moduleid.'_content` (
                            `id`,`pid`, `description`, `answer`, `question`,`user`, `email`,`added`)
                            VALUES (
                            NULL,
                            '.$partid.', "'.mysql_real_escape_string($data[1]).'", "'.mysql_real_escape_string($data[2]).'",
                            "'.mysql_real_escape_string($data[0]).'","","","'.date("Y-m-d H:i:s").'" )';
                        $kernel->runSQL($sql);
                    }
                    fclose($handle);
                }
                $kernel->pub_redirect_refresh_reload('show_list');
                break;
            //Добовляем новый раздел
            case "faq_add_partition":

                if (!isset($get_values['id']) || (empty($get_values['id'])))
                    $get_values['id'] = "NULL";

                $this->priv_partition_add($get_values['id']);
                $kernel->pub_redirect_refresh_reload('partitions');
                break;

            //Форма редактирования раздела
            case "partition_edit":
                $html = $container_begin;
                
                if (isset($get_values['id']))
                    $html .= $this->priv_form_add_partition($get_values['id']);
                    
                $html .= $container_end;
                break;

            //Удаляем раздел
            case "partition_delete":
                $pid = intval($get_values['id']);
                $sql = "DELETE FROM `".$kernel->pub_prefix_get()."_".$moduleid."_partitions`
                        WHERE `id`=".$pid."
                        LIMIT 1";
                $kernel->runSQL($sql);

                $sql = "DELETE FROM `".$kernel->pub_prefix_get()."_".$moduleid."_content`
                        WHERE `pid`=".$pid."";
                $kernel->runSQL($sql);
                $kernel->pub_redirect_refresh('partitions');
                break;

            //Выводим форму для редактирования или добавления вопроса
            case "add_quest":

                $sel_par_id = 0;
                if (isset($get_values['partition_id']))
        	       $sel_par_id = $get_values['partition_id'];

        	    $content_id = 0;
        	    if (isset($get_values['content_id']))
                    $content_id = $get_values['content_id'];
                $html = $container_begin;
                $html .= $this->priv_create_form_element($sel_par_id, $content_id);
                $html .= $container_end; 
                break;

            //Сохранение отредактированного вопроса
            case 'element_save':
                $content_id = intval($kernel->pub_httppost_get('id'));
                $this->priv_element_save($content_id);
                $kernel->pub_redirect_refresh_reload('show_list');
                break;

            //удаяляем ворпос из FAQ
            case "del_quest":

                if (isset($get_values['content_id']))
                {
                    $sql = "DELETE FROM `".$kernel->pub_prefix_get()."_".$moduleid."_content`
                            WHERE `id`=".$get_values['content_id'].";";
                    $kernel->runSQL($sql);
                }
                if ($kernel->pub_httpget_get('redirect_qusers'))
                    $kernel->pub_redirect_refresh('users_questions');
                else
                    $kernel->pub_redirect_refresh('show_list');
                break;

            //Отобразить список вопросов заданных с сайта
            default:
            case "users_questions":

                $template = "modules/faq/templates_admin/usersquestions.html";
                $this->parse_template($template);

                $sql = "SELECT content.*,partitions.name AS pname FROM ".$kernel->pub_prefix_get()."_".$moduleid."_content AS content
                        LEFT JOIN ".$kernel->pub_prefix_get()."_".$moduleid."_partitions AS partitions ON partitions.id=content.pid
                        WHERE content.answer IS NULL ORDER BY `added` DESC";
                $res = $kernel->runSQL($sql);

                if (mysql_num_rows($res)>0)
                {
                    // Подготавливаем массив с разделами
                    $lines = '';
                    while ($data = mysql_fetch_assoc($res))
                    {
                        $line = $this->template_array['line'];
                        $line = str_replace("%user_name%",     $data['user'],        $line);
                        $line = str_replace("%user_email%",    $data['email'],       $line);
                        $line = str_replace("%question%",      $data['question'], $line);
                        $line = str_replace("%description%",   $data['description'], $line);
                        $line = str_replace("%content_id%",    $data['id'],          $line);
                        $line = str_replace("%partition_id%",  $data['pid'],         $line);
                        $line = str_replace("%partition_name%",$data['pname'],         $line);
                        $line = str_replace("%added%",         $data['added'],         $line);
                        $line = str_replace("%question_edit%", "add_quest&content_id=".$data['id']."&partition_id=".$data['pid'], $line);
                        $line = str_replace("%action_delet_q%","del_quest&content_id=".$data['id']."&redirect_qusers=1", $line);

                        $lines .= $line;
                    }
                    $html = $container_begin;
                    $html .= $this->template_array['begin'].$lines.$this->template_array['end'];
                    $html .= $container_end;
                    mysql_free_result($res);
                }
                else
                    $html = $container_begin."[#faq_menu_users_questions_nonew#]".$container_end;

                break;

            // Выводим список разделов
            case "show_list":
                $html = $container_begin;
                $html .= $this->priv_create_list_partition();
                $html .= $container_end;
                break;
        }
        return $html;
    }

    /**
     * Выводит форму для управления разделами
     *
     * @access private
     * @return string
     */
    function priv_create_list_partition()
    {
        global $kernel;

        // Предустановки
        $template = $kernel->pub_template_parse('modules/faq/templates_admin/form_admin_table.html');
        $list_table = $template["table"];

        $pid = intval($kernel->pub_httpget_get('pid'));
        $date = $kernel->pub_httpget_get('date');

        $moduleid = $kernel->pub_module_id_get();
        $sql = "SELECT content.*,partitions.name AS pname FROM ".$kernel->pub_prefix_get()."_".$moduleid."_content AS content
                LEFT JOIN ".$kernel->pub_prefix_get()."_".$moduleid."_partitions AS partitions ON partitions.id=content.pid ";

        if ($pid>0)
            $sql.=" WHERE pid=".$pid;
        elseif(!empty($date))
            $sql.=" WHERE `added`>='".$date." 00:00:00' AND `added`<='".$date." 23:59:59'";
        $sql.=" ORDER BY `added` DESC";

        $res = $kernel->runSQL($sql);

        // Парсим шаблоны
        $lines='';
        $i=1;
        while ($value=mysql_fetch_assoc($res))
        {
            $line = $template['row'];
            if (empty($value['user']))
                $value['user'] = "Аноним";
            $line = str_replace("%num%", $i++,$line);
            $line = str_replace("%added%", $value['added'],$line);
            $line = str_replace("%partition_name%", $value['pname'],$line);
            $line = str_replace("%question%", $value['question'],$line);
            $line = str_replace("%user_name%", $value['user'],$line);
            $line = str_replace("%user_email%", $value['email'],$line);
            $line = str_replace("%action_delet_q%", "del_quest&content_id=".$value['id'], $line);
            $line = str_replace("%question_edit%", "add_quest&content_id=".$value['id'], $line);
            if (empty($value['answer']))
                $line  = str_replace('%has_answer%', $template['no_answer'], $line);
            else
                $line  = str_replace('%has_answer%', $template['has_answer'], $line);


            $lines .= $line;
        }
        $list_table = str_replace('%rows%', $lines, $list_table);
        $list_table = str_replace('%total%', mysql_num_rows($res), $list_table);
        mysql_free_result($res);


        return $list_table;
    }

    /**
     * формирует форму для добавления или редактирования раздела
     *
     * @param integer|string $id ID
     * @return string
     */
    function priv_form_add_partition($id = "")
    {
        global $kernel;

        $template  = "modules/faq/templates_admin/form_add_them.html";

        $this->parse_template($template);

        $html = $this->template_array['table'];

        //Возможно это редактирование и надо узнать текущее значение формы
        $value       = "";
        $form_label  = "[#faq_admin_table_label#]";
        $name_button = "[#faq_button_name_add_partition#]";
        if (!empty($id))
        {
            $part = $this->get_partition(intval($id));
            if ($part)
                $value = $part['name'];
            $form_label  = "[#faq_admin_table_label_edit#]";
            $name_button = "[#faq_button_save#]";
        }

        $html_line = str_replace("%value%", $value, $this->template_array['line']);

        $html = str_replace("%lines%"      , $html_line, $html);
        $html = str_replace("%action%"     , $kernel->pub_redirect_for_form("faq_add_partition&amp;id=".$id), $html);
        $html = str_replace("%form_label%" , $form_label, $html);
        $html = str_replace("%name_button%", $name_button, $html);


        //4 csv import
        if (empty($id))
        {
            $html .= $this->template_array['import'];
            $parts = $this->get_partitions();
            $plines = "";
            foreach ($parts as $part)
            {
                $pline = $this->template_array['part_select_line'];
                $pline = str_replace("%ovalue%", $part['id'], $pline);
                $pline = str_replace("%oname%", htmlspecialchars($part['name']), $pline);
                $plines.=$pline;
            }
            $html = str_replace("%action_import%", $kernel->pub_redirect_for_form("faq_import_csv"), $html);
            $html = str_replace("%part_select_lines%", $plines, $html);
        }
        return $html;
    }


    /**
     * Добавляет раздел или заменяет имя уже существующего
     *
     * @param integer $id ID раздела при изменении имени и NULL при добавлении нового
     * @return boolean
     */
    function priv_partition_add($id)
    {
        global $kernel;

        $pname = $kernel->pub_httppost_get('partition_name',false);
        if (empty($pname))
            return false;
        $sql = 'REPLACE `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_partitions`
                  (`id`, `name`)
                VALUES ('.$id.', \''.mysql_real_escape_string(htmlspecialchars($pname)).'\');';

        if (!$kernel->runSQL($sql))
            return false;

        return true;
    }


    function priv_create_form_element($partition = 0, $content_id = 0)
    {
        global $kernel;

        // Вызываем яваскрипт-форму для текста
        $content = new edit_content();
        $content->set_edit_name('faqcontent');
        $content->set_simple_theme(true);


        $template = "modules/faq/templates_admin/add_faq.html";
        $this->parse_template($template);

        $html = $this->template_array['table'];

        if ($content_id > 0)//Значит это конкретный вопрос и узнаем что у него уже есть
            $result = $this->get_question(intval($content_id));
        else //новый вопрос
            $result = array('id'=>0,'question'=>'','user'=>'','email'=>'','description'=>'', 'answer'=>'','pid'=>0);

        $html = str_replace("%id%", $result['id'], $html);
        $html = str_replace("%question%", $result['question'], $html);
        $html = str_replace("%user_name%",$result['user'], $html);
        $html = str_replace("%user_email%",$result['email'], $html);
        $html = str_replace("%description%",$result['description'], $html);
        $content->set_content($result['answer']);

        // Подготовка массива для создания select'а
        if ($partition==0)
            $partition = $result['pid'];
        $partitions = $this->get_partitions();
        // Создаем select
        $catslist = '<select name="selected_category"><option value="0">--не выбрано--</option> ';
        foreach ($partitions as $data)
        {
            if ($data['id']==$partition)
                $catslist.='<option value="'.$data['id'].'" selected>'.$data['name'].'</option>';
            else
                $catslist.='<option value="'.$data['id'].'">'.$data['name'].'</option>';
        }
        $catslist.='</select>';
        $html = str_replace("%category_list%",$catslist,$html);


        $html = str_replace("%action%", $kernel->pub_redirect_for_form("element_save"), $html);
        $html = str_replace("%content_id%", $content_id, $html);
        $html = str_replace("%editor%", $content->create(), $html);

        return $html;
    }

    /**
     * Сохраняет отредактированный вопрос в базе
     *
     * @param integer $content_id ID вопроса, если он уже существует
     * @return boolean
     */
    function priv_element_save($content_id = 0)
    {
        global $kernel;

        $question = $kernel->pub_httppost_get('faq_add_content_question');
        $content = $kernel->pub_httppost_get('faqcontent');
        $user = $kernel->pub_httppost_get('user');
        $descr = $kernel->pub_httppost_get('faq_description');
        $email = $kernel->pub_httppost_get('email');
        $catid =  intval($kernel->pub_httppost_get('selected_category'));
        if ($content_id==0)
        {
            $sql = 'INSERT INTO `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_content`
                    (`pid`, `question`, `answer`, `user`, `email`, `description`,`added`)
                    VALUES
                    (
                    "'.$catid.'",
                    "'.$question.'",
                    "'.$content.'",
                    "'.$user.'",
                    "'.$email.'",
                    "'.$descr.'",
                    "'.date("Y-m-d H:i:s").'");';


        }
        else
        {
            $sql = 'UPDATE `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_content`
                    SET `pid`='.$catid.',
                        `question`="'.$question.'",
                        `answer`="'.$content.'",
                        `user`="'.$user.'",
                        `email`="'.$email.'",
                        `description`="'.$descr.'"
                    WHERE id='.$content_id;

            $questionRec = $this->get_question($content_id);

            //отправка письма если надо
            $adminEmail = $kernel->pub_modul_properties_get('email');

            if (empty($questionRec['answer']) && $kernel->pub_is_valid_email($email) && $adminEmail['isset'])
            {
                $adminEmail = explode(",",$adminEmail['value']);
                $adminEmail = $adminEmail[0];
                if (!$kernel->pub_is_valid_email($adminEmail))
                    $adminEmail = 'admin@'.$_SERVER['HTTP_HOST'];

                $moduleTpl = "modules/faq/templates_admin/add_".$kernel->pub_module_id_get().".html";
                if (file_exists($moduleTpl))
                    $template = $moduleTpl;
                else
                    $template = "modules/faq/templates_admin/add_faq.html";
                $this->parse_template($template);
                $mail_body = $this->template_array['email2user'];
                $mail_body = str_replace('%added%', $questionRec['added'], $mail_body);
                $mail_body = str_replace('%user%', $user, $mail_body);
                $mail_body = str_replace('%question%', $question, $mail_body);
                $mail_body = str_replace('%description%', $descr, $mail_body);
                $mail_body = str_replace('%answer%', $content, $mail_body);


                $subj = $kernel->pub_modul_properties_get('answer_email_subj');
                if ($subj['isset'])
                    $subj = $subj['value'];
                else
                    $subj = 'Ответ на ваш вопрос на сайте '.$_SERVER['HTTP_HOST'];
                $kernel->pub_mail(array($email),
                                  array($email),
                                  $adminEmail,
                                  'admin',
                                  $subj,
                                  $mail_body,
                                  false
                                  );
            }
        }

        if (!$kernel->runSQL($sql))
            return false;
        return true;
    }
}
