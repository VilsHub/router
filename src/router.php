<?php
namespace vilshub\router;
use vilshub\helpers\message;
use \Exception;
use vilshub\helpers\get;
use vilshub\helpers\style;
use vilshub\helpers\textProcessor;
use vilshub\validator\validator;
/**
 *
 */
  /**
  *
  */
  class router
  {
    private $displayBase        = null;
    private $error404URL        = null;
    private $displayFile        = null;
    private $callBack           = null;
    private $data               = null;
    private $defaultBaseFile    = "index";
    private $defaultRouteMapDir = "/root";
    private $id                 = null;
    private $params             = [];
    private $routesMapDir       = null;
    private $hasDynamicRoute    = false;
    private $strictDisplay      = false;
    private $directories        = false;
    private $maskURL            = "";
    private $errorMode          = false;
    private $maintenanceURL     = null;
    private $maintenanceMode    = false;
    private $host               = null;


    private function setFile($file){
      //Set target file
      if($this->callBack != null){
        $callBackName = $this->callBack;
        $callBackRes = $callBackName($this);
        if($callBackRes){
          $this->displayFile = $file;
        }
      }else{
        $this->data = null;
        $this->displayFile = $file;
      }
    }
    private function checkDir($dir, $msg=null){
        if(is_dir($dir)){
          return true;
        }else{
          if($msg == null){
            trigger_error(message::write("error", " the directory ".style::color($dir, "black")." does not exist"));
          }else{
            trigger_error($msg);
          }
        };
    }
    private function checkRouteType($route){
      if(strpos($route, "{") === FALSE){
        return "static";
      }else{
        return "dynamic";
      }
    }
    private function checkDefaultBaseFile($baseDir){
      $filePath = $baseDir."/".$this->defaultBaseFile.".php";
      if(file_exists($filePath)){
        $this->setFile($filePath);
        return true;
      }else{
        return false;
      }
    }
    private function matchPattern($pattern, $index, $urlSegments){
      $parsedPattern = trim($pattern, "{}");
      return $parsedPattern;
    }
    private function setData($id){
      $this->id = $id;
      $queryPart = \parse_url($id);
      if(isset($queryPart["query"])){
        parse_str($queryPart["query"], $this->params);
      }
    }
    private function getDynamicSegment($route, $urlSegment){
      $result = [];$status=false;
      $routeSegments = explode("/",trim($route, "/"));
      $totalRouteSegment = count($routeSegments);
      $totalURLSegment = count($urlSegment);
      $extraSegment = [];
      if($totalURLSegment > $totalRouteSegment){
        $extraSegment[] = $urlSegment[$totalRouteSegment];
        if($totalURLSegment == $totalRouteSegment+2){
          $extraSegment[] = $urlSegment[$totalRouteSegment+1];
        }
      }
      foreach ($routeSegments as $key => $value) {
        if(strpos($value, "{") !== FALSE){
          $result[] = $key;
        }else{
          continue;
        }
      }
      if($totalRouteSegment > 1){
        $tempSegment = $routeSegments;
        if($totalRouteSegment == 2){
          unset($tempSegment[$totalRouteSegment-1]);
          $key1 = "/".implode("/", $tempSegment);
          $key2 = null;
        }else{
          unset($tempSegment[$totalRouteSegment-1]);
          $key1 = "/".implode("/", $tempSegment);
          unset($tempSegment[$totalRouteSegment-2]);
          $key2 = "/".implode("/", $tempSegment);;
        }
      }else{
        $key1 = null;
        $key2 = null;
      }
      return [
        "indexes"           => $result,
        "segments"          => $routeSegments,
        "segmentSize"       => $totalRouteSegment,
        "route"             => $route,
        "exactLength"       => $totalURLSegment == $totalRouteSegment,
        "extraSegments"     => $extraSegment,
        "totalExtraSegments"=> count($extraSegment)
      ];
    }
    private function checkForDynamicRoute($url, $displayBase){
      $totalURLSegments = count($url);
      foreach ($this->routesMapDir as $key => $value) {
        $routeType = $this->checkRouteType($key);
        if($routeType == "static") continue;
        
        $dynamicSegment = $this->getDynamicSegment($key, $url);
        if($totalURLSegments < $dynamicSegment["segmentSize"]) continue;
        if($totalURLSegments > ($dynamicSegment["segmentSize"]+2)) continue;

        //get route dynmic pattern
        $totalIndexes = count($dynamicSegment["indexes"]);
        $routeBase = $displayBase.$value;
        $matched = false;
        for ($i=0; $i <$totalIndexes ; $i++) { 
          $pattern = $dynamicSegment["segments"][$dynamicSegment["indexes"][$i]];
          $targetURLSegment = $url[$dynamicSegment["indexes"][$i]];
          $parsedPattern = "/".$this->matchPattern($pattern, $dynamicSegment["indexes"][$i], $url)."/";
          if(preg_match($parsedPattern, $targetURLSegment)){
            $matched = true;
          }else{
            $matched = false;
            break;
          };
        }

        if($matched){
          if($totalURLSegments > 1){
            $mainRoute = $dynamicSegment["route"];
            if($dynamicSegment["exactLength"] && isset($this->routesMapDir[$mainRoute])){//Route is defined
              //Set default display file
              $routeBase = $displayBase.$this->routesMapDir[$mainRoute];
              $this->checkDefaultBaseFile($routeBase);
            }else if (!$dynamicSegment["exactLength"]){
              if($dynamicSegment["totalExtraSegments"] == 1){
                //compute the 1st segment //segment could be file or data
                $extraSegment1 = $dynamicSegment["extraSegments"][0];
                $this->checkAndDisplay($extraSegment1, $extraSegment1, $routeBase, true );
              }else{
                //compute the 1st segment //segment1 must be a file, and segment could be file or data
                $extraSegment1 = $dynamicSegment["extraSegments"][0];
                $extraSegment2 = $dynamicSegment["extraSegments"][1];
                $this->checkTargetFile($extraSegment1, $extraSegment2, $routeBase, true);
              }
            }
          }else{
            $this->checkAndDisplay(null, $url[0], $routeBase, true);
          }
        }else{
          continue;
        }
      }
    }
    private function checkTargetFile($fileName=null, $id=null, $displayBase, $useDefaultRouteMapDir=false){
      $defaultRouteMapDir = $useDefaultRouteMapDir?trim($this->defaultRouteMapDir, "/\\")."/":"";
      $filePath           = $displayBase."/".$defaultRouteMapDir.$fileName.".php";
      $idData             = $displayBase."/".$id.".php";
      $default            = $displayBase."/".$defaultRouteMapDir.$this->defaultBaseFile.".php";
      if($id == null){// check for only file
        if(file_exists($filePath)){
          $this->setFile($filePath);
          return true;
        }else{
          return false;
        }
      }else{//check for both file and data
        if($fileName != null){ //execute check for both file and data
          if(file_exists($filePath)){//file exist
            $lastSegmentAsFile = file_exists($idData);
            if($fileName != $id && $lastSegmentAsFile){//set last segment as file
              $this->setFile($idData);
              return true;
            }else if($fileName != $id && !$lastSegmentAsFile){//set 1st segment as file, and last as data
              $this->setFile($filePath);      
              $this->setData($id);
              return true;
            }else if($fileName == $id && $lastSegmentAsFile){//Add any segment as file and set no data
              $this->setFile($filePath); 
              return true;
            }
          }else{
            $this->setFile($default);
            $this->setData($id);
            return true;
          }
        }else{ //execute check for data only
          if(!$this->strictDisplay){
            $this->setFile($default);
            $this->setData($id);
            return true;
          }else{
            return false;
          }
        }
      }
    }
    private function checkAndDisplay($file, $data, $base, $useDefaultRouteMapDir){
      if(!$this->checkTargetFile($file, $data, $base, $useDefaultRouteMapDir)){
        $this->setData($data);
        $this->checkDefaultBaseFile($base);
      };
    }
    private function setHost(){
      $protocol = explode("/", $_SERVER["SERVER_PROTOCOL"])[0];
      $secureStatus = $_SERVER["SERVER_PORT"] == 43 ? "s://":"://";
      $this->host = $protocol.$secureStatus.$_SERVER["HTTP_HOST"];
    }
    private function redirect($url){
      header("Location: {$url}");
    }
    public function __set($propertyName, $value){
      switch ($propertyName) {
        case 'error404URL':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of URL e.g. ".style::color("'/error/e404'", "blue");
          validator::validateString($value, message::write("error", $msg));
          $this->error404URL = $value;
          break;
        case 'maintenanceURL':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of URL e.g. ".style::color("'/maintenance'", "blue");
          validator::validateString($value, message::write("error", $msg));
          $this->maintenanceURL = $value;
          break;
        case 'defaultBaseFile':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of file name e.g ".style::color("index.php", "blue");
          validator::validateString($value, message::write("error", $msg));
          $this->defaultBaseFile = rtrim($value, ".php");
          break;
        case 'callBack':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string";
          validator::validateString($value, message::write("error", $msg));
          $this->callBack = $value;
          break;
        case 'data':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be an array";
          validator::validateArray($value, message::write("error", $msg));
          $this->data = $value;
          break;
        case 'routesMapDir':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be an array";
          validator::validateArray($value, message::write("error", $msg));
          $this->routesMapDir = $value;
          break;
        case 'defaultRouteMapDir':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string";
          validator::validateString($value, message::write("error", $msg));
          $this->defaultRouteMapDir = $value;
          break;
        case 'hasDynamicRoute':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a boolean";
          validator::validateBoolean($value, message::write("error", $msg));
          $this->hasDynamicRoute = $value;
          break;
        case 'maintenanceMode':
          $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a boolean";
          validator::validateBoolean($value, message::write("error", $msg));
          $this->maintenanceMode = $value;
          break;
        default:
          trigger_error(message::write("error", " unknown property ".style::color(__CLASS__."->", "black").style::color($propertyName, "red")), E_USER_NOTICE) ;
          break;
      }
    }
    public function __get($propertyName){
      switch ($propertyName) {
        case 'id':
          return $this->id;
          break;
        case 'params':
          return $this->params;
          break;
        case 'directories':
          return $this->directories;
          break;
      }
    }
    public function listen($displayBase, $strict=false){
        $this->setHost();
        if ($this->maintenanceMode) $this->redirect($this->host.$this->maintenanceURL);
        ob_start();
        $this->strictDisplay = $strict;
        if(isset($_SESSION["errorMode"])){
          if($_SESSION["errorMode"]  == false){
            $this->maskURL = $this->host.$_SERVER["REQUEST_URI"];
            $_SESSION["errorMode"] = true;
          }
        }
        
        //validate argument
        $msg =  "Invalid argument type, ".style::color(__CLASS__."->", "black").style::color("display()", "black")." method argument must be a string";
        validator::validateString($displayBase, message::write("error", $msg));
        if(!isset($this->routesMapDir["/"])){
          trigger_error(message::write("error", "At least, a root '/' route must be defined, define using the ".style::color(__CLASS__."->", "black").style::color("routesMap", "black") . " propety. Example ".style::color("routerObj->routesMap = ['/' => '/root']", "blue") ));
        }else{
          //check if directory exist
          if(!$this->checkDir($displayBase.$this->routesMapDir["/"], message::write("error", "The root '/' route display base ".style::color("'".$this->routesMapDir["/"]."'", "black")." specified, does not exist relative to the block display base ".style::color($displayBase."/", "black"))));
        }

        $url = $_SERVER["REQUEST_URI"];
        if($url == "/"){//root        
          //check for default base file
          $rootBase = $displayBase.$this->routesMapDir["/"];
          if(!$this->checkDefaultBaseFile($rootBase)){
            trigger_error($msg);
          }
        }else{
          $trimUrl  = trim($url, "/");
          $parsedURL = rtrim($url, "/");
          $urlFragments = explode("/", $trimUrl);
          $urlTotalSegments = count($urlFragments);
          if(isset($this->routesMapDir[$parsedURL])){
            $routeBase = $displayBase.$this->routesMapDir[$parsedURL];
            $this->checkDefaultBaseFile($routeBase);
          }else{
            if($urlTotalSegments == 1){
              $rootBase = $displayBase.$this->routesMapDir["/"];
              if(!$this->checkAndDisplay($trimUrl, $trimUrl, $rootBase, false)){
                if($this->hasDynamicRoute){
                  //Check if dynamic route exist 
                  $this->checkForDynamicRoute($urlFragments, $displayBase);
                }
              }
            }else if($urlTotalSegments == 2){
                if(isset($this->routesMapDir["/".$urlFragments[0]])){
                  $routeBase = $displayBase.$this->routesMapDir["/".$urlFragments[0]];
                  $this->checkAndDisplay($urlFragments[1], $urlFragments[1], $routeBase, false);
                }else{
                  if($this->hasDynamicRoute){
                    //Check if dynamic route exist  
                    $this->checkForDynamicRoute($urlFragments, $displayBase);
                    if($this->displayFile == null)$this->checkAndDisplay($urlFragments[0], $urlFragments[1], $displayBase, true);
                  }else{
                    $this->checkAndDisplay($urlFragments[0], $urlFragments[1], $displayBase, true);
                  }
                }
            }else if($urlTotalSegments > 2){
              $tempURL =  $urlFragments;
              unset($tempURL[$urlTotalSegments-1]); //remove last segment
              $altRout1 = "/".implode("/",$tempURL);
              if(isset($this->routesMapDir[$altRout1])){
                $routeBase = $displayBase.$this->routesMapDir[$altRout1];
                $this->checkAndDisplay($urlFragments[$urlTotalSegments-1], $urlFragments[$urlTotalSegments-1], $routeBase, false);
              }else{
                unset($tempURL[$urlTotalSegments-1], $tempURL[$urlTotalSegments-2]); //remove last 2 segment
                $altRout2 = "/".implode("/",$tempURL);
                if(isset($this->routesMapDir[$altRout2])){
                  $routeBase = $displayBase.$this->routesMapDir[$altRout2];
                  $this->checkTargetFile($urlFragments[$urlTotalSegments-2], $urlFragments[$urlTotalSegments-1], $routeBase, false);  
                }else{
                  if($this->hasDynamicRoute){
                    //Check if dynamic route exist 
                    $this->checkForDynamicRoute($urlFragments, $displayBase);
                  }
                }
              }
            }          
          }
        }

       // Set 404 as display, if no file is found
        if($this->displayFile == null){
          if($this->error404URL == null) $this->displayFile = dirname(__DIR__, 1)."/files/404.php";
          $this->data["router"] = &$this;
          extract($this->data);
          $this->showError(404);
        }
        ob_flush();
    }
    public function showError($id){
      $msg =  " Invalid argument value, ".style::color(__CLASS__."->", "black").style::color("showError(x)", "black")." method argument must be an integer";
      validator::validateInteger($id, message::write("error", $msg));
      if($id == 404)$this->redirect($this->host.$this->error404URL);
    }
    public function strictMode(){
      if($this->id != null){
          $this->showError(404);
      }
    }
    public function setPageTitle($value){
      $msg =  " Invalid argument value, ".style::color(__CLASS__."->", "black").style::color("setPageTitle(x)", "black")." method argument must be a string";
      validator::validateString($value, message::write("error", $msg));
      echo "<script type = 'text/javascript' >document.querySelector('title').innerHTML = '{$value}' </script>";
    }
    public function showContent(){
      if($this->strictDisplay) $this->strictMode();
      extract($GLOBALS);
      include($this->displayFile);
      $scriptContent = "window.history.pushState(null, null, '{$this->maskURL}')";
      echo "<script>{$scriptContent}</script>";
      $_SESSION["errorMode"] = false;
    }
    static function includeBlockFragment($files){
      function fragmentCheck($trimUrl,$key){
        $keysSegments = explode("|",$key);
        $status = null;
        $totalKeysSegments = count($keysSegments);
        $indexes = [];
        if($totalKeysSegments == 2){
          $indexes[0] = array_search($keysSegments[0], $trimUrl);//segment exist
          $indexes[1] = array_search($keysSegments[1], $trimUrl);//segment exist
          if($indexes[1] > $indexes[0]){
            $status = true;
          }else{
            $status = false;
          }
        }else{
          $index = array_search($keysSegments[0], $trimUrl, true);
          if($index !== false){//segment exist 
            $status = true;
          }else{
            $status = false;
          }
        }
        return $status;
      }
      function includeFile($file){
        if(file_exists($file)){
          extract($GLOBALS);
          include($file); 
          return true;
        }else{
          trigger_error(message::write("error", " The target file ".style::color($file, "black")." not found for auto inclusion "));
        }
      }
      validator::validateArray($files, message::write("error", "router::includeBlockFragment() static method, expects an array as argument"));
      $url = $_SERVER["REQUEST_URI"];
      $trimUrl  = explode("/", trim($url, "/"));
      foreach ($files as $key => $value) {
        if($url == "/" && $key == "/"){
          if(includeFile($value)) break;
        }else{
          if(fragmentCheck($trimUrl, $key)){
            if(includeFile($value)) break;
          }
        }
      }  
    }
    static public function block($route, $uri){
      return preg_match("/".str_replace("/", "\/", $route)."[a-zA-Z0-9\?\.\-\_\/]{0,}$/", $uri);
    }
  }
?>