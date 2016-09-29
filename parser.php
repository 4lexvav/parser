<?php

ini_set('display_errors', false);

libxml_use_internal_errors(true);

class Parser
{
	const LIMIT = 1000;

	/**
	 * Parsed site
	 * Format:
	 * 	[
	 * 		'url' => [
	 * 			'title' => 'Title',
	 * 			'description' => 'Meta Description',
	 * 			'keywords' => 'Meta Keywords',
	 * 			'link_text' => 'Text for tag "a"'
	 * 		]
	 * 	]
	 *
	 * @var array
	 */
	private $result = [];

	/**
	 * @var DOMDocument
	 */
	private $dom = null;

	/**
	 * @var string $site - parsed site
	 */
	private $site = null;

	/**
	 * @var string $host - site's host
	 */
	private $host = null;

	private $ignoredPages = [];

	private $ignoredPatterns = ['basket'];

	/**
	 * Load HTML of the passed site to the DOMDocument
	 *
	 * @param string $site
	 * @param string $host
	 */
	public function __construct($site, $host = null)
	{
		$this->site = $site;
		$this->host = $host;
		$this->dom = new DOMDocument();
		$this->dom->loadHTML(file_get_contents($site, false));
	}

	public function parse($url = null, $linkText = null)
	{
		$result = [];

		if (count($this->result) > self::LIMIT) {
			return $this->result;
		}

		if ($url) {
			// Ignore not valid pages
			if (!$this->canParse($url)) {
				return $result;
			}

			$dom = $this->createDOM($url);
		} else {
			$url = $this->site;
			$dom = $this->dom;
		}


		if ($dom && $this->canParse($url)) {
			$result = [
				'linkText'		=> $linkText,
				'title' => $this->getTitle($dom),
				'description' => $this->getMeta($dom, 'description'),
				'keywords' => $this->getMeta($dom, 'keywords'),
			];

			$this->result[$url] = $result;

			/* @var $tagA DOMElement */
			foreach ($dom->getElementsByTagName('a') as $tagA) {
				$href = $tagA->getAttribute('href');
				if ($this->isValidHref($href)) {
					$link = (strpos($href, '/') === 0) ? $this->site . ltrim($href, '/') : $href;
					if ($this->canParse($link)) {
						$this->parse($link, $tagA->nodeValue);
					}
				}
			}
		}

		return $this->result;
	}

	/**
	 * Check if HREF attribute contains valid link which can be parsed
	 *
	 * @param string $href
	 *
	 * @return bool
	 */
	private function isValidHref($href)
	{
		$result = true;
		if (strpos($href, '/') === false) {
			return false;
		}

		foreach ($this->ignoredPatterns as $pattern) {
			if (strpos($href, $pattern)) {
				$result = false;
				break;
			}
		}

		return $result;
	}

	/**
	 * Can parse page or not
	 *
	 * @param string $url
	 *
	 * @return bool
	 */
	private function canParse($url)
	{
		if (array_key_exists($url, $this->result)) {
			return false;
		}

		if (strpos($url, $this->host) === false
			|| in_array($url, $this->ignoredPages)
			|| strpos($url, 'jpg')
			|| strpos($url, 'jpeg')
			|| strpos($url, 'png')
			|| strpos($url, 'pdf')
			|| strpos($url, 'gif')
			|| strpos($url, '?')
		) {
			if (!in_array($url, $this->ignoredPages)) {
				$this->ignoredPages[] = $url;
			}

			return false;
		}

		return true;
	}

	/**
	 * Create DOMDocument object from URL
	 *
	 * @param string $url
	 *
	 * @return DOMDocument
	 */
	public function createDOM($url)
	{
		$dom = null;
		// $html = file_get_contents($url, false);
		$html = $this->curlGetContents($url);

		if ($html) {
			$dom = new DOMDocument();
			$dom->loadHTML($html);
		} else {
			// Add URL to ingored pages if its content not accessible
			$this->ignoredPages[] = $url;
		}

		return $dom;
	}

	private function curlGetContents($url)
	{
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		$data = curl_exec($ch);
		curl_close($ch);

		return $data;
	}

	private function getMeta(DOMDocument $dom, $type)
	{
		foreach($dom->getElementsByTagName('meta') as $tag) {
			if ($tag->getAttribute('name') === $type) {
				return $tag->getAttribute('content');
			}
		}

		return '';
	}

	private function getTitle(DOMDocument $dom)
	{
		foreach($dom->getElementsByTagName('title') as $tag) {
			return $tag->nodeValue;
		}

		return '';
	}
}

function returnCsv($rows) {
	// output headers so that the file is downloaded rather than displayed
	header('Content-Type: text/csv; charset=utf-8');
	header('Content-Disposition: attachment; filename=data.csv');

	// create a file pointer connected to the output stream
	$output = fopen('php://output', 'w');

	// output the column headings
	fputcsv($output, ['URL', 'Link Text', 'Title', 'Description', 'Keys']);


	// loop over the rows, outputting them
	foreach ($rows as $key => $item) {
		$row = [$key, $item['linkText'], $item['title'], $item['description'], $item['keywords']];

		fputcsv($output, $row);
	}
}

$url = $_GET['url'];
$parsedUrl = parse_url($url);
$host = isset($parsedUrl['host']) ? $parsedUrl['host'] : null;

$parser = new Parser($url, $host);
$result = $parser->parse();

returnCsv($result);

/*echo '<pre>';
print_r(count($result));
echo '</pre>';
echo '<pre>';
print_r($result);
echo '</pre>';
die();*/