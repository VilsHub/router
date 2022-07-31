<?php
namespace vilshub\router;

use Error;
use vilshub\helpers\Get;
use vilshub\helpers\Style;
use vilshub\validator\Validator;
use \Route;

/**
 * 
 */
  class Router
  {
    private $displayBase        = null;
    private $error404File       = null;
    private $error404URL        = null;
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
    private $socketFiles        = [];
    private $url;
    private $config;
    private $maskExtension      = ".php";
    private $wordSeperator      = "-";
    private $useWordSeperator   = false;

    function __construct($routes, $socketFiles, $config){
      $this->url          = $_SERVER["REQUEST_URI"];
      $this->socketFiles  = $socketFiles;
      $this->routes       = $routes;
      $this->config       = $config;
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
            trigger_error(" the directory ".Style::color($dir, "black")." does not exist");
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
      if ($this->useWordSeperator){
        $fileName = str_replace($this->wordSeperator, "", $fileName);
      }
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
      $defaultRouteMapDir = $useDefaultRouteMapDir ? dirname($displayBase)."/".trim($this->defaultRouteMapDir, "/\\")."/":$displayBase;
      //unmask extension
      $fileName           = $this->unmaskExtenstion($fileName);
      $filePath           = $displayBase."/".$fileName.".php";
    
      if($fileName != null){//has file to check
        if(file_exists($filePath)){//file exist
          $this->setFile($filePath);
          $this->setData($id);
          return true;
        }
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
      if(!$this->checkTargetFile($file, $data, $base, $useDefaultRouteMapDir)){
        $this->setData($data);
        if ($includeDefault === true) $this->checkDefaultBaseFile($base);
      }
    }
    private function pathIsValid($route, $uri){
      $totalRouteSegments = count(Route::segments($route));
      $totalURISegments = count(Route::segments($uri));
      return $totalURISegments <= $totalRouteSegments+2;//2 is the last trail, which could either be data or file
    }
    private function validateTrail($routeSegments, $uriSegments){
      $totalRouteSegments   = count($routeSegments);
      $totalURISegments     = count($uriSegments);
      $file                 = null;
      $data                 = "";
        
      $strippedURISegments  = $uriSegments;
      $trailSegments        = array_splice($strippedURISegments, $totalRouteSegments);
      $trailUri             = implode("/", $trailSegments);
      $totalTrailSegments   = count($trailSegments);
      if($totalTrailSegments > 0){
        if(isset($trailSegments[0]))$file = $trailSegments[0];
        if(isset($trailSegments[1]))$data = $trailSegments[1]; 
      }else{
        $file = $uriSegments[$totalURISegments -1];
      }
   
      return [
        "file" => $file,
        "data" => $data
      ];
    }
    private function checkForDynamicRoute($displayBase){
      if($this->dynamicRoute){
        foreach ($this->routes as $key => $value) {
          $routeType = Route::type($key);
          if($routeType == "static") continue;
          if(!$this->pathIsValid($key, $this->url)) continue;

          $dynamicSegment = Route::dynamicInfo($key, $this->url);
          if($dynamicSegment["matched"] === TRUE){
            //check for the last 2 trail and set file and data
            $trailStatus = $this->validateTrail($dynamicSegment["routeSegments"], $dynamicSegment["urlSegments"]);
            if(strlen($trailStatus["data"]) > 0) $dynamicSegment["data"][] = $trailStatus["data"];
            $rootBase = $displayBase.$this->routes[$key];
            $this->checkAndDisplay($trailStatus["file"],  $dynamicSegment["data"], $rootBase, false, false);
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
          Validator::validateString($value,  $msg);
          $this->error404File = $value;
          break;
        case 'error404URL':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of URL e.g. ".Style::color("'/error/e404'", "blue");
          Validator::validateString($value,  $msg);
          $this->error404URL = $value;
          break;
        case 'maintenanceURL':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of URL e.g. ".Style::color("'/maintenance'", "blue");
          Validator::validateString($value,  $msg);
          $this->maintenanceURL = $value;
          break;
        case 'defaultBaseFile':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string of file name e.g ".Style::color("index.php", "blue");
          Validator::validateString($value,  $msg);
          $this->defaultBaseFile = rtrim($value, ".php");
          break;
        case 'defaultRouteMapDir':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string";
          Validator::validateString($value,  $msg);
          $this->defaultRouteMapDir = $value;
          break;
        case 'dynamicRoute':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a boolean";
          Validator::validateBoolean($value,  $msg);
          $this->dynamicRoute = $value;
          break;
        case 'maintenanceMode':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a boolean";
          Validator::validateBoolean($value,  $msg);
          $this->maintenanceMode = $value;
          break;
        case 'maskExtension':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a string";
          Validator::validateString($value,  $msg);
          $this->maskExtension = $value;
          break;
        case 'wordSeperator':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must";
          Validator::validateString($value,  $msg." be a string");
          if (strlen($value) > 1) trigger_error($msg." not be more than a character");
          $this->wordSeperator = $value;
          break;
        case 'useWordSeperator':
          $msg =  " Invalid property value, ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "black")." value must be a boolean";
          Validator::validateBoolean($value,  $msg);
          $this->useWordSeperator = $value;
          break;
        default:
          trigger_error(" unknown property ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "red"), E_USER_NOTICE) ;
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
          global $app;
          extract(["router" => $app]);
          include($this->displayFile);
          break;
      }
    }

    public function listen($block, $displayBase, $routes=null, $strict=false){
        $url = $_SERVER["REQUEST_URI"];
        if ($this->maintenanceMode) {
          if($url != $this->maintenanceURL){
            $this->redirect($this->maintenanceURL);
          }
        };
        $this->routes = $routes == null ? $this->routes:$routes;
        ob_start();
        $this->strictDisplay = $strict;
        
        //validate argument
        $msg =  "Invalid argument type, ".Style::color(__CLASS__."->", "black").Style::color("display()", "black")." method argument must be a string";
        Validator::validateString($displayBase,  $msg);
        if(!file_exists($block)){
          trigger_error("The specified block file ".Style::color("'".$block."'", "black").", does not exist");
        }
        if($routes == null){
          if(!isset($this->routes["/"])){
            trigger_error("At least, a root '/' route must be defined, define using the ".Style::color(__CLASS__."->", "black").Style::color("routesMapDir", "black") . " propety. Example ".Style::color("routerObj->routesMapDir = ['/' => '/root']", "blue"));
          }else{
            //check if directory exist
            if(!$this->checkDir($displayBase.$this->routes["/"],  "The root '/' route display base ".Style::color("'".$this->routes["/"]."'", "black")." specified, does not exist relative to the block display base ".Style::color($displayBase."/", "black")));
          }
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
              //try and display default file 
              $routeBase = $displayBase.$this->routes[$parsedURL];
              $this->checkTargetFile($trimUrl, [], $routeBase, false);
            }else{
              //check if display file exist for it
              if($routes == null){
                $rootBase = $displayBase.$this->routes["/"];
                if(!$this->checkTargetFile($urlFragments[0], [], $rootBase)){
                  //file not exist, check for dynamic segment
                  $this->checkForDynamicRoute($displayBase);
                }
              }
            }
          }else if($urlTotalSegments == 2){
            if(isset($this->routes[$parsedURL])){//has a defined route
              //try and display the last segment as file. If fails, display default display file
              $file = $urlFragments[1];
              $routeBase = $displayBase.$this->routes[$parsedURL];
              if(!$this->checkTargetFile($file, [], $routeBase, false)){
                //try and display default file
                $this->checkTargetFile($this->defaultBaseFile, [], $routeBase, false);
              }
            }else{
              //check sub route
              if(isset($this->routes[$subRoute])){
                //include 2nd segment as file, and pass no data
                $routeBase = $displayBase.$this->routes[$subRoute];
                $this->checkAndDisplay($urlFragments[1], [], $routeBase, false, false);
              }else{    
                // dd($displayBase);
                $this->checkForDynamicRoute($displayBase);
              }
            }

          }else if($urlTotalSegments > 2){
            if(isset($this->routes[$parsedURL])){//has a defined route
              //try and display the last segment as file. If fails, display default display file
              $file = $urlFragments[$urlTotalSegments-1];
              $routeBase = $displayBase.$this->routes[$parsedURL];
              if(!$this->checkTargetFile($file, [], $routeBase, false)){
                //try and display default file
                $this->checkTargetFile($this->defaultBaseFile, [], $routeBase, false);
              }
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
           global $app, $systemApp, $systemAppsHandler;
           extract(["app" => $app, "systemApp" => json_decode(json_encode($systemApp)), "systemAppsHandler" => $systemAppsHandler]);
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

    public function showError($type){
      switch ($type) {
        case '404':
          if(file_exists($this->error404File)){
            require($this->error404File);
            die;
          }else{
            trigger_error("No {$type} error file found");
          }
          break;
        default:
          # code...
          break;
      }
    }

    private function fragmentCheck($trimUrl, $key){
      $keysSegments = explode("|",$key);
      $status  = null;
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
      }else if($totalKeysSegments == 1){
        $index = array_search($keysSegments[0], $trimUrl, true);
        if($index !== false){//segment exist 
          $status = true;
        }else{
          $status = false;
        }
      }
      return $status;
    }

    private function includeFile($file, $obj){
      if(file_exists($file)){
        global $app;
        extract(["app" => $app, "systemApp" => $obj]);
        include($file); 
        return true;
      }else{
        trigger_error(" The target file ".Style::color($file, "black")." not found for auto inclusion ");
      }
    }

    public function plugToSocket($name, $application=null){      
      $trimUrl  = Route::segments($this->url);
      $total    = count($trimUrl);

      //unmask last url segment
      $lastUrlSegment     = $this->unmaskExtenstion($trimUrl[$total-1]);
      $trimUrl[$total-1]  = $lastUrlSegment;


      //Set socket file for either system route or application route

      if($application != null){ //application socket files given
        //Validate $appSocketFiles argument
       
        $appSocketFile = $application->routeFiles->socket;
        $msg           = "The {$application->id} application socket file: ".Style::color($application->routeFiles->socket, "blue");// as argument 2 must be null or an array of socket files";

        Validator::validateFile($appSocketFile, $msg. " passed into does not exist");
       
        $appSocketFiles = require_once($appSocketFile);
        $this->socketFiles = $appSocketFiles;
      }
      
      //Check for global file and plug
      if(isset($this->socketFiles[$name]["*"])){
        $this->includeFile($this->socketFiles[$name]["*"], $application);;
      } 

      if (count($this->socketFiles) > 0){
        //Check and plug other file
        foreach ($this->socketFiles[$name] as $key => $value) {
          if(($this->url == "/" && $key == "/") || ($this->url == "/index".$this->maskExtension && ($key == "/index".$this->maskExtension || $key == "/"))){
            if($this->includeFile($value, $application)) break;
          }else{
            if($this->fragmentCheck($trimUrl, $key)){
              if($this->includeFile($value, $application)) break;
            }
          }
        } 
      }      
    }

    public function validateURLSegments($maxSegment){//if url segment is more than the specified max
      $urlSegments = Route::segments($this->url);
      if($urlSegments > $maxSegment){
        $this->error($this->error404URL);
      }
    }
    public function validateData($data){
      $msg =  " Invalid argument value, ".Style::color(__CLASS__."->", "black").Style::color("validateData(x)", "black")." method argument must be an array";
      Validator::validateArray($data,  $msg);
      
      if(count($this->data) > 0){
        foreach ($this->data as $key => $value) {
          if(!in_array($value, $data)){
            $this->showError("404");
          }
        }
      }

    }
  }
?>