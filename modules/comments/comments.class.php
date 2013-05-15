<?php
require_once realpath(dirname(__FILE__)."/../../")."/include/basemodule.class.php";
/**
 * Модуль "Комментарии"
 *
 * @author Александр Ильин mecommayou@gmail.com
 * @copyright ArtProm (с) 2001-2008
 * @name comments
 * @version 1.0 beta
 *
 */
class comments extends BaseModule
{
    private $offset_name='offset';

    const ADMNAME = 'Админ';//добавлено aim ... админ сайта, от чьего имени будут добавлятся комменты из админки
	                        //Измените по своему усмотрению

    /**
     * Имя параметра при успешной публикации
     *
     * @var string
     */
    private $publish_success_param = 'published_successfully';

    /**
     * Имя параметра при публикации, которая должна быть промодерирована админом
     *
     * @var string
     */
    private $publish_2moderate_param = 'published_need_approve';

    /**
     * Префикс путей к шаблонам административного интерфейса//
     *
     * @var string
     */
    private $templates_admin_prefix = 'modules/comments/templates_admin/';

    public function __construct()
    {
    	global $kernel;
    	if ($kernel->pub_httpget_get('flush'))
            $kernel->pub_session_unset();
    }

    /*
    function show_selection($template)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($template));
        $content = $this->get_template_block('form');
        $content = str_replace('%url%', $kernel->pub_page_current_get().'.html', $content);
        $content = str_replace('%date_alone_name%', 'date', $content);
        $content = str_replace('%date_start_name%', 'start', $content);
        $content = str_replace('%date_stop_name%', 'stop', $content);
        return $content;
    }
    */


    private function generate_captcha_block($content)
    {
        global $kernel;
        $iscaptcha = $kernel->pub_modul_properties_get('showcaptcha');
        if ($iscaptcha['value']=='true')
            $content = str_replace('%form_captcha%', $this->get_template_block('captcha'), $content);
        else
            $content = str_replace('%form_captcha%', '', $content);
        return $content;
    }


    /*********************************************************
     * Публичное метод для отображения статистики по отзывам
     *
     * @param string $template Путь к файлу с шаблонами
     * @param string $httpparams
     * @return string
     */
    public function pub_show_reviews_stat($template,$httpparams)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($template));

        $conds = $this->get_reviews_conditions($httpparams);
        $total = $this->get_total_reviews($conds);
        if ($total==0)
            return $this->get_template_block('no_data');

        $content = $this->get_template_block('content');

        $conds[]='rate IS NOT NULL';
        $query = 'SELECT AVG(rate) AS avg FROM `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_reviews` WHERE '.implode(' AND ', $conds).'';
        $res=$kernel->runSQL($query);
        $avg_rate_float = mysql_result($res, 0, 'avg');
        mysql_free_result($res);
        $avg_rate_int = round($avg_rate_float);
        $avg_rate_float = round($avg_rate_float,2);
        $content = str_replace('%total%',$total,$content);
        if ($avg_rate_int>0)
        {
            $content = str_replace('%avg_rate_float%',str_replace('%avg_rate_float%',$avg_rate_float,$this->get_template_block('avg_rate_float')),$content);
            $content = str_replace('%avg_rate_int%',$this->get_template_block('avg_rate_int_'.$avg_rate_int),$content);
        }
        $content = $this->clear_left_labels($content);
        return $content;
    }

    /*********************************************************
     * Публичное метод для отображения отзывов
     *
     * @param string $template Путь к файлу с шаблонами
     * @param integer $limit Количество выводимых комментариев
     * @param string $type Тип отбора комментариев для вывода
     * @param string $httpparams
     * @return string
     */
     public function pub_show_reviews($template, $limit, $type, $httpparams)
     {
         global $kernel;
         $this->set_templates($kernel->pub_template_parse($template));
         $offset = $this->get_offset();
         $conditions = $this->get_reviews_conditions($httpparams);
         $items = $this->get_reviews($conditions, $limit, $offset, $type);
         $total = $this->get_total_reviews($conditions);
         if ($total == 0)
             $reviews_rows = $this->get_template_block('no_data');
         else
         {
             $lines = '';
             foreach ($items as $item)
             {
                 //особый блок, если написал админ
                 if ($item['name'] == self::ADMNAME)
                     $line = $this->get_template_block('review_row_by_admin');
                 else
                     $line = $this->get_template_block('review_row');
                 if ($item['pros'])
                     $line = str_replace('%pros%',str_replace('%pros%',$item['pros'],$this->get_template_block('review_row_pros')), $line);
                 if ($item['cons'])
                     $line = str_replace('%cons%',str_replace('%cons%',$item['cons'],$this->get_template_block('review_row_cons')), $line);
                 if ($item['rate'])
                     $line = str_replace('%rate%',$this->get_template_block('review_row_rate_'.$item['rate']), $line);
                 $line = str_replace('%id%', $item['id'], $line);
                 $line = str_replace('%name%', $item['name'], $line);
                 $line = str_replace('%num%', $item['num'], $line);
                 $line = str_replace('%when%', $item['when'], $line);
                 $line = str_replace('%comment%', $item['comment'], $line);
                 $lines .= $line;
             }
             $reviews_rows = $this->get_template_block('reviews_header') . $lines . $this->get_template_block('reviews_footer');
         }
         $content = $this->get_template_block('content');

         $content = str_replace('%reviews_rows%', $reviews_rows, $content);
         $content = str_replace('%form%', $this->process_reviews_form($httpparams), $content);
         $purl = $this->create_page_url($httpparams);
         $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $purl, 0), $content);
         $content = str_replace('%current_page%', $purl, $content);
         $content = str_replace('%total%', $total, $content);

         $content = $this->clear_left_labels($content);
         return $content;
     }



    private function create_page_url($httpparams)
    {
        global $kernel;
        $purl = $kernel->pub_page_current_get() . '.html?';
        if (strlen(trim($httpparams)) > 0)
        {
            $params = explode(',', $httpparams);
            foreach ($params as $param)
            {
                $purl .= $param.'='.urlencode($kernel->pub_httpget_get($param, false)).'&';
            }
        }
        $purl .= $this->offset_name . '=';
        return $purl;
    }


    /*********************************************************
     * Публичное действие для отображения комментариев
     *
     * @param string $template Путь к файлу с шаблонами
     * @param integer $limit Количество выводимых комментариев
     * @param string $type Тип отбора комментариев для вывода
     * @param string $httpparams
     * @param string $no_parent
     * @return string
     */
     public function pub_show_comments($template, $limit, $type, $httpparams, $no_parent)
     {
         global $kernel;
         $this->set_templates($kernel->pub_template_parse($template));
         if (strlen(trim($httpparams)) > 0 && $no_parent == 'no')
         {
             foreach (explode(",",trim($httpparams)) as $httpparam)
             {
                 if (empty($httpparam))
                     continue;
                 $get_param=$kernel->pub_httpget_get($httpparam);
                 if (empty($get_param))
                     return '';//нет хотя бы одного параметра - нет контента
             }
         }
         $offset = $this->get_offset();
         $conditions = $this->get_comments_conditions($httpparams);
         $items = $this->get_comments($conditions, $limit, $offset, $type);
         $total = $this->get_total_comments($conditions);
         if ($total == 0)
             $comments_rows = $this->get_template_block('no_data');
         else
         {
             $lines = '';
             foreach ($items as $item)
             {
                 //особый блок, если написал админ
                 if ($item['author'] == self::ADMNAME)
                     $line = $this->get_template_block('comment_row_by_admin');
                 else
                     $line = $this->get_template_block('comment_row');
                 $line = str_replace('%num%', $item['num'], $line);
                 $line = str_replace('%date%', $item['date'], $line);
                 $line = str_replace('%time%', $item['time'], $line);
                 $line = str_replace('%txt%', $item['txt'], $line);
                 $line = str_replace('%author%', $item['author'], $line);
                 $lines .= $line;
             }
             $comments_rows = $this->get_template_block('comments_header') . $lines . $this->get_template_block('comments_footer');

         }
         $content = $this->get_template_block('content');
         $content = str_replace('%total%', $total, $content);
         $content = str_replace('%comments_rows%', $comments_rows, $content);
         $content = str_replace('%form%', $this->process_comments_form($httpparams), $content);
         $purl = $this->create_page_url($httpparams);
         $content = str_replace('%pages%', $this->build_pages_nav($total, $offset, $limit, $purl, 0), $content);
         return $content;
     }

	private function process_comments_form($httpparams)
	{
	    global $kernel;
		$content = '';
        if ($kernel->pub_httppost_get('view')=='form_processing')
        {//Обработаем данные, введенные пользователем

            $cmnt_name = trim($kernel->pub_httppost_get('cmnt_name', false));
            $cmnt_txt = trim($kernel->pub_httppost_get('cmnt_txt', false));
            $aval=1;
            $addok=true;

            $kernel->pub_session_set('cmnt', array('user_name'=>$cmnt_name,'user_txt'=>$cmnt_txt));

            //простейшая проверка на заполнение всех полей формы
            if ($cmnt_name =='' || $cmnt_txt =='')
            {
                $addok=false;
                $content .=$this->get_template_block('msg_fields_not_filled');
            }

            //защита от добавления коммента от имени админа сайта
            if ($cmnt_name == comments::ADMNAME)
            {
                $addok=false;
                $content .=$this->get_template_block('msg_no_admin_comments');
                $kernel->pub_session_unset('cmnt', $kernel->pub_module_id_get());
            }

            $iscaptcha = $kernel->pub_modul_properties_get('showcaptcha');
            $ispremod = $kernel->pub_modul_properties_get('premod');
            if ($ispremod['value']=='true')
                $aval=0;
            if ($iscaptcha['value']=='true')
            {
                $cmnt_code = $kernel->pub_httppost_get('cmnt_captcha');
                require_once(dirname(__FILE__).'/php-captcha.inc.php');
                if (!PhpCaptcha::Validate($cmnt_code))
                {
                    $content .=$this->get_template_block('msg_badcaptcha');
                    $addok=false;
                }
            }
            $content .=$this->get_template_block('form');
            //$val = $kernel->pub_session_get('cmnt');
            //$user_name = $val['user_name'];
            //$user_txt = $val['user_txt'];
            $content = str_replace('%user_txt%', htmlspecialchars($cmnt_txt), $content);
            $content = str_replace('%user_name%', htmlspecialchars($cmnt_name), $content);
            $content=$this->generate_captcha_block($content);
            //$kernel->pub_session_unset('cmnt', $kernel->pub_module_id_get());

            if ($addok)
            {
                $page_sub_id='""';
                $httpLink = "http://".$_SERVER['HTTP_HOST']."/".$kernel->pub_page_current_get().".html?";
                if (strlen(trim($httpparams))>0)
                {
                    $page_sub_id='"';
                    $params = explode(',',$httpparams);
                    foreach ($params as $param)
                    {
                        $httpLink.= $param.'='.urlencode($kernel->pub_httpget_get($param,false))."&";
                        $page_sub_id.=$param.'='.$kernel->pub_httpget_get($param).',';
                    }
                    $page_sub_id.='"';
                }

                $sql = 'INSERT INTO `'.$kernel->pub_prefix_get().'_comments` (page_id,page_sub_id,module_id,txt,author,available,`date`,`time`)'.
                      ' VALUES ("'.$kernel->pub_page_current_get().'",'.$page_sub_id.', "'.$kernel->pub_module_id_get().'","'.mysql_real_escape_string(nl2br(htmlspecialchars($cmnt_txt))).'","'.mysql_real_escape_string(htmlspecialchars($cmnt_name)).'",'.$aval.',CURDATE(),CURTIME());';
                $kernel->runSQL($sql);

                $subj = "Новый комментарий";
                if ($aval!=1)
                    $subj.= " требующий модерации";
                $body = "<html><body><a href='".$httpLink."'>Ссылка</a><br>Имя: ".htmlspecialchars($cmnt_name)."<br>Текст: ".nl2br(htmlspecialchars($cmnt_txt))."</body></html>";
                $this->send_admin_email($subj,$body);
                $redirUrl=$_SERVER["REQUEST_URI"];
                if (strpos($redirUrl,"?")===FALSE)
                    $redirUrl.="?";
                else
                    $redirUrl.="&";
                if ($aval==1)
                    $redirUrl=$redirUrl.$this->publish_success_param."=1";
                else
                    $redirUrl=$redirUrl.$this->publish_2moderate_param."=1";
                $kernel->priv_redirect_301($redirUrl);
            }
        }
        else
        {//form_show
            if (strlen($kernel->pub_httpget_get($this->publish_success_param))>0)
                $content .=$this->get_template_block('msg_processing_success');
            elseif (strlen($kernel->pub_httpget_get($this->publish_2moderate_param))>0)
                $content .=$this->get_template_block('msg_need_admin_approve');
            $content .=$this->get_template_block('form');
            $content = str_replace('%user_txt%', '', $content);
            $content = str_replace('%user_name%', '', $content);
            $content = $this->generate_captcha_block($content);
        }
	    return $content;
	}


    private function process_reviews_form($httpparams)
	{
	    global $kernel;
		$content = '';
        if ($kernel->pub_httppost_get('view')=='form_processing')
        {//Обработаем данные, введенные пользователем

            $name = trim($kernel->pub_httppost_get('r_name', false));
            $comment = trim($kernel->pub_httppost_get('r_comment', false));
            $pros = trim($kernel->pub_httppost_get('r_pros', false));
            $cons = trim($kernel->pub_httppost_get('r_cons', false));
            $rate = intval($kernel->pub_httppost_get('r_rate'));

            $aval=1;
            $addok=true;
            //простейшая проверка на заполнение обязательных полей формы
            if (empty($name)|| empty($comment))
            {
                $addok=false;
                $content .=$this->get_template_block('msg_fields_not_filled');
            }

            //защита от добавления коммента от имени админа сайта
            if ($name == comments::ADMNAME)
            {
                $addok=false;
                $content .=$this->get_template_block('msg_no_admin_comments');
                $kernel->pub_session_unset('cmnt', $kernel->pub_module_id_get());
            }

            $iscaptcha = $kernel->pub_modul_properties_get('showcaptcha');
            $ispremod = $kernel->pub_modul_properties_get('premod');
            if ($ispremod['value']=='true')
                $aval=0;
            if ($iscaptcha['value']=='true')
            {
                $cmnt_code = $kernel->pub_httppost_get('cmnt_captcha');
                require_once(dirname(__FILE__).'/php-captcha.inc.php');
                if (!PhpCaptcha::Validate($cmnt_code))
                {
                    $content .=$this->get_template_block('msg_badcaptcha');
                    $addok=false;
                }
            }
            $content .=$this->get_template_block('form');
            $content = str_replace('%r_name%', htmlspecialchars($name), $content);
            $content = str_replace('%r_comment%', htmlspecialchars($comment), $content);
            $content = str_replace('%r_pros%', htmlspecialchars($pros), $content);
            $content = str_replace('%r_cons%', htmlspecialchars($cons), $content);
            $content = str_replace('%r_rate_'.$rate.'_checked%', 'checked', $content);
            if ($addok)
            {
                if ($rate<1 || $rate>5)
                    $rate=null;
                $rec=array(
                    'name'=>mysql_real_escape_string(htmlspecialchars($name)),
                    'pros'=>mysql_real_escape_string(nl2br(htmlspecialchars($pros))),
                    'cons'=>mysql_real_escape_string(nl2br(htmlspecialchars($cons))),
                    'comment'=>mysql_real_escape_string(nl2br(htmlspecialchars($comment))),
                    'when'=>date("Y-m-d H:i:s"),
                    'rate'=>$rate,
                    'pageid'=>$this->get_pageid_for_reviews($httpparams),
                    'available'=>$aval
                );
                $kernel->db_add_record("_".$kernel->pub_module_id_get()."_reviews",$rec);

                $subj = trim($this->get_template_block('subj2admin'));
                $body = $this->get_template_block('email2admin');
                if ($aval!=0)//если не пост-модерация, то можно добавить и ссылку
                {
                    $httpLink = "http://".$_SERVER['HTTP_HOST']."/".$this->create_page_url($httpparams);
                    $body=str_replace('%link%',$this->get_template_block('email2admin_link'),$body);
                    $body=str_replace('%link%',$httpLink,$body);
                }
                $body = str_replace('%name%', htmlspecialchars($name), $body);
                $body = str_replace('%comment%', nl2br(htmlspecialchars($comment)), $body);
                $body = str_replace('%pros%', nl2br(htmlspecialchars($pros)), $body);
                $body = str_replace('%cons%', nl2br(htmlspecialchars($cons)), $body);
                $body = str_replace('%rate%', $rate, $body);

                $this->send_admin_email($subj,$body);
                $redirUrl=$_SERVER["REQUEST_URI"];
                if (strpos($redirUrl,"?")===FALSE)
                    $redirUrl.="?";
                else
                    $redirUrl.="&";
                if ($aval==1)
                    $redirUrl=$redirUrl.$this->publish_success_param."=1";
                else
                    $redirUrl=$redirUrl.$this->publish_2moderate_param."=1";
                $kernel->priv_redirect_301($redirUrl);
            }
        }
        else
        {//form_show
            if (strlen($kernel->pub_httpget_get($this->publish_success_param))>0)
                $content .=$this->get_template_block('msg_processing_success');
            elseif (strlen($kernel->pub_httpget_get($this->publish_2moderate_param))>0)
                $content .=$this->get_template_block('msg_need_admin_approve');
            $content .=$this->get_template_block('form');
        }

        $content=$this->generate_captcha_block($content);
	    return $content;
	}

    private function send_admin_email($subj,$body)
    {
        global $kernel;
        $admEmailProp = $kernel->pub_modul_properties_get('comments_admin_email');
        if (empty($admEmailProp['value']))
            return;
        $host = $_SERVER['HTTP_HOST'];
        $host = preg_replace("/^www\\./i","", $host);
        $fromname = $host;
        $fromaddr = "noreply@".$host;
        $kernel->pub_mail(array($admEmailProp['value']), array("admin"),$fromaddr, $fromname, $subj,$body);
    }


    /**
     * Возвращает массив набора SQL-условий для комментариев
     * @param $httpparams
     * @return array
     */
    private function get_comments_conditions($httpparams)
    {
        global $kernel;
        $where = array();
        $where[] = '`module_id` = "'.$kernel->pub_module_id_get().'"';
        $where[] = '`available` = 1';
        $where[] = '`page_id` = "'.$kernel->pub_page_current_get().'"';
        if (!empty($httpparams))
        {
            $params = explode(",",$httpparams);
            $wstr='';
            foreach ($params as $param)
            {
                $wstr=$param.'='.$kernel->pub_httpget_get($param).',';
            }
            $where[] = '`page_sub_id` = "'.$wstr.'"';
        }
        return $where;
    }

    private function get_pageid_for_reviews($httpparams)
    {
        global $kernel;
        $pageid = $kernel->pub_page_current_get();
        $params = explode(",",$httpparams);
        foreach ($params as $param)
        {
            if (strlen($param)==0)
                continue;
            $pageid.=','.$param.'='.$kernel->pub_httpget_get($param);
        }
        return $pageid;
    }

    /**
     * Возвращает массив набора SQL-условий для отзывов
     * @param $httpparams
     * @return array
     */
    private function get_reviews_conditions($httpparams)
    {
        $where = array();
        $where[] = '`available` = 1';
        $where[] = '`pageid` = "'.$this->get_pageid_for_reviews($httpparams).'"';
        return $where;
    }

    /**
     * @param array $where
     * @param int $limit
     * @param int $offset
     * @param string $type
     * @return array
     */
    private function get_comments($where, $limit, $offset, $type)
    {
        global $kernel;
        $items = array();
        $order = array();
        if ($type=='new_at_bottom')
        {
            $order[] = '`comments`.`date` ASC';
            $order[] = '`comments`.`time` ASC';
        }
        else
        {
            $order[] = '`comments`.`date` DESC';
            $order[] = '`comments`.`time` DESC';
        }

        $query = 'SELECT `id` , DATE_FORMAT(`date`, "%d-%m-%Y") AS `date` , `time` , `txt` , `author`
                  FROM `'.$kernel->pub_prefix_get().'_comments` AS `comments`
                  WHERE '.implode(' AND ', $where).' ORDER BY '.implode(', ', $order).' LIMIT '.$limit.' OFFSET '.$offset;

        $result = $kernel->runSQL($query);

	    if ($type == "new_at_bottom")
            $num = 1;
        else//if ($type == "new_at_top" or $type == "default")
            $num = $this->get_total_comments($where);
	    while ($row = mysql_fetch_assoc($result))
        {
            if ($type == "new_at_bottom")
                $nums = $offset + $num++;
            else//if ($type == "new_at_top" or $type == "default")
                $nums = ($num--) - $offset;
            $row['num']=$nums;
            $items[] = $row;
        }
        mysql_free_result($result);
	    return $items;
	}

    /**
     * @param array $where
     * @param int $limit
     * @param int $offset
     * @param string $type
     * @return array
     */
    private function get_reviews($where, $limit, $offset, $type)
    {
        global $kernel;
        $items = array();
        if ($type=='new_at_bottom')
            $orderby = '`when` ASC';
        else
            $orderby = '`when` DESC';
        $query = 'SELECT `id` , DATE_FORMAT(`when`, "%d-%m-%Y %H:%i") AS `when`, `name`,`comment`,`rate`,`pros`,`cons`
                  FROM `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_reviews`
                  WHERE '.implode(' AND ', $where).' ORDER BY '.$orderby.' LIMIT '.$limit.' OFFSET '.$offset;

        $result = $kernel->runSQL($query);

	    if ($type == "new_at_bottom")
            $num = 1;
        else//if ($type == "new_at_top" or $type == "default")
            $num = $this->get_total_reviews($where);
	    while ($row = mysql_fetch_assoc($result))
        {
            if ($type == "new_at_bottom")
                $nums = $offset + $num++;
            else//if ($type == "new_at_top" or $type == "default")
                $nums = ($num--) - $offset;
            $row['num']=$nums;
            $items[] = $row;
        }
        mysql_free_result($result);
	    return $items;
	}


    private function get_offset()
    {
        global $kernel;
    	$offset = intval($kernel->pub_httpget_get($this->offset_name));
    	return $offset;
    }

    private function get_total_comments($where)
    {
        global $kernel;
        $query = 'SELECT COUNT(*) AS `total` FROM `'.$kernel->pub_prefix_get().'_comments` WHERE '.implode(' AND ', $where).'';
        $res=$kernel->runSQL($query);
        $total = mysql_result($res, 0, 'total');
        mysql_free_result($res);
        return $total;
    }

    private function get_total_reviews($where)
    {
        global $kernel;
        $query = 'SELECT COUNT(*) AS `total` FROM `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_reviews` WHERE '.implode(' AND ', $where).'';
        $res=$kernel->runSQL($query);
        $total = mysql_result($res, 0, 'total');
        mysql_free_result($res);
        return $total;
    }

    /**
     * Функция для построения меню для административного интерфейса
     *
     * @param pub_interface $menu Обьект класса для управления помтроеним меню
     * @return boolean
     */
	public function interface_get_menu($menu)
	{
        $menu->set_menu_block('[#comments_menu_label#]');
        $menu->set_menu("[#comments_menu_show_list#]","show_list", array('flush' => 1));
        $menu->set_menu("[#comments_menu_add_new#]","show_add", array('flush' => 1));
        $menu->set_menu("[#comments_menu_notmoderated#]","select_notmoderated", array('flush' => 1));

        $menu->set_menu_block('[#comments_reviews_menu_block#]');
        $menu->set_menu("[#comments_menu_show_list#]","show_reviews_list", array('flush' => 1));
        $menu->set_menu("[#comments_menu_add_new#]","show_review_add", array('flush' => 1));
        $menu->set_menu("[#comments_menu_notmoderated#]","show_notmoderated_reviews", array('flush' => 1));
        //$menu->set_menu_block('[#comments_menu_label1#]');
        //$menu->set_menu_plain($this->priv_show_date_picker());
        $menu->set_menu_default('show_list');
	    return true;
	}

    /*
	function priv_show_date_picker()
	{
	    global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->templates_admin_prefix.'date_picker.html'));
        $content = $this->get_template_block('date_picker');
        return $content;
	}
    */

	/**
	 * Функция для отображаения административного интерфейса
	 *
	 * @return string
	 */
    public function start_admin()
    {
        global $kernel;
        switch ($kernel->pub_section_leftmenu_get())
        {
            case 'show_reviews_list':
                return $this->show_reviews_list();

            case 'show_notmoderated_reviews':
                return $this->show_reviews_list(true);

            case 'show_review_form':
                return $this->show_review_form($kernel->pub_httpget_get('id'));

            case 'show_review_add':
                return $this->show_review_form(0);

            case 'review_save':
                $values = $kernel->pub_httppost_get('values');
                $this->save_review($values);
                $kernel->pub_redirect_refresh_reload('show_reviews_list');
                break;

            case 'delete_review':
                $this->delete_review($kernel->pub_httpget_get('id'));
                $kernel->pub_redirect_refresh('show_reviews_list');
                break;
            case 'reviews_actions':
                $this->reviews_do_action($kernel->pub_httppost_get('action'), $kernel->pub_httppost_get('items'));
                $kernel->pub_redirect_refresh_reload('show_reviews_list');
                break;


            default:
        	case 'show_list':
                return $this->show_comments_list();

            case 'select_notmoderated':
               return $this->show_comments_list(true);

            case 'show_edit':
                return $this->show_comment_form($kernel->pub_httpget_get('id'));

            case 'show_add':
                return $this->show_comment_form(0);

            case 'comment_save':
                $values = $kernel->pub_httppost_get('values');
                $values['description_full'] = $kernel->pub_httppost_get('content_html');
                $this->save_comment($values);
                $kernel->pub_redirect_refresh_reload('show_list');
                break;

            case 'delete_comment':
                $this->delete_comment($kernel->pub_httpget_get('id'));
                $kernel->pub_redirect_refresh('show_list');
                break;


            case 'comments_actions':
                $this->comments_do_action($kernel->pub_httppost_get('action'), $kernel->pub_httppost_get('items'));
                $kernel->pub_redirect_refresh_reload('show_list');
                break;

        }

        return (isset($content)?$content:null);
    }

    private function comments_do_action($action, $items)
    {
        global $kernel;
        if (empty($items))
            return false;
        switch ($action)
        {
        	case 'available_on':
        	    $query = 'UPDATE `'.$kernel->pub_prefix_get().'_comments` SET `available` = "1" WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
        	case 'available_off':
        	    $query = 'UPDATE `'.$kernel->pub_prefix_get().'_comments` SET `available` = "0" WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
        	case 'delete':
        	    $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_comments` WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
            default:
        	    $query = 'UPDATE `'.$kernel->pub_prefix_get().'_comments` SET `module_id` = "'.$action.'" WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
        }
        return mysql_affected_rows();
    }

    private function reviews_do_action($action, $items)
    {
        global $kernel;
        if (empty($items))
            return false;
        $table = '`'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_reviews`';
        switch ($action)
        {
            default:
        	case 'available_on':
        	    $query = 'UPDATE '.$table.' SET `available` = "1" WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
        	case 'available_off':
        	    $query = 'UPDATE '.$table.' SET `available` = "0" WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;
        	case 'delete':
        	    $query = 'DELETE FROM '.$table.' WHERE `id` IN ('.implode(', ', $items).')';
        	    $kernel->runSQL($query);
        		break;

        }
        return mysql_affected_rows();
    }

    private function delete_comment($item_id)
    {
        global $kernel;
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_comments` WHERE `id` = '.$item_id.'';
        $kernel->runSQL($query);
    }

    private function delete_review($item_id)
    {
        global $kernel;
        $query = 'DELETE FROM `'.$kernel->pub_prefix_get().'_'.$kernel->pub_module_id_get().'_reviews` WHERE `id` = '.$item_id.'';
        $kernel->runSQL($query);
    }

    private function save_comment($item_data)
    {
        global $kernel;
        list($day, $month, $year) = explode('.', $item_data['date']);
        if (isset($item_data['available']))
            $item_data['available']=1;
        else
            $item_data['available']=0;
        $item_data['date']=$year.'-'.$month.'-'.$day;

        $table='_comments';
        $item_data['id']=intval($item_data['id']);
        if ($item_data['id']==0)
            $item_data['id']=null;
        //оставим только реальные поля, строкам - escape
        $fields=$this->get_table_fields($table);
        foreach ($item_data as $k=>&$v)
        {
            if (!in_array($k,$fields))
                unset($item_data[$k]);
            else
            {
                if (is_string($v))
                   $v=mysql_real_escape_string($v);
            }

        }
        $item_data['module_id']=$kernel->pub_module_id_get();
        $kernel->db_add_record($table,$item_data,"REPLACE");
    }

    private function save_review($item_data)
    {
        global $kernel;
        list($day, $month, $year) = explode('.', $item_data['date']);


        if (isset($item_data['available']))
            $item_data['available']=1;
        else
            $item_data['available']=0;
        $item_data['when']=$year.'-'.$month.'-'.$day.' '.$item_data['time'];

        $item_data['id']=intval($item_data['id']);
        if ($item_data['id']==0)
            $item_data['id']=null;
        $table='_'.$kernel->pub_module_id_get().'_reviews';

        //оставим только реальные поля, строкам - escape
        $fields=$this->get_table_fields($table);
        foreach ($item_data as $k=>&$v)
        {
            if (!in_array($k,$fields))
                unset($item_data[$k]);
            else
            {
                if (is_string($v))
                    $v=mysql_real_escape_string($v);
            }

        }
        $kernel->db_add_record($table,$item_data,"REPLACE");
    }


    /**
     * Форма редактирования или добавления комментария в админке
     * @param integer $item_id
     * @return string
     */
    private function show_comment_form($item_id = 0)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->templates_admin_prefix.'item_form.html'));
        $content = $this->get_template_block('form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('comment_save'), $content);
        $content = str_replace('%id%', ((is_numeric($item_id)) ? ($item_id) : ('NULL')), $content);
        if (!$item_id)
        {
            $content = str_replace('%time%', date('H:i:s'), $content);
            $content = str_replace('%date%', date('d.m.Y'), $content);
            $select_page = $this->get_select_page4comments();
            $pages = $this->get_all_pages();
            $sub_select_page = '';
            foreach ($pages as $row)
            {
                $sub_select_page .= $this->comments_select_page_sub_id($row['page_id']);
            }
            $sub_select_page = substr(trim($sub_select_page), 0, -1);

            $content = str_replace('%rows%', $this->get_template_block('select'), $content);
            $content = str_replace('%select_page%', $select_page, $content);
            $content = str_replace('%sub_select_page%', $sub_select_page, $content);
        }
        else
        {
            $content = str_replace('%rows%', $this->get_template_block('page_info'), $content);
            $info = '<b style="padding-left:30px">' . '%page_id%' . '  ' . '%page_sub_id%' . '<input type="hidden" name="values[page_id]" value=' . '%page_id%' . '><input type="hidden" name="values[page_sub_id]" value=' . '%page_sub_id%' . '>';
            $content = str_replace('%page_info%', $info, $content);
        }
        $content = str_replace($this->get_comment_data_search(), $this->get_comment_data_replace($item_id), $content);
        return $content;
    }

    /**
     * Форма редактирования или добавления отзыва в админке
     * @param integer $item_id
     * @return string
     */
    private function show_review_form($item_id = 0)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->templates_admin_prefix.'review_form.html'));

        $content = $this->get_template_block('form');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('review_save'), $content);

        if (!$item_id)
        {
            $content = str_replace('%id%', 'NULL', $content);
            $content = str_replace('%time%', date('H:i:s'), $content);
            $content = str_replace('%date%', date('d-m-Y'), $content);
            $content=str_replace('%available%','checked',$content);
        }
        else
        {
            $data = $this->get_review_data(intval($item_id));
            $content = str_replace('%id%', $item_id, $content);
            $content = str_replace('%time%', $data['time'], $content);
            $content = str_replace('%date%', $data['date'], $content);
            $content = str_replace('%comment%', htmlspecialchars($data['comment']), $content);
            $content = str_replace('%pros%', htmlspecialchars($data['pros']), $content);
            $content = str_replace('%cons%', htmlspecialchars($data['cons']), $content);
            $content = str_replace('%name%', htmlspecialchars($data['name']), $content);
            $content = str_replace('%pageid%', htmlspecialchars($data['pageid']), $content);
            $content = str_replace('%r_rate_'.$data['rate'].'_checked%', 'checked', $content);  //%r_rate_1_checked%
            if ($data['available'])
                $content=str_replace('%available%','checked',$content);
            //$content = str_replace('%rate%', $data['rate'], $content);
        }

        $content=$this->clear_left_labels($content);
        return $content;
    }

	//функция по формированию 1-ого списка со страницами добавления комментов (page_id)
	private function get_select_page4comments()
    {
        $pages = $this->get_all_pages();
		$select = '';
		foreach ($pages as $row)
        {
		   $select .= '<option value="'.$row['page_id'].'">'.$row['page_id'].'</option>'."\n";
        }
	    return $select;
	}

	private function get_all_pages()
    {
	    global $kernel;
        return $kernel->db_get_list_simple('_comments',"module_id='".$kernel->pub_module_id_get()."'","DISTINCT `page_id`");
	}

	//функция формирования куска JS-кода для вывода 2-ого связанного списка page_sub_id в шаблоне item_form.html
	private function comments_select_page_sub_id($page_id)
    {
	    global $kernel;
		$query = 'SELECT DISTINCT `page_sub_id` FROM `'.$kernel->pub_prefix_get().'_comments` WHERE module_id="'.$kernel->pub_module_id_get().'" AND page_id="'.$page_id.'"';
	    $res = $kernel->runSQL($query);
		$sub_select = '';
		while($row = mysql_fetch_assoc($res))
        {
		    $sub_select .= '"'.$row['page_sub_id'].'":"'.(($row['page_sub_id'] == '')?('без параметра(ов)'):(substr($row['page_sub_id'],0,-1))).'",';
        }
		$sub_select = '"'.$page_id.'":{'.substr(trim($sub_select),0,-1).'},';
		return $sub_select;
	}

    function get_comment_data_replace($item_id)
    {
        $item_data = $this->get_comment_data($item_id);
        if (empty($item_data))
        {
            return array(
                '',
                '',
                'checked',
                '',
                self::ADMNAME,//добавлено aim
                '',
                ''
            );
        }
        else
        {
        	return array(
        	   $item_data['date'],
        	   $item_data['time'],
        	   ($item_data['available'] == 1)?('checked'):(''),
        	   $item_data['txt'],
        	   $item_data['author'],
               $item_data['page_id'],
               $item_data['page_sub_id']
			 );
        }
    }

    private function get_comment_data_search()
    {
    	$array = array(
    	   '%date%',
    	   '%time%',
    	   '%available%',
    	   '%txt%',
    	   '%author%',
           '%page_id%',
           '%page_sub_id%'
    	);
    	return $array;
    }

    /**
     * Возвращает данные по указанному ID
     *
     * @param integer $item_id
     * @return array
     */
    private function get_comment_data($item_id)
    {
        global $kernel;
        if (!is_numeric($item_id))
            return array();
        return $kernel->db_get_record_simple("_comments","id=".$item_id,"*, DATE_FORMAT(`date`,'%d.%m.%Y') AS date");
    }

    /**
     * Возвращает данные по указанному ID
     *
     * @param integer $item_id
     * @return array
     */
    private function get_review_data($item_id)
    {
        global $kernel;
        return $kernel->db_get_record_simple("_".$kernel->pub_module_id_get()."_reviews","id=".$item_id,"*, DATE_FORMAT(`when`,'%d.%m.%Y') AS date, DATE_FORMAT(`when`,'%H:%i:%s') AS time");
    }


    private function get_limit_admin()
    {
    	global $kernel;
    	$property = $kernel->pub_modul_properties_get('comments_per_page_admin');
    	if ($property['isset'] && is_numeric($property['value']))
    	    return $property['value'];
    	else
    	    return 10;
    }


    /**
     * Возвращает текущий сдвиг
     *
     * @return integer
     */
    private function get_offset_admin()
    {
        global $kernel;
        $offset = $kernel->pub_httpget_get('offset');
        if (trim($offset) == '')
            $offset = $kernel->pub_session_get('offset');
    	if (!is_numeric($offset))
    	    $offset = 0;
    	$kernel->pub_session_set('offset', $offset);
    	return $offset;
    }

    private function get_direction_admin()
    {
    	global $kernel;
    	$direction = $kernel->pub_httpget_get('direction');
    	if (empty($direction))
            $direction = $kernel->pub_session_get('direction');
    	if (!in_array(strtoupper($direction), array('ASC', 'DESC')))
    	    $direction = 'ASC';
    	$kernel->pub_session_set('direction', $direction);
    	return $direction;
    }

    private function get_table_fields($table)
    {
        global $kernel;
        $query = 'SHOW COLUMNS FROM `'.$kernel->pub_prefix_get().''.$table.'`';
        $result = $kernel->runSQL($query);
        $fields = array();
        while ($row = mysql_fetch_assoc($result))
        {
            $fields[] = $row['Field'];
        }
        mysql_free_result($result);
        return $fields;
    }

    private function get_sort_field($table='_comments',$default='date')
    {
        global $kernel;
        $fields = $this->get_table_fields($table);
        $field = $kernel->pub_httpget_get('field');
        if (empty($field))
        	$field = $kernel->pub_session_get('field');
        if (!in_array($field, $fields))
            $field = $default;
        $kernel->pub_session_set('field', $field);
        return $field;
    }

    private function get_start_date_admin()
    {
        global $kernel;
        $start = $kernel->pub_httpget_get('start');
        if (empty($start))
        	$start = $kernel->pub_session_get('start');
        $kernel->pub_session_set('start', $start);
        return $start;
    }

    private function get_end_date_admin()
    {
        global $kernel;
        $stop = $kernel->pub_httpget_get('stop');
        if (empty($stop))
        	$stop = $kernel->pub_session_get('stop');
        $kernel->pub_session_set('stop', $stop);
        return $stop;
    }

    private function get_date_param_admin()
    {
        global $kernel;
    	$date = $kernel->pub_httpget_get('date');
    	if (empty($date))
    		$date = $kernel->pub_session_get('date');
    	$kernel->pub_session_set('date', $date);
    	return $date;
    }




    /**
     * Отображает список отзывов в админке
     * @param boolean $only_not_moderated только немодерированные?
     * @return string
     */
    private function show_reviews_list($only_not_moderated = false)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->templates_admin_prefix.'reviews_list.html'));

        $table='_'.$kernel->pub_module_id_get().'_reviews';
        $limit = $this->get_limit_admin();
        $offset = $this->get_offset_admin();
        $field = $this->get_sort_field($table,'when');
        $direction = $this->get_direction_admin();
        $start_date = $this->get_start_date_admin();
        $end_date = $this->get_end_date_admin();
        $date = $this->get_date_param_admin();

        $query = 'SELECT *, DATE_FORMAT(`when`, "%d-%m-%Y %H:%i") AS `date_rus` FROM `' . $kernel->pub_prefix_get().$table.'` WHERE ';
        if ($start_date && $end_date)
        {
            $cond=  '`when`>="' . $start_date . ' 00:00:00" AND `when`<="' . $end_date . ' 23:59:59" ';
            $limits = 'LIMIT '.$limit . ' OFFSET ' . $offset;
        }
        elseif ($date)
        {
            $cond= '`when` >= "'.$date.' 00:00:00" AND `when` <="'.$date.' 23:59:59" ';
            $limits='';
        }
        else if ($only_not_moderated)
        {
            $cond= '`available`=0 ';
            $limits = 'LIMIT '.$limit . ' OFFSET ' . $offset;
        }
        else
        {
            $cond='true';
            $limits = 'LIMIT '.$limit . ' OFFSET ' . $offset;
        }
        $query.=$cond.' ORDER BY `'.$field.'` '.$direction.' '.$limits;
        $result = $kernel->runSQL($query);

        if ((mysql_num_rows($result) == 0))
            return $this->get_template_block('no_data');

        $lines = array();
        $first_element_number = $offset + 1;
        while ($row = mysql_fetch_assoc($result))
        {
            $line = $this->get_template_block('table_body');
            $line = str_replace('%number%', $first_element_number++, $line);
            $line = str_replace('%page_id%', $row['pageid'], $line);
            $line = str_replace('%id%', $row['id'], $line);
            $line = str_replace('%date%', $row['date_rus'], $line);
            $line = str_replace('%rate%', $row['rate'], $line);
            $line = str_replace('%author%', (($row['name'] == comments::ADMNAME) ? ('<span style="color:blue">' . $row['name'] . '</span>') : ($row['name'])), $line);
            $line = str_replace('%available%', (($row['available']) ? ($this->get_template_block('on')) : ($this->get_template_block('off'))), $line);
            $line = str_replace('%txt%', $row['comment'].'<br>достоинства:<br>'.$row['pros'].'<br>недостатки:<br>'.$row['cons'], $line);
            $line = str_replace('%action_edit%', 'show_review_form', $line);
            $line = str_replace('%action_remove%', 'delete_review', $line);
            $lines[] = $line;
        }
        mysql_free_result($result);

        $header = $this->get_template_block('table_header');
        $header = str_replace('%img_sort_' . $field . '%', (($direction == 'ASC') ? ($this->get_template_block('img_sort_asc')) : ($this->get_template_block('img_sort_desc'))), $header);
        $header = preg_replace('/\%img_sort_\w+%/', '', $header);

        $content = $header . implode("\n", $lines) . $this->get_template_block('table_footer');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('reviews_actions'), $content);


        $actions = array(
            'available_on' => '[#comments_show_list_action_available_on#]',
            'available_off' => '[#comments_show_list_action_available_off#]',
            'delete' => '[#comments_show_list_action_delete#]'
        );
        $content = str_replace('%actions%', $this->generate_html_select('action', $actions), $content);

        $total = $this->get_total_reviews(array($cond));

        $purl = 'show_reviews_list&field='.$field.'&direction='.$direction.'&'.$this->offset_name.'=';
        $content = str_replace('%pages%', $this->build_pages_nav($total,$offset,$limit,$purl,0), $content);

        $sort_headers = $this->get_reviews_sort_headers($field, $direction, $date, $start_date, $end_date);
        $content = str_replace(array_keys($sort_headers), $sort_headers, $content);
        $content = str_replace('%range_form_action%', $kernel->pub_redirect_for_form('show_reviews_list'), $content);
        $content = str_replace('%total%', $total, $content);   //change
        return $content;
    }

    /**
     * Отображает список комментариев в админке
     * @param boolean $only_not_moderated только немодерированные?
     * @return string
     */
    private function show_comments_list($only_not_moderated = false)
    {
        global $kernel;
        $this->set_templates($kernel->pub_template_parse($this->templates_admin_prefix.'show_list.html'));

        $limit = $this->get_limit_admin();
        $offset = $this->get_offset_admin();
        $field = $this->get_sort_field();
        $direction = $this->get_direction_admin();
        $start_date = $this->get_start_date_admin();
        $end_date = $this->get_end_date_admin();
        $date = $this->get_date_param_admin();

        $query = 'SELECT *, DATE_FORMAT(`date`, "%d-%m-%Y") AS `date_rus` FROM `' . $kernel->pub_prefix_get() . '_comments`
                  WHERE ';
        $where=array('`module_id` = "'.$kernel->pub_module_id_get().'"');
        if (!is_null($start_date) && !is_null($end_date))
        {
            $where[]= ' `date` BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
            $limits='LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        elseif ($date)
        {
            $where[]= ' `date` = "' . $date . '"';
            $limits='';
        }
        else if ($only_not_moderated)
        {
            $where[]='`available`=0 ';
            $limits= '  LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        else
        {
            $limits= '  LIMIT ' . $limit . ' OFFSET ' . $offset;
        }
        $result = $kernel->runSQL($query.implode(' AND ',$where).' ORDER BY `'.$field.'` '.$direction.' '.$limits);
        if ((mysql_num_rows($result) == 0))
        {
            return $this->get_template_block('no_data');
        }

        $lines = array();
        $first_element_number = $offset + 1;
        while ($row = mysql_fetch_assoc($result))
        {
            $line = $this->get_template_block('table_body');
            $line = str_replace('%number%', $first_element_number++, $line);
            $line = str_replace('%page_id%', $row['page_id'] . '&nbsp;' . substr($row['page_sub_id'], 0, -1), $line);
            $line = str_replace('%id%', $row['id'], $line);
            $line = str_replace('%date%', $row['date_rus'], $line);
            $line = str_replace('%time%', $row['time'], $line);
            $line = str_replace('%author%', (($row['author'] == comments::ADMNAME) ? ('<span style="color:blue">' . $row['author'] . '</span>') : ($row['author'])), $line); //добавлено aim
            $line = str_replace('%available%', (($row['available']) ? ($this->get_template_block('on')) : ($this->get_template_block('off'))), $line);
            $line = str_replace('%txt%', $row['txt'], $line);
            $line = str_replace('%action_edit%', 'show_edit', $line);
            $line = str_replace('%action_remove%', 'delete_comment', $line);
            $lines[] = $line;
        }
        mysql_free_result($result);

        $header = $this->get_template_block('table_header');
        $header = str_replace('%img_sort_' . $field . '%', (($direction == 'ASC') ? ($this->get_template_block('img_sort_asc')) : ($this->get_template_block('img_sort_desc'))), $header);
        $header = preg_replace('/\%img_sort_\w+%/', '', $header);

        $content = $header . implode("\n", $lines) . $this->get_template_block('table_footer');
        $content = str_replace('%form_action%', $kernel->pub_redirect_for_form('comments_actions'), $content);

        $modules = $kernel->pub_modules_get('comments');
        $array = array();
        foreach ($modules as $module_id => $properties)
        {
            if ($module_id != $kernel->pub_module_id_get())
            {
                $array[$module_id] = $properties['caption'];
            }
        }
        if (count($modules) > 1)
        {
            $actions = array(
                '[#comments_actions_simple#]' => array(
                    'available_on' => '[#comments_show_list_action_available_on#]',
                    'available_off' => '[#comments_show_list_action_available_off#]',
                    'delete' => '[#comments_show_list_action_delete#]'
//                    'move' => '[#comments_show_list_action_move#]'
                ),
                '[#comments_actions_advanced#]' => $array
            );
            $content = str_replace('%actions%', $this->generate_html_select('action', $actions, array(), true), $content);
        }
        else
        {
            $actions = array(
                'available_on' => '[#comments_show_list_action_available_on#]',
                'available_off' => '[#comments_show_list_action_available_off#]',
                'delete' => '[#comments_show_list_action_delete#]'
            );
            $content = str_replace('%actions%', $this->generate_html_select('action', $actions), $content);
        }

        /*
        $tQuery='SELECT COUNT(*) AS totalCount FROM `'.$kernel->pub_prefix_get().'_comments` WHERE `module_id` = "'.$kernel->pub_module_id_get().'" ';
        if (!empty($date))
            $tQuery .= 'AND `date` = "'.$date.'"';
        elseif (!empty($start_date) && !empty($end_date))
            $tQuery .= 'AND `date` BETWEEN "'.$start_date.'" AND "'.$end_date.'"';
        $total = mysql_result($kernel->runSQL($tQuery), 0, 'totalCount');
        */
        $total = $this->get_total_comments($where);

        $purl = 'show_list&field='.$field.'&direction='.$direction.'&'.$this->offset_name;
        $content = str_replace('%pages%', $this->build_pages_nav($total,$offset,$limit,$purl,0), $content);

        $sort_headers = $this->get_comments_sort_headers($field, $direction, $kernel->pub_httpget_get('date'), $start_date, $end_date);
        $content = str_replace(array_keys($sort_headers), $sort_headers, $content);
        $content = str_replace('%range_form_action%', $kernel->pub_redirect_for_form('show_list'), $content);
        $content = str_replace('%total%', $total, $content);
        return $content;
    }

    private function get_comments_sort_headers($field, $direction, $date = null, $start = null, $stop = null )
    {
        $url = 'show_list&offset=0&field=%field%&direction=%direction%';
        if (!empty($date))
        	$url .= '&date='.$date;
        elseif (!empty($start) && !empty($stop))
        	$url .= '&start='.$start.'&stop='.$stop;
        $array = array(//
            '%url_sort_id%' => (($field == 'id')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'id'), $url)):(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'id'), $url))),
            '%url_sort_date%' => (($field == 'date')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'date'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'date'), $url))),
            '%url_sort_txt%' => (($field == 'txt')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'txt'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'txt'), $url))),
            '%url_sort_available%' => (($field == 'available')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'available'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'available'), $url))),
            '%url_sort_author%' => (($field == 'author')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'author'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'author'), $url))),
        );
        return $array;
    }


    private function get_reviews_sort_headers($field, $direction, $date = null, $start = null, $stop = null )
    {
        $url = 'show_reviews_list&offset=0&field=%field%&direction=%direction%';
        if (!empty($date))
        	$url .= '&date='.$date;
        elseif (!empty($start) && !empty($stop))
        	$url .= '&start='.$start.'&stop='.$stop;
        $array = array(
            '%url_sort_when%' => (($field == 'when')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'when'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'when'), $url))),
            '%url_sort_available%' => (($field == 'available')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'available'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'available'), $url))),
            '%url_sort_name%' => (($field == 'name')?(str_replace(array('%direction%', '%field%'), array((strtoupper($direction) == 'ASC')?('DESC'):('ASC'), 'name'), $url)):(str_replace(array('%direction%', '%field%'), array('ASC', 'name'), $url))),
        );
        return $array;
    }

    /**
     * Возвращает html код select'a
     *
     * @param string $name
     * @param array $array
     * @param array $selected
     * @param boolean $optgruop
     * @param string $style
     * @param boolean $multiple
     * @param integer $size
     * @param boolean $disabled
     * @param string $adds
     * @return string
     */
    private function generate_html_select($name, $array, $selected = array(), $optgruop = false, $style = "", $multiple = false, $size = 1, $disabled = false, $adds = '')
    {
        $html_select = '<select id="'.$name.'" '.($multiple?'multiple="multiple"':'').' size="'.$size.'" name="'.$name.'" style="'.$style.'"'.($disabled?'disabled="disabled"':'').' class="text" '.$adds.'>'."\n";
        switch ($optgruop)
        {
            case false:
                foreach ($array as $option => $label)
                {
                    if (!is_null($selected) && in_array($option, $selected))
                        $html_select .= '<option value="'.$option.'" selected="selected"">'.htmlspecialchars($label).'</option>'."\n";
                    else
                        $html_select .= '<option value="'.$option.'">'.$label.'</option>'."\n";
                }
                break;

            case true:
                foreach ($array as $key => $value)
                {
                    $html_select .= '<optgroup label="'.$key.'">'."\n";
                    foreach ($value as $option => $label)
                    {
                        if (!is_null($selected) && in_array($option, $selected))
                            $html_select .= '<option value="'.$option.'" selected="selected" style="background-color: white;">'.htmlspecialchars($label).'</option>'."\n";
                        else
                            $html_select .= '<option value="'.$option.'">'.$label.'</option>'."\n";
                    }
                    $html_select .= '</optgroup>'."\n";
                }
            	break;
        }
        $html_select .= '</select>'."\n";
        return $html_select;
    }
}