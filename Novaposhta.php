<?php

class Novaposhta {
	
	const URL_ALL_OFFICES = 'http://novaposhta.ua/frontend/brunchoffices/ru?alpha=all';
	
	const URL_CLOSEST_OFFICES = 'http://maps.novaposhta.ua/index.php?r=site/nearest';
	
	protected $baseOfficeLink = 'http://novaposhta.ua/map/warehouse/id/%s';
	
	protected $baseOfficeMap = 'https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false&language=ru&region=ua';
	
	protected $regexOfficeId = '#/map/warehouse/id/([0-9]+)#';
	
	public function getAllOffices() {
		$result = array();
		
		$doc = @DOMDocument::loadHTMLFile(self::URL_ALL_OFFICES);
		
		$table = $doc->getElementsByTagName('table')->item(1);
		
		foreach($table->getElementsByTagName('tr') as $tr) {
			$children = $tr->getElementsByTagName('td');
			
			if ($children->length == 1) {
				$offCity = trim($children->item(0)->nodeValue);
			} else {
				$a = $children->item($children->length - 1)->getElementsByTagName('a');
				
				if (!$a->length) {
					continue;
				}
				
				$a = $a->item(0);
				
				preg_match($this->regexOfficeId, $a->getAttribute('href'), $match);
				$offId = $match[1];
				
				$addrNode = $children->item(1);
				$address = explode(':', trim($addrNode->nodeValue));
				
				$offName = isset($address[0]) ? $address[0] : '';
				$offAddress = isset($address[1]) ? $address[1] : '';
				
				$phoneNode = $children->item(2);
				$offPhone = trim($phoneNode->nodeValue);
				
				$result[] = array(
					'id' => $offId,
					'name' => $offName,
					'address' => $offAddress,
					'phone' => $offPhone,
					'city' => $offCity
				);
			}
		}
		
		return $result;
	}
	
	protected function _getCoords($address) {
		$encoded = str_replace(' ', '+', trim($address));
		
		if (!$encoded) {
			return false;
		}
		
		$url = sprintf($this->baseOfficeMap, $encoded);
		
		$json = @file_get_contents($url);
		
		if ($json === false) {
			return false;
		}
		
		$json = json_decode($json);
		
		if ($json->status != 'OK') {
			return false;
		}
		
		$mapObj = array_shift($json->results);
		
		$coords['lat'] = $mapObj->geometry->location->lat;
		$coords['lng'] = $mapObj->geometry->location->lng;
		
		return $coords;
	}
	
	public function getClosestOffices($address) {
		$coords = $this->_getCoords($address);
		
		if ($coords === false) {
			return false;
		}
		
		$postdata = http_build_query($coords);
		
		$options = array(
			'http' => array(
				'method'  => 'POST',
				'header'  => 'Content-type: application/x-www-form-urlencoded',
				'content' => $postdata
			)
		);
		
		$context  = stream_context_create($options);
		
		$json = @file_get_contents(self::URL_CLOSEST_OFFICES, false, $context);
		
		if ($json === false) {
			return false;
		}
		
		$json = json_decode($json);
		
		$result = array();
		
		foreach($json as $office) {
			$result[] = $office->id;
		}
		
		return $result;
	}
	
	public function getOfficeLink($id) {
		return sprintf($this->baseOfficeLink, $id);
	}
	
}

/* EOF */