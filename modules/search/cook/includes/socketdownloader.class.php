<?php

class SocketDownloader extends Downloader
{
	function get($url)
	{
		$result = new DownloaderResult();
        $result->set_url($url);

		$thread = new Thread($url);
		$thread->set_connect_timeout($this->connect_timeout);
		$thread->set_idle_timeout($this->timeout);
		//$thread->set_max_size(false);
		$headers = array();
		foreach ($this->headers as $header)
			if (preg_match("'^(.+?): (.+?)$'", $header, $matches))
				$headers[$matches[1]] = $matches[2];

		$thread->headers = $headers;
		$thread->go();
		$result->set_errno($thread->errno);
		$result->set_error($thread->errors[$thread->errno]);
		$result->set_responsecontent($thread->response_body);
		$result->set_responseheaders($thread->response_headers);
		return $result;
	}

	function post($url, $data)
	{
		$result = new DownloaderResult();
        $result->set_url($url);

		$thread = new Thread($url);
		$thread->set_connect_timeout($this->connect_timeout);
		$thread->set_idle_timeout($this->timeout);

		$thread->set_method("POST");
		$thread->set_post_data($data);
		//$thread->set_max_size(false);
		$headers = array();
		foreach ($this->headers as $header)
			if (preg_match("'^(.+?): (.+?)$'", $header, $matches))
				$headers[$matches[1]] = $matches[2];

		$thread->headers = $headers;
		$thread->go();
		$result->set_errno($thread->errno);
		$result->set_error($thread->errors[$thread->errno]);
		$result->set_responsecontent($thread->response_body);
		$result->set_responseheaders($thread->response_headers);
		return $result;
	}
}