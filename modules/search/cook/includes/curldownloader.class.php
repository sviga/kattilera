<?php

class CurlDownloader extends Downloader
{
	function CurlDownloader()
	{

	}


	function proxy($ch)
	{
	    $proxy_list = file('proxylist.txt');

	    $clean_proxy_list = array();

	    foreach ($proxy_list as $line)
	    {
	        $line = trim($line);
	        if (strlen($line) > 0)
	           $clean_proxy_list[] = $line;
	    }

        $random_proxy = $proxy_list[array_rand($clean_proxy_list)];

        $parts = explode(" ", $random_proxy);

		curl_setopt($ch, CURLOPT_PROXY,         $parts[0]);
        curl_setopt($ch, CURLOPT_PROXYUSERPWD,  $parts[1]);
	}

	function get($url)
	{
		$result = new DownloaderResult();
		$result->set_url($url);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        //$this->proxy($ch);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);

		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_getinfo($ch);
		curl_close($ch);

		$result->set_errno($errno);
		$result->set_error($error);

		if ($errno == 0)
			$result->set_response($response);

		return $result;
	}


	function post($url, $post_data)
	{
		$result = new DownloaderResult();
		$result->set_url($url);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connect_timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        //$this->proxy($ch);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);

		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_getinfo($ch);
		curl_close($ch);

		$result->set_errno($errno);
		$result->set_error($error);
		if ($errno == 0)
			$result->set_response($response);
		return $result;
	}

}
