<?php

class Novaposhta_Exception extends Exception {}

class Novaposhta {
    
    const URL_ALL_OFFICES = 'http://novaposhta.ua/frontend/brunchoffices/ru?alpha=all';
    
    const URL_CLOSEST_OFFICES = 'http://maps.novaposhta.ua/index.php?r=site/nearest';
    
    const URL_DELIVERY_STATUS = 'http://novaposhta.ua/frontend/tracking/ru';
    
    protected $baseOfficeLink = 'http://novaposhta.ua/map/warehouse/id/%s';
    
    protected $baseOfficeMap = 'https://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false&language=ru&region=ua';
    
    protected $regexOfficeId = '#/map/warehouse/id/([0-9]+)#';
    
    protected $regexOfficeCity = '#(.+) \((.+)\)#';
    
    protected $regexOfficeWeights = '#(\(.*\))#';
    
    protected $uselessInAddress = array('Отделение,');
    
    protected $encoding = 'UTF-8';
    
    protected $cacheFile;
    
    protected $cacheExpire;
    
    private $_allOffices = null;
    
    public static $CURL_OPTS = array(
        CURLOPT_CONNECTTIMEOUT    => 10,
        CURLOPT_RETURNTRANSFER    => 1,
        CURLOPT_TIMEOUT           => 60
    );
    
    public function __construct($config = array()) {
        $this->cacheFile = isset($config['cacheFile']) ? $config['cacheFile'] : dirname(__FILE__).'/.np_offices_cache';
        $this->cacheExpire = isset($config['cacheExpire']) ? $config['cacheExpire'] : 24 * 3600;
    }
    
    protected function _ucfirst($str) {
        return mb_strtoupper(mb_substr($str, 0, 1, $this->encoding), $this->encoding).mb_substr($str, 1, mb_strlen($str, $this->encoding) - 1, $this->encoding);
    }
    
    protected function _setCache($data) {
        $f = @fopen($this->cacheFile, 'w');
        if ($f !== false) {
            fwrite($f, serialize($data));
            fclose($f);
        }
    }
    
    protected function _getFromCache() {
        if (!file_exists($this->cacheFile) || time() - filemtime($this->cacheFile) > $this->cacheExpire) {
            return null;
        }
        return unserialize(file_get_contents($this->cacheFile));
    }
    
    public function getAllOffices() {
        if ($this->_allOffices) {
            return $this->_allOffices;
        }
        
        $this->_allOffices = $this->_getFromCache();
        if ($this->_allOffices) {
            return $this->_allOffices;
        }
        
        $allOffices = array();
        
        $doc = @DOMDocument::loadHTML($this->_fetch(self::URL_ALL_OFFICES));
        
        $table = $doc->getElementsByTagName('table')->item(1);
        
        foreach($table->getElementsByTagName('tr') as $tr) {
            $children = $tr->getElementsByTagName('td');
            
            if ($children->length == 1) {
                $city = trim($children->item(0)->nodeValue);
                
                preg_match($this->regexOfficeCity, $city, $match);
                $offCity = $match[1];
                $offRegion = $match[2];
            } else {
                $a = $children->item($children->length - 1)->getElementsByTagName('a');
                
                if (!$a->length) {
                    continue;
                }
                
                $a = $a->item(0);
                
                preg_match($this->regexOfficeId, $a->getAttribute('href'), $match);
                $offId = $match[1];
                
                $offNode = $children->item(0);
                $offName = $this->_ucfirst(trim($offNode->nodeValue));
                
                $addrNode = $children->item(1);
                $address = explode(':', trim($addrNode->nodeValue), 2);
                
                if (count($address) < 2) {
                    $offAddress = $address[0];
                } else {
                    if (preg_match($this->regexOfficeWeights, $address[0], $match)) {
                        $offName .= ' '.$match[1];
                    }
                    $offAddress = $address[1];
                }
                $offAddress = trim(str_replace($this->uselessInAddress, '', $offAddress));
                
                $phoneNode = $children->item(2);
                $offPhone = trim($phoneNode->nodeValue);
                
                $allOffices[] = array(
                    'id' => $offId,
                    'link' => $this->getOfficeLink($offId),
                    'name' => $offName,
                    'address' => $offAddress,
                    'phone' => $offPhone,
                    'city' => $offCity,
                    'region' => $offRegion
                );
            }
        }
        
        $this->_setCache($allOffices);
        $this->_allOffices = $allOffices;
        
        return $allOffices;
    }
    
    protected function _getCoords($address) {
        $encoded = str_replace(' ', '+', trim($address));
        
        if (!$encoded) {
            return false;
        }
        
        $url = sprintf($this->baseOfficeMap, $encoded);
        
        $jsonStr = $this->_fetch($url);
        
        $json = json_decode($jsonStr);
        
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
        
        $jsonStr = $this->_fetch(self::URL_CLOSEST_OFFICES, $coords, true);
        
        $json = json_decode($jsonStr);
        
        $result = array();
        
        foreach($json as $office) {
            $result[] = $this->getOfficeById($office->id);
        }
        
        return $result;
    }
    
    public function getOfficeLink($id) {
        return sprintf($this->baseOfficeLink, $id);
    }
    
    protected function _fetch($url, $params = array(), $post = false) {
        $ch = curl_init();
        
        curl_setopt_array($ch, self::$CURL_OPTS);
        
        if ($post) {
            curl_setopt($ch, CURLOPT_POST, 1);
            if ($params) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            }
        } elseif ($params) {
            $url .= (strpos($url, '?') !== false ? '&' : '?').http_build_query($params);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        
        $response = curl_exec($ch);
        
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($errno) {
            throw new Novaposhta_Exception($error, $errno);
        }
        
        return $response;
    }
    
    public function getOfficeById($id) {
        if (!$id || !is_numeric($id)) {
            throw new Novaposhta_Exception('Wrong format for Id. It should be numeric value.');
        }
        
        foreach($this->getAllOffices() as $office) {
            if ($office['id'] == $id) {
                return $office;
            }
        }
        
        return false;
    }
    
    public function getOfficesBy($field, $value, $substr = false) {
        $allOffices = $this->getAllOffices();
        
        if (!isset($allOffices[0][$field])) {
            throw new Novaposhta_Exception('Unknown field specified.');
        }
        
        $value = trim($value);
        
        if (!$value) {
            throw new Novaposhta_Exception('Value is not specified.');
        }
        
        $result = array();
        
        if ($substr) {
            foreach($allOffices as $office) {
                if (mb_strpos($office[$field], $value, 0, $this->encoding) !== false) {
                    $result[] = $office;
                }
            }
        } else {
            foreach($allOffices as $office) {
                if ($office[$field] == $value) {
                    $result[] = $office;
                }
            }
        }
        
        return $result;
    }
    
    public function getDeliveryStatus($id) {
        $doc = @DOMDocument::loadHTML($this->_fetch(self::URL_DELIVERY_STATUS, array('en' => $id), true));
        
        $xpath = new DOMXPath($doc);
        
        $resultNode = $xpath->query('//div[@class="result"]/table|//div[@class="result"]/div');
        
        if ($resultNode->length != 1) {
            return false;
        }
        
        $statusNode = $resultNode->item(0);
        
        if ($statusNode->nodeName == 'table') {
            $result = array();
            
            foreach($statusNode->getElementsByTagName('tr') as $tr) {
                $cells = $tr->getElementsByTagName('td');
                
                $row = array(
                    'caption' => $cells->item(0)->nodeValue,
                    'message' => $cells->item(1)->nodeValue
                );
                
                $a = $cells->item(1)->getElementsByTagName('a');
                
                if ($a->length) {
                    if (preg_match($this->regexOfficeId, $a->item(0)->getAttribute('href'), $match)) {
                        $row['ref_office'] = $this->getOfficeById($match[1]);
                    }
                }
                
                $result[] = $row;
            }
        } else {
            $result = $statusNode->nodeValue;
        }
        
        return $result;
    }

}

/* EOF */