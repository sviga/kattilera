<?PHP

class finance {

    function finance() {
    	global $kernel;
    }


    //***********************************************************************
    //	Наборы Публичных методов из которых будут строится макросы
    //**********************************************************************

   /**
    * Публичный метод для действия 'Показать график Yahoo'
    *
    * @param string $template Шаблон вывода формы
    * @return HTML
    */
	public function pub_show_finance_chart_yahoo($template, $link_to_data, $wait_time) {
        global $kernel;

        if(is_nan($wait_time)) {
            $wait_time = 60*60;
        }
        // Парсим шаблон
        $template = trim( $template );
        if( !empty( $template ) && file_exists( $template ) )
        	$template = $kernel -> pub_template_parse( $template );
      	else
        	return '[#finance_error_no_template#]';

        $data = 'cache/yahoo_data.csv';
        $volume = 'cache/yahoo_volume.csv';
        if(!file_exists($data) || !file_exists($volume) || time()-filemtime($data) > $wait_time) {
            $data_from_yahoo = file_get_contents($link_to_data);
            $data_from_yahoo = simplexml_load_string($data_from_yahoo);
            if(empty($data_from_yahoo->error)) {
                $series = $data_from_yahoo->series;
                $data = "";
                $volume = "";
                $data_count = count($series->p);
                for($i=0;$i<$data_count;$i++) {
                    $ar = ((array)$series->p[$i]->v);
                    $data   .= ((int)$series->p[$i]["ref"])*1000 .";". $ar[0]."\n";
                    $volume .= ((int)$series->p[$i]["ref"])*1000 .";". $ar[4]."\n";
                }

                file_put_contents("cache/yahoo_data.csv", $data);
                file_put_contents("cache/yahoo_volume.csv", $volume);
            }
        }
        $html = $template['begin_chart'];

        return $html;
    }

    /**
     * Публичный метод для действия 'Показать график Google'
     *
     * @param string $template Шаблон вывода формы
     * @return HTML
     */
    public function pub_show_finance_chart_google($template, $link_to_data, $wait_time) {
        global $kernel;

        if(is_nan($wait_time)) {
            $wait_time = 60*60;
        }
        // Парсим шаблон
        $template = trim( $template );
        if( !empty( $template ) && file_exists( $template ) )
            $template = $kernel -> pub_template_parse( $template );
        else
            return '[#finance_error_no_template#]';

        $data = 'cache/google_data.csv';
        $volume = 'cache/google_volume.csv';
        if(!file_exists($data) || !file_exists($volume) || time()-filemtime($data) > $wait_time) {
            $data_from_google = file_get_contents($link_to_data);
            $data_from_google = explode("\n", $data_from_google);
            $i=8;
            if(!empty($data_from_google[$i])) {
                $ar = explode(",", $data_from_google[$i-1]);
                $time_start = str_replace("a", "", $ar[0]);
                $interval = str_replace("INTERVAL=", "", $data_from_google[3]);
                $data = $time_start*1000 .";". $ar[1]."\n";
                $volume = $time_start*1000 .";". $ar[2]."\n";
                $data_count = count($data_from_google) - $i;
                for(;$i<$data_count;$i++) {
                    $ar = explode(",", $data_from_google[$i]);
                    $time = $ar[0]*$interval+$time_start*1000;
                    $data   .= $time .";". $ar[1]."\n";
                    $volume .= $time .";". $ar[2]."\n";
                }

                file_put_contents("cache/google_data.csv", $data);
                file_put_contents("cache/google_volume.csv", $volume);
            }
        }
        $html = $template['begin_chart'];

        return $html;
    }


}

?>