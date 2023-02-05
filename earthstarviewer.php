<?php
/**
* EarthStarViewer is a class for downloading manga chapters from the reader on comic-earthstar.jp.
* To download a chapter, an ID is required. This can be acquired in different ways.
* The class will then attempt to download the entire chapter, saving all pages decrypted to a local file.
* 
* The reader is meant to be used for reading the newest chapter of mangas published by Comic Earth Star.
* But since they don't delete old chapters from the page it is possible to still access them, long after they have been published.
* To read a chapter an ID 
*/
class EarthStarViewer {
	
	private string $hash;
	private array $urlParts;
	private array $savedPatterns;
	
	/**
	* constructor for class EarthStarViewer
	*
	* @param string $hash
	* @access public
	*/
	public function __construct(string $hash) {
		$this->hash = $hash;
		$this->urlParts = array();
	}
	
	/**
	* getter for the name of the series that is associated with the current ID
	*
	* @return string  the name of the series associated with the current ID
	* @access public
	*/
	public function getSeries() : string {
		$this->loadUrlParts();
		return $this->urlParts[4];
	}
	
	/**
	* main function for downloading the chapter
	*
	* @access public
	*/
	public function download() : void {
		$this->loadUrlParts();
		
		$outputPath = "out/" . $this->urlParts[4] . "/" . $this->urlParts[5];
		
		$configReq = curl_init($this->base->url . "configuration_pack.json");
		curl_setopt($configReq, CURLOPT_RETURNTRANSFER, true);
		$configRes = curl_exec($configReq);
		$config = json_decode($configRes);
		
		$status = curl_getinfo($configReq, CURLINFO_RESPONSE_CODE);
		
		curl_close($configReq);
		
		if($status != 200) {
			$configReq = curl_init($this->base->url . "normal_default/configuration_pack.json");
			curl_setopt($configReq, CURLOPT_RETURNTRANSFER, true);
			$configRes = curl_exec($configReq);
			$config = json_decode($configRes);
			
			$status = curl_getinfo($configReq, CURLINFO_RESPONSE_CODE);
			
			curl_close($configReq);
			
			if($status != 200) {
				$error = new stdClass();
				$error->status = $status;
				$error->hash = $this->hash;
				throw new Exception(json_encode($error));
			}
		}

		foreach($config->configuration->contents as $content) {
			$file = (string) $content->file;
			$pageNum = (string) $content->index;
			$fileData = $config->$file;
			
			foreach($fileData->FileLinkInfo->PageLinkInfoList as $pageLinkInfoList) {
				$patternBase = $file . "/" . $pageLinkInfoList->Page->No;
				for($i = $sum = 0; $i < strlen($patternBase); $i++) {
					$sum += ord(substr($patternBase, $i, 1));
				}
				$pattern = $sum % 4 + 1;
				
				$src = @imagecreatefromjpeg(str_replace("../", "", $this->base->url . $file . "/" . $pageLinkInfoList->Page->No . ".jpeg"));
				if(!$src) continue;
				$data = ["width" => imagesx($src), "height" => imagesy($src), "pattern" => $pattern];
				$dst = imagecreatetruecolor($data["width"], $data["height"]);
				
				$this->drawPage($src, $data, $dst);
				
				if(!is_dir("./" . $outputPath)) mkdir("./" . $outputPath, recursive: true);
				imagejpeg($dst, "./" . $outputPath . "/" . $pageNum . ".jpg");
				
				imagedestroy($src);
				imagedestroy($dst);
			}
		}
	}
	
	/**
	* function for drawing a single page
	*
	* @param GdImage $src  the scrambled source image
	* @param array $data   src image data including height, width and pattern
	* @param GdImage $dst  the destination image where the correct image will be written
	* @access public
	*/
	private function drawPage(GdImage &$src, array $data, GdImage &$dst) : void {
		foreach($this->patternUnscrambler($data["width"], $data["height"], $data["pattern"]) as $part) {
			if($part["srcX"] < 0 + $data["width"] && $part["srcX"] + $part["width"] >= 0 && $part["srcY"] < 0 + $data["height"] && $part["srcY"] + $part["height"] >= 0) {
				imagecopy($dst, $src, $part["srcX"], $part["srcY"], $part["dstX"], $part["dstY"], $part["width"], $part["height"]);
			}
		}
	}
	
	/**
	* helper function for unscrambling an image based on its pattern
	*
	* @param int $width    the width of the image
	* @param int $height   the height of the image
	* @param int $pattern  the pattern which was used to scramble the original image
	* @access private
	*/
	private function patternUnscrambler(int $width, int $height, int $pattern) : array {
		if(isset($this->savedPatterns[$pattern])) return $this->savedPatterns[$pattern];
		
		$chunkSizeX = $chunkSizeY = 64;
		
		$v = array();
		
		$c = floor($width / $chunkSizeX);
		$g = floor($height / $chunkSizeY);
		
		$width %= $chunkSizeX;
		$height %= $chunkSizeY;
		
		$h = $c - 43 * $pattern % $c;
		$h = 0 == $h % $c ? ($c - 4) % $c : $h;
		$h = 0 == $h ? $c - 1 : $h;
		$l = $g - 47 * $pattern % $g;
		$l = 0 == $l % $g ? ($g - 4) % $g : $l;
		$l = 0 == $l ? $g - 1 : $l;
		
		if(0 < $width && 0 < $height) {
			$k = $h * $chunkSizeX;
			$m = $l * $chunkSizeY;
			
			array_push($v, array(
				"srcX" => $k,
				"srcY" => $m,
				"dstX" => $k,
				"dstY" => $m,
				"width" => $width,
				"height" => $height
			));
		}
		
		if(0 < $height) {
			for($t = 0; $t < $c; $t++) {
				$p = $this->calcXCoordinateXRest($t, $c, $pattern);
				$k = $this->calcYCoordinateXRest($p, $h, $l, $g, $pattern);
				$p = $this->calcPositionWithRest($p, $h, $width, $chunkSizeX);
				$r = $k * $chunkSizeY;
				$k = $this->calcPositionWithRest($t, $h, $width, $chunkSizeX);
				$m = $l * $chunkSizeY;
				
				array_push($v, array(
					"srcX" => $k,
					"srcY" => $m,
					"dstX" => $p,
					"dstY" => $r,
					"width" => $chunkSizeX,
					"height" => $height
				));
			}
		}
		
		if(0 < $width) {
			for($q = 0; $q < $g; $q++) {
				$k = $this->calcYCoordinateYRest($q, $g, $pattern);
				$p = $this->calcXCoordinateYRest($k, $h, $l, $c, $pattern);
				$p *= $chunkSizeX;
				$r = $this->calcPositionWithRest($k, $l, $height, $chunkSizeY);
				$k = $h * $chunkSizeX;
				$m = $this->calcPositionWithRest($q, $l, $height, $chunkSizeY);
				
				array_push($v, array(
					"srcX" => $k,
					"srcY" => $m,
					"dstX" => $p,
					"dstY" => $r,
					"width" => $width,
					"height" => $chunkSizeY
				));
			}
		}
		
		for($t = 0; $t < $c; $t++) {
			for($q = 0; $q < $g; $q++) {
				$p = ($t + 29 * $pattern + 31 * $q) % $c;
				$k = ($q + 37 * $pattern + 41 * $p) % $g;
				$r = $p >= $this->calcXCoordinateYRest($k, $h, $l, $c, $pattern) ? $width : 0;
				$m = $k >= $this->calcYCoordinateXRest($p, $h, $l, $g, $pattern) ? $height : 0;
				$p = $p * $chunkSizeX + $r;
				$r = $k * $chunkSizeY + $m;
				$k = $t * $chunkSizeX + ($t >= $h ? $width : 0);
				$m = $q * $chunkSizeY + ($q >= $l ? $height : 0);
				
				array_push($v, array(
					"srcX" => $k,
					"srcY" => $m,
					"dstX" => $p,
					"dstY" => $r,
					"width" => $chunkSizeX,
					"height" => $chunkSizeY
				));
			}
		}
		
		$this->savedPatterns[$pattern] = $v;
		
		return $v;
	}
	
	/**
	* helper function for the unscrambler. Calculates the position with rest
	* 
	* @param int $width       width of the image
	* @param int $height      height of the image
	* @param int $chunkSizeX  size of the chunk in the x direction
	* @param int $chunkSizeY  size of the chunk in the y direction
	* @access private
	*/
	private function calcPositionWithRest(int $width, int $height, int $chunkSizeX, int $chunkSizeY) : int {
		return $width * $chunkSizeY + ($width >= $height ? $chunkSizeX : 0);
	}
	
	/**
	* helper function for the unscrambler. Calculates the x coordinate with x rest
	*
	* @param int $width       width of the image
	* @param int $height      height of the image
	* @param int $chunkSizeX  size of the chunk in the x direction
	* @access private
	*/
	private function calcXCoordinateXRest(int $width, int $height, int $chunkSizeX) : int {
		return ($width + 61 * $chunkSizeX) % $height;
	}
	
	/**
	* helper function for the unscrambler. Calculates the y coordinate with x rest
	* 
	* @param int $width       width of the image
	* @param int $height      height of the image
	* @param int $chunkSizeX  size of the chunk in the x direction
	* @param int $chunkSizeY  size of the chunk in the y direction
	* @param int $pattern     the pattern which was used to scramble the original image
	* @access private
	*/
	private function calcYCoordinateXRest(int $width, int $height, int $chunkSizeX, int $chunkSizeY, int $pattern) : int {
		$tmp = 1 === $pattern % 2;
		if($width < $height ? $tmp : !$tmp) {
			$chunkSizeY = $chunkSizeX;
			$height = 0;
		} else {
			$chunkSizeY -= $chunkSizeX;
			$height = $chunkSizeX;
		}
		return ($width + 53 * $pattern + 59 * $chunkSizeX) % $chunkSizeY + $height;
	}
	
	/**
	* helper function for the unscrambler. Calculates the x coordinate with y rest
	* 
	* @param int $width       width of the image
	* @param int $height      height of the image
	* @param int $chunkSizeX  size of the chunk in the x direction
	* @param int $chunkSizeY  size of the chunk in the y direction
	* @param int $pattern     the pattern which was used to scramble the original image
	* @access private
	*/
	private function calcXCoordinateYRest(int $width, int $height, int $chunkSizeX, int $chunkSizeY, int $pattern) : int {
		$tmp = 1 == $pattern % 2;
		if($width < $chunkSizeX ? $tmp : !$tmp) {
			$chunkSizeY -= $height;
			$chunkSizeX = $height;
		} else {
			$chunkSizeY = $height;
			$chunkSizeX = 0;
		}
		return ($width + 67 * $pattern + $height + 71) % $chunkSizeY + $chunkSizeX;
	}
	
	/**
	* helper function for the unscrambler. Calculates the y coordinate with y rest
	*
	* @param int $width       width of the image
	* @param int $height      height of the image
	* @param int $chunkSizeX  size of the chunk in the x direction
	* @access private
	*/
	private function calcYCoordinateYRest(int $width, int $height, int $chunkSizeX) : int {
		return ($width + 73 * $chunkSizeX) % $height;
	}
	
	/**
	* helper function for loading of url parts. Will only be executed once and saves its results
	*
	* @access private
	*/
	private function loadUrlParts() : void {
		if(!empty($this->urlParts)) return;
		
		$baseReq = curl_init("https://api.comic-earthstar.jp/c.php?cid=" . $this->hash);
		curl_setopt($baseReq, CURLOPT_RETURNTRANSFER, true);
		$baseRes = curl_exec($baseReq);
		$this->base = json_decode($baseRes);
		curl_close($baseReq);
		
		if($this->base->status != 200) {
			$this->base->hash = $this->hash;
			throw new Exception(json_encode($this->base));
		}
		
		$this->base->url = "https:" . str_replace("http:", "", str_replace("https:", "", $this->base->url));
		
		$this->urlParts = explode("/", $this->base->url);
		
		if(count($this->urlParts) < 5) {
			$error = new stdClass();
			$error->status = 404;
			$error->hash = $this->hash;
			throw new Exception(json_encode($error));
		}
	}
}
?>