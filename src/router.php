<?php
namespace vilshub\router;
use vilshub\helpers\Message;
use \Exception;
use vilshub\helpers\Get;
use vilshub\helpers\Style;
use vilshub\helpers\textProcessor;
use vilshub\validator\Validator;
use \Route;

/**
 * 
 */
  class Router
  {
    private $displayBase        = null;
    private $error404File       = null;
    private $displayFile        = null;
    private $data               = null;
    private $defaultBaseFile    = "index";
    private $defaultRouteMapDir = "/root";
    private $params             = [];
    private $routes             = null;
    private $dynamicRoute       = false;
    private $strictDisplay      = false;
    private $directories        = false;
    private $maintenanceURL     = null;
    private $maintenanceMode    = false;
    private $host               = null;
    private $socketFiles        = [];
    private $apiID              = "api";
    private $url;
    private $config;
    private $maskExtension      = ".php";

    function __construct(){
      $this->url = $_SERVER["REQUEST_URI"];
    }

    private function setFile($file){
      //Set target file
        $this->displayFile = $file;
    }
    private function checkDir($dir, $msg=null){
        if(is_dir($dir)){
          return true;
        }else{
          if($msg == null){
            trigger_error(Message::write("error", " the directory ".Style::color($dir, "black")." does not exist"));
          }else{
            trigger_error($msg);
          }
        };
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
    private function unmaskExtenstion($fileName){
      return str_replace($this->maskExtension, "", $fileName);
    }
    private function setData($data){
      $total = count($data);
      $this->data = $data;
      if($total>0){
        $queryPart = \parse_url($data[$total-1]);
        if(isset($queryPart["query"])){
          parse_str($queryPart["query"], $this->params);
        }
      }
    }
    private function checkTargetFile($fileName=null, $id=[], $displayBase, $useDefaultRouteMapDir=false){
      $defaultRouteMapDir = $useDefaultRouteMapDir?dirname($displayBase)."/".trim($this->defaultRouteMapDir, "/\\")."/":"";

      //unmask extension
      $fileName           = $this->unmaskExtenstion($fileName);
      $filePath           = $displayBase."/".$fileName.".php";

      if($fileName != null){//has file to check
        if(file_exists($filePath)){//file exist
          $this->setFile($filePath);
          return true;
        }else{
          return $this->checkDefaultBaseFile($defaultRouteMapDir);
        }
        $this->setData($id);
        
      }else{
        if(!$this->strictDisplay){
          $this->setData($id);
          return $this->checkDefaultBaseFile($defaultRouteMapDir);
        }else{
          return false;
        }
      }
    }
    private function checkAndDisplay($file, $data, $base, $useDefaultRouteMapDir, $includeDefault){
      if(!$this->checkTargetFile($file, $data, $base, $useDefaultRouteMapDir, $includeDefault)){
        $this->setData($data);
        if ($includeDefault === true) $this->checkDefaultBaseFile($base);
      };
    }
    private function checkForDynamicRoute($displayBase){
      if($this->dynamicRoute){
        foreach ($this->routes as $key => $value) {
          $routeType = Route::type($key);
          if($routeType == "static") continue;
          $dynamicSegment = Route::dynamicInfo($key, $this->url);
          if($dynamicSegment["matched"] === TRUE){         
            //include the 1st route segment as file, and pass data
            $rootBase = $displayBase.$this->routes[$key];
            $this->checkAndDisplay($dynamicSegment["urlSegments"][0], $dynamicSegment["data"], $rootBase, false, false);
            break;
          }else{
            continue;
          }
        }
      }
    }
    private function stripTrail($urlFragments){
      $total = count($urlFragments);
      unset($urlFragments[$total-1]);
      return "/".implode("/", $urlFragments);
    }
    private function redirect($url){
      header("Location: {$url}");
    }

    public function __set($propertyName, $value){
      switch ($propertyName) {
        case 'error404File':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of URL e.g. ".Style::color("'/error/e404'", "blue");
          Validator::validateString($value, Message::write("error", $msg));
          $this->error404File = $value;
          break;
        case 'maintenanceURL':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of URL e.g. ".Style::color("'/maintenance'", "blue");
          Validator::validateString($value, Message::write("error", $msg));
          $this->maintenanceURL = $value;
          break;
        case 'defaultBaseFile':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of file name e.g ".Style::color("index.php", "blue");
          Validator::validateString($value, Message::write("error", $msg));
          $this->defaultBaseFile = rtrim($value, ".php");
          break;
        case 'routes':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be an array";
          Validator::validateArray($value, Message::write("error", $msg));
          $this->routes = $value;
          break;
        case 'defaultRouteMapDir':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string";
          Validator::validateString($value, Message::write("error", $msg));
          $this->defaultRouteMapDir = $value;
          break;
        case 'dynamicRoute':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a boolean";
          Validator::validateBoolean($value, Message::write("error", $msg));
          $this->dynamicRoute = $value;
          break;
        case 'maintenanceMode':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a boolean";
          Validator::validateBoolean($value, Message::write("error", $msg));
          $this->maintenanceMode = $value;
          break;
        case 'socketFiles':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be an array";
          Validator::validateArray($value, Message::write("error", $msg));
          $this->socketFiles = $value;
          break;
        case 'config':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be an array";
          Validator::validateObject($value, Message::write("error", $msg));
          $this->config = $value;
          break;
        case 'maskExtension':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string";
          Validator::validateString($value, Message::write("error", $msg));
          $this->maskExtension = $value;
          break;
        default:
          trigger_error(Message::write("error", " unknown property ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "red")), E_USER_NOTICE) ;
          break;
      }
    }

    public function __get($propertyName){
      switch ($propertyName) {
        case 'data':
          return $this->data;
          break;
        case 'params':
          return $this->params;
          break;
        case 'directories':
          return $this->directories;
          break;
        case 'showContent':
          extract(["router" => &$this]);
          include($this->displayFile);
          break;
        case 'config':
          return $this->config;
          break;
      }
    }

    public function listen($block, $displayBase, $strict=false){
        $url = $_SERVER["REQUEST_URI"];
        if ($this->maintenanceMode) {
          if($url != $this->maintenanceURL){
            $this->redirect($this->maintenanceURL);
          }
        };
        ob_start();
        $this->strictDisplay = $strict;
        
        //validate argument
        $msg =  "Invalid argument type, ".Style::color(__CLASS__."->", "black").Style::color("display()", "black")." method argument must be a string";
        Validator::validateString($displayBase, Message::write("error", $msg));
        if(!isset($this->routes["/"])){
          trigger_error(Message::write("error", "At least, a root '/' route must be defined, define using the ".Style::color(__CLASS__."->", "black").Style::color("routesMapDir", "black") . " propety. Example ".Style::color("routerObj->routesMapDir = ['/' => '/root']", "blue") ));
        }else{
          //check if directory exist
          if(!$this->checkDir($displayBase.$this->routes["/"], Message::write("error", "The root '/' route display base ".Style::color("'".$this->routes["/"]."'", "black")." specified, does not exist relative to the block display base ".Style::color($displayBase."/", "black"))));
        }
        
        if($url == "/"){//root        
          //check for default base file
          $rootBase = $displayBase.$this->routes["/"];
          if(!$this->checkDefaultBaseFile($rootBase)){
            trigger_error($msg);
          }
        }else{
          $trimUrl          = trim($url, "/");
          $parsedURL        = rtrim($url, "/");

          $urlFragments     = explode("/", $trimUrl);
          $urlTotalSegments = count($urlFragments);
          $subRoute         = $this->stripTrail($urlFragments);

          if($urlTotalSegments == 1){
            if(isset($this->routes[$parsedURL])){//has a defined route
              //try and display file default
              $routeBase = $displayBase.$this->routes[$parsedURL];
              $this->checkTargetFile($trimUrl, [], $routeBase, false);
            }else{
              //file not exist, check for dynamic segment
              $this->checkForDynamicRoute($displayBase);
            }
          }else if($urlTotalSegments == 2){
            if(isset($this->routes[$parsedURL])){//has a defined route
               //try and display default file
               $routeBase = $displayBase.$this->routes[$parsedURL];
               $this->checkTargetFile($this->defaultBaseFile, [], $routeBase, false);
            }else{
              //check sub route
              if(isset($this->routes[$subRoute])){
                //include 2nd segment as file, and pass no data
                $routeBase = $displayBase.$this->routes[$subRoute];
                $this->checkAndDisplay($urlFragments[1], [], $routeBase, false, false);
              }else{
                $this->checkForDynamicRoute($displayBase);
              }
            }
          }else if($urlTotalSegments > 2){
            if(isset($this->routes[$parsedURL])){//has a defined route
              //try and display default file
              $routeBase = $displayBase.$this->routes[$parsedURL];
              $this->checkTargetFile($this->defaultBaseFile, [], $routeBase, false);
            }else{   
              //check sub route
              if(isset($this->routes[$subRoute])){
                //include last segment as file, and pass no data
                $routeBase = $displayBase.$this->routes[$subRoute];
                $this->checkAndDisplay($urlFragments[$urlTotalSegments-1], [], $routeBase, false, false);
              }else{
                $this->checkForDynamicRoute($displayBase);
              }
            }
          }
        }

        if($this->displayFile != null){
           //insert block
           extract(["router" => &$this]);
           require($block);
        }
        ob_flush();
    }

    public function error(){
      if($this->displayFile == null){ //show 404 error
        $this->displayFile = ($this->error404File == null)? $this->displayFile = dirname(__DIR__, 1)."/files/404.php":$this->error404File;
        require($this->displayFile);
      }
    }

    public function setPageTitle($value){
      $msg =  " Invalid argument value, ".Style::color(__CLASS__."->", "black").Style::color("setPageTitle(x)", "black")." method argument must be a string";
      Validator::validateString($value, Message::write("error", $msg));
      echo "<script type = 'text/javascript'>document.querySelector('title').innerHTML = '{$value}' </script>";
    }

    public function plugToSocket($name){
      function fragmentCheck($trimUrl,$key){
        $keysSegments = explode("|",$key);
        $status       = null;
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
      function includeFile($file, $obj){
        if(file_exists($file)){
          extract(["router" => $obj]);
          include($file); 
          return true;
        }else{
          trigger_error(Message::write("error", " The target file ".Style::color($file, "black")." not found for auto inclusion "));
        }
      }
      
      $url      = $_SERVER["REQUEST_URI"];
      $trimUrl  = explode("/", trim($url, "/"));
      $total    = count($trimUrl);
      
      //unmask last url segment
      $lastUrlSegment = $this->unmaskExtenstion($trimUrl[$total-1]);
      $trimUrl[$total-1] = $lastUrlSegment;
      
      foreach ($this->socketFiles[$name] as $key => $value) {
        if(($url == "/" && $key == "/") || ($url == "/index".$this->maskExtension && ($key == "/index".$this->maskExtension || $key == "/"))){
          if(includeFile($value, $this)) break;
        }else{
          if(fragmentCheck($trimUrl, $key)){
            if(includeFile($value, $this)) break;
          }
        }
      }  
    }

    public function route($type){
      return Route::for($type, $this->config->apiId);      
    }
  }
?>