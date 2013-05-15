<?php

abstract class Downloader
{

	var $downloaderresult = null;
	var $connect_timeout = 20;
	var $timeout = 100;
	var $headers = array(
	                        'User-Agent: Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1)',
                        	'Connection: Keep-Alive',
                        	'Accept: image/gif, image/x-xbitmap, image/jpeg, image/pjpeg, application/x-shockwave-flash, application/vnd.ms-excel, application/vnd.ms-powerpoint, application/msword, application/x-gsarcade-launch, */*'
                        );


    /**
     * @abstract
     * @param $url
     * @return DownloaderResult
     */
    abstract function get($url);

    /**
     * @abstract
     * @param $url
     * @param $post_data
     * @return DownloaderResult
     */
    abstract function post($url, $post_data);

	function get_instance()
	{
		if (function_exists("curl_setopt"))
			return new CurlDownloader();
		else
			return new SocketDownloader();
	}



	function get_with_301_follow($url)
	{
		$response = $this->get($url);
		$headers = $response->responseheaders;
		if ($headers->response_code == 301
			&& $headers->get_header("Location"))
		{
			$response = $this->get($headers->get_header("Location"));
		}
		return $response;
	}


	function post_with_301_follow($url, $data)
	{
		$response = $this->post($url, $data);
		$headers = $response->responseheaders;
		if ($headers->response_code == 301
			&& $headers->get_header("Location"))
		{
			$response = $this->post($headers->get_header("Location"), $data);
		}
		return $response;
	}


	function get_with_non_ssl_redirect($url)
	{
		$response = $this->get($url);
		$headers = $response->responseheaders;
		if (($headers->response_code == 301 || $headers->response_code == 302)
			&& $headers->get_header("Location") && !preg_match("'^https:'i", $headers->get_header("Location")))
		{
			$response = $this->get($headers->get_header("Location"));
		}
		return $response;
	}


	/*function post_with_301_follow($url, $data)
	{
		$response = $this->post($url, $data);
		return $response;
	}

*/
	function set_connect_timeout($connect_timeout)
	{
		$this->connect_timeout = $connect_timeout;
	}

	function set_timeout($timeout)
	{
		$this->timeout = $timeout;
	}

	function set_headers($headers)
	{
		$this->headers = $headers;
	}

	function add_header($header)
	{
		$this->headers[] = $header;
	}

}