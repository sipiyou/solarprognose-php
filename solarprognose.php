<?php
/*
  (c) 2022 Nima Ghassemi Nejad (sipiyou@hotmail.com)

  v 1.0 - 10.09.2022 - initial relase

  Small PHP class for retrieving data from http://www.solarprognose.de
*/
class generalHelpers {
    public function external_dbg ($p1,$p2) {
        if (function_exists('exec_debug'))
            exec_debug ($p1,$p2);
    }
    
    public function normalize (&$val, $div) {
        if ($val > 0)
            $val /= $div;
    }
   
    public function get01StateFromArray ($array, $key) {
        $ret = false;
        if (isset ($array[$key])) {
            if ($array[$key] === 1) {
                $ret = true;
            }
        }

        return ($ret);
    }

    public function getStateFromArray ($array, $key,$subkey = null) {
        $ret = '';
        if (isset($subkey)) {
            if (isset ($array[$key][$subkey]))
                $ret = $array[$key][$subkey];
             
        } else {
            if (isset ($array[$key]))
                $ret = $array[$key];
        }

        return ($ret);
    }

    public function getIntStateFromArray ($array, $key) {
        $ret = -1;
        if (isset ($array[$key]))
            $ret = intval ($array[$key]);
     
        return ($ret);
    }

    public function getAllValues ($path) {
        $val = $this->getStateFromArray ($path,'value');
        $unit = $this->getStateFromArray ($path, 'unit');
        $ts = $this->getStateFromArray ($path, 'timestamp');
        //print "value = $val, $unit : $ts\n";
        return array ($val, $unit, $ts);
    }

    public function getValues ($path) {
        $val = $this->getStateFromArray ($path,'value');
        //print "value = $val, $unit : $ts\n";
        return ($val);
    }
}

define("SP_TYPE_HOURLY", "hourly");
define("SP_TYPE_DAILY", "daily");

class solarPrognose extends generalHelpers {
    public $requestStatus;
    private $statusCodes = array (0   => "OK",
                                 -2  => "INVALID ACCESS TOKEN",
                                 -3  => "MISSING PARAMETER ACCESS TOKEN",
                                 -4  => "EMPTY PARAMETER ACCESS TOKEN",
                                 -5  => "INVALID TYPE",
                                 -6  => "MISSING TYPE",
                                 -7  => "INVALID ID",
                                 -8  => "ACCESS DENIED",
                                 -9  => "INVALID ITEM",
                                 -10 => "INVALID TOKEN",
                                 -11 => "NO SOLAR DATA AVAILABLE",
                                 -12 => "NO DATA",
                                 -13 => "INTERNAL ERROR",
                                 -14 => "UNKNOWN ERROR",
                                 -15 => "INVALID START DAY",
                                 -16 => "INVALID END DAY",
                                 -17 => "INVALID DAY",
                                 -18 => "INVALID WEATHER SERVICE ID",
                                 -19 => "DAILY QUOTA EXCEEDED",
                                 -20 => "INVALID OR MISSING ELEMENT ITEM",
                                 -21 => "NO PARAMETER",
                                 -22 => "INVALID PERIOD",
                                 -23 => "INVALID START EPOCH TIME",
                                 -24 => "INVALID END EPOCH TIME",
                                 -25 => "ACCESS DENIED TO ITEM DUE TO LIMIT",
                                 -26 => "NO CLEARSKY VALUES",
                                 -27 => "MISSING INPUT ID AND TOKEN",
                                 -28 => "INVALID ALGORITHM",
                                 -29 => "FAILED TO LOAD WEATHER LOCATION ITEM"
    );

    private $parameters = array();
    /*
                        array ("access-token" => "",
                                 "project" => "",
                                 "item" => "",
                                 "id" => "",
                                 "type" => "",
                                 "_format" => "json",
                                 "algorithm" => "",
                                 "day" => "",
                                 "start_epoch_time" => "",
                                 "end_epoch_time" => "",
                                 "start_day" => "",
                                 "end_day" => "",
                                 "snomminixml" => "",
                                 );
    */


    private $nextExecutionTime;
    private $datalinename;
    private $weather_source_text;
    

    private $solarData;
        
    public $mainUrl = "http://www.solarprognose.de/web/solarprediction/api/v1";
    public $deviceMainStatusUrl;

    public $lastError; // used if 'error' is returned

    private $lastResponse;
    
    public function __construct ($token) {
        $this->parameters = array();

        $this->parameters["access-token"] = $token;
        $this->requestStatus = 0;
        $this->datalinename = '';
        $this->weather_source_text = '';
        $this->nextExecutionTime = 0;
        
        $this->solarData = array();
    }

    public function getHRStatusCode () {
        // get Human Readable status code
        return ($this->getStateFromArray ($this->statusCodes, $this->requestStatus));
    }
    
    public function setAlgorithm ($id) {
        switch ($id) {
        case 1:
            $algo = 'mosmix';
            break;
        case 2:
            $algo = 'own-v1';
            break;
        case 3:
            $algo = 'clearsky';
            break;
        default:
            $algo = '';
            break;
        }
        $this->parameters["algorithm"] = $algo;
    }

    public function inRange ($day) {
        if ( $day >= -2 && $day <=6 )
            return (1);
        return (0);
    }
    
    public function setType ($type) {
        $this->parameters["type"] = $type;
    }

    public function setItemID ($item, $id) {
        $this->parameters["item"] = $item;
        $this->parameters["id"] = $id;
    }
    
    public function setDayRange ($start, $end) {
        if ($this->inRange ($start) && $this->inRange ($end)) {
            $this->parameters["START_DAY"] = $start;
            $this->parameters["END_DAY"] = $end;
            
            unset ($this->parameters["START_EPOCH_TIME"]);
            unset ($this->parameters["END_EPOCH_TIME"]);
            unset ($this->parameters["DAY"]);
        }
    }

    public function setDay ($days) {
        unset ($this->parameters["START_EPOCH_TIME"]);
        unset ($this->parameters["END_EPOCH_TIME"]);
        unset ($this->parameters["START_DAY"]);
        unset ($this->parameters["END_DAY"]);

        if ($this->inRange($days)) {
            $this->parameters["DAY"] = $days;
        }
    }
    
    public function setEpochDayRange ($start, $end) {
        unset ($this->parameters["START_DAY"]);
        unset ($this->parameters["END_DAY"]);
        unset ($this->parameters["DAY"]);
        
        $this->parameters["START_EPOCH_TIME"] = $start;
        $this->parameters["END_EPOCH_TIME"] = $end;
    }
    
    public function requestData ($method = 'GET') {
        $params = array ();
        
        foreach ($this->parameters as $k=>$v) {
            $params[$k] = $v;
        }

        $url = $this->mainUrl."?".http_build_query ($params);
        
        $ch = curl_init($url);

        $this->external_dbg (3, "Url=".$url);
        
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_CUSTOMREQUEST => $method,
        )
        );

        $resp = curl_exec($ch);
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->external_dbg (3, "solarPrognose:Resp ($httpCode)".$resp);

        $this->lastResponse = $resp;
        if ($httpCode == 200) {
            $this->lastResponse = $resp;
            return (true);
        } else {
            $this->lastError = $resp;
            $this->external_dbg (0, "solarPrognose:Resp ($httpCode)".$resp);
            // solarPrognose:Resp (401){"name":"Unauthorized","message":"Your request was made with invalid credentials.","code":0,"status":401}
            // 401 = unauthorized
            return (false);
        }
    }

    public function parseData () {
        $resp = json_decode ($this->lastResponse,true);

        print_r ($resp);
        $this->requestStatus = $this->getStateFromArray ($resp, 'status');
        $this->nextExecutionTime = $this->getStateFromArray ($resp, 'preferredNextApiRequestAt','epochTimeUtc');
        $this->datalinename = $this->getStateFromArray ($resp, 'datalinename');
        $this->weather_source_text = $this->getStateFromArray ($resp, 'weather_source_text');
        
        $this->solarData = array();

        if ($this->requestStatus === 0) {
            $solarData = $resp['data'];

            if (empty($this->parameters["type"]) || $this->parameters["type"] === SP_TYPE_HOURLY) {
                $this->solarData = $solarData;
            } else {
                // daily data convert to same structure for easier data handling
                foreach ($solarData as $k => $v) {
                    $datenew = DateTime::createFromFormat("Ymd", $k);
                    $datenew->setTime (5,0,0,0);
                    $this->solarData[$datenew->getTimestamp()] = array ($v, $v);;
                }
            }
            
            return (true);
        } else {
            // error
            $this->external_dbg (0, "solarPrognose:API Error ($this->requestStatus) = ".$this->lastResponse);
        }
        
        return (false);
    }

    public function getNextExecutionTime () {
        return ($this->nextExecutionTime);
    }

    public function getDataLineName() {
        return ($this->datalinename);
    }

    public function getWeatherSource() {
        return ($this->weather_source_text);
    }
    public function getData() {
        if ($this->parseData() ) {
            return ($this->solarData);
            return (true);
        }
        return (false);
    }
}

?>
