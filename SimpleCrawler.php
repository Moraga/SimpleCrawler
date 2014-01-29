<?php
/**
 * PHP Simple Crawler
 */

class SimpleCrawler {
	/**
	 * Crawler user agent
	 * @var string
	 */
	public $userAgent = 'MyCrawler';
	
	/**
	 * Delay execution in microseconds
	 * 2000000 = 2 seconds
	 * @var int
	 */
	public $delay = 0;
	
	/**
	 * The URL to visit
	 * @var array
	 */
	private $urls = array();
	
	/**
	 * The URLs visited
	 * @var array
	 */
	private $visited = array();
	
	/**
	 * Starts the crawler
	 * @param string $url The URL to start
	 */
	function start($url) {
		$this->visit($url);
	}
	
	/**
	 * Gets the next URL to visit
	 * @return string Returns the next URL
	 */
	private function next() {
		foreach ($this->urls as $url)
			if (!in_array($url, $this->visited)) {
				$this->visit($url);
				return;
			}
	}
	
	/**
	 * Visits a URL
	 * @param string $url The URL to visit
	 */
	private function visit($url) {
		$this->report("Visiting: {$url}");
		$this->visited[] = $url;
		
		// URL data
		preg_match('@^(?:http://)?([^/]+)@i', $url, $info);
		
		$host = $info[1];
		$target = str_replace($info[0], '', $url);
		
		if ($target && $target{0} == '/')
			$target = substr($target, 1);
			
		if ($fp = @fsockopen($host, 80, $errno, $errstr)) {
			$request = "%s /%s HTTP/1.1\r\nHost:%s\r\nUser-Agent: {$this->userAgent} (compatible;)\r\nConnection: close\r\n\r\n";
			fwrite($fp, sprintf($request, 'GET', $target, $host));
			
			
			$http_status	= false;	// HTTP response
			$ctheader		= false;	// HTTP Headers
			$is_utf8		= false;	// uft-8 charset
			$content		= null;		// Content
			
			while (!feof($fp)) {
				$gets = fgets($fp, 128);
				if (!$http_status) {
					@preg_match('@HTTP\/\d\.\d\s(\d{3})\s(.+)@', $gets, $http);
					if ($http[1] != '200') {
						$this->report("HTTP {$http[1]} {$http[2]}\nExiting..");
						break;
					}
					else
						$http_status = true;
				}
				
				// while content-type in header not found
				if (!$ctheader) {
					if (preg_match('@^Content\-Type:\s(.*)@i', $gets, $ct)) {
						$ctheader = true;
						if (!@preg_match('@text\/html@i', $ct[1])) {
							$this->report("Non text/html : {$ct[1]}");
							break;
						}
						else {
							if (@preg_match('@utf\-8@i', $ct[1]))
								$is_utf8 = true;
						}
					}
				}
				else {
					$content .= $gets;
				}
			}
			
			fclose($fp);
		}
		
		$i = 0;
		
		if ($content) {
			preg_match_all('@href\=[\"|\'](.+)[\"|\']@Ui', $content, $links);
			
			foreach ($links[1] as $link) {
				if ($link{0} == '#' || stripos($link, $url.'#') === 0)
					continue;
				
				if (stripos($link, 'http://') !== 0)
					if ($link{0} == '/')
						$link = "http://{$host}{$link}";
					else
						$link = $url.$link;
				
				if (!in_array($link, $this->urls))
					$this->urls[] = $link;
			}
			
			if ($is_utf8)
				$content = utf8_decode($content);
			
			// do something with the content
			// $content ...
		}	
		
		usleep($this->delay);
		$this->next();
	}
	
	/**
	 * Reports a message
	 * @param string $message The message
	 */
	function report($message) {
		echo $message."\n";
	}
	
}

?>