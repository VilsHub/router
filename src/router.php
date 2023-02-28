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
    private $params             = [];
    private $routes             = null;
    private $dynamicRoute       = false;
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
    private function displayDefaultBaseFile($baseDir, $data=[]){
      $parsedDefaultBaseFile = explode(".php", $this->defaultBaseFile);
      $filePath = $baseDir."/".$parsedDefaultBaseFile[0].".php";
      $status = false;
      if (count($data) > 0) $this->data = $data;

      if(file_exists($filePath)){
        $this->setFile($filePath);
        $status = true;
      }

      return $status;
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
    private function displayFileExist($displayBase, $fileName){
      $status = [
        "exist"    =>  false,
        "fileName"  =>  null 
      ];

      //unmask extension
      $fileName  = $this->useWordSeperator? $this->unmaskExtenstion($fileName) : $fileName;
      $filePath  = $displayBase."/".$fileName.".php";

      $status["exist"]    = (bool) file_exists($filePath);
      $status["fileName"] = $fileName;
      $status["filePath"] = $filePath;

      return $status;
    }
    private function checkTargetFile($displayBase, $fileName=null, $id=[]){

      //unmask extension
      $fileName           = $this->unmaskExtenstion($fileName);
      $filePath           = $displayBase."/".$fileName.".php";
    
      if($fileName != null){//has file to check
        if(file_exists($filePath)){//file exist
          $this->setFile($filePath);
          $this->setData($id);
          return true;
        }
      }
    }
    private function checkAndDisplay($file, $data, $base, $includeDefault){ 
      if(!$this->checkTargetFile($base, $file, $data)){
        $this->setData($data);
        if ($includeDefault === true) $this->displayDefaultBaseFile($base);
      }
    }

    private function checkForDynamicRoute($displayBase){

      if($this->dynamicRoute){
        foreach ($this->routes as $key => $value) {

          $dynamicSegment = Route::dynamicInfo($key, $this->url);
          if($dynamicSegment["matched"] === TRUE){
            //get display file
            if($dynamicSegment["displayFile"] != null){ //use the defaultBaseFile as display file
              $rootBase = $displayBase.$this->routes[$key];
              $displayFile = ($dynamicSegment["displayFile"] == "default")?$this->defaultBaseFile:$dynamicSegment["displayFile"];
              $this->checkAndDisplay($displayFile,  $dynamicSegment["data"], $rootBase, false, false);
              break;
            }
          }else{
            continue;
          }
        }
      }

    }

    private function isDynamicRoute($route){
      if($this->dynamicRoute){
          $routeType = Route::type($route);
          if($routeType == "static"){
            return false;
          }else{
            return true;
          };

      }else{
        return false;
      }
    }

    private function matchForDynamicRoute($route){
      $dynamicSegment = Route::dynamicInfo($route, $this->url);
      return $dynamicSegment["matched"] === TRUE;
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
        case 'showContent':
          global $app;
          extract(["router" => $app]);
          include($this->displayFile);
          break;
      }
    }

    public function listen($block, $displayBase, $routes=null){
        $url = $_SERVER["REQUEST_URI"];
        
        if ($this->maintenanceMode) {
          if($url != $this->maintenanceURL){
            $this->redirect($this->maintenanceURL);
          }
        };
        $this->routes = $routes == null ? $this->routes:$routes;
        ob_start();

        //validate argument
        $msg =  "Invalid argument type, ".Style::color(__CLASS__."->", "black").Style::color("listen(.x..)", "black")." method argument 2 must be a string";
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
         
          if(!$this->displayDefaultBaseFile($rootBase)) trigger_error("No defaultDisplayFile (".Style::color($this->defaultBaseFile.".php", "black"). ") found for the '/' block home page for)");

        }else{
          $trimUrl          = trim($url, "/");
          $parsedURL        = rtrim($url, "/");

          $urlFragments     = explode("/", $trimUrl);
          $urlTotalSegments = count($urlFragments);

          if(isset($this->routes[$parsedURL])){//has a defined route

            $routeBase = $displayBase.$this->routes[$parsedURL];
            //check if trail exist as file
            $fileInfo = $this->displayFileExist($routeBase, $trimUrl);

            if ($fileInfo["exist"]){
              $this->setFile($fileInfo["filePath"]);
            }else{
              //try and display default file 
              $this->displayDefaultBaseFile($routeBase, []);
            }

          }else{

            if ($urlTotalSegments == 1){ // Check against the / route for display file
              $rootBase = $displayBase.$this->routes["/"];   
              $this->checkTargetFile($rootBase, $urlFragments[0], []);
            }else{
              foreach ($this->routes as $routeDefinition => $routeDisplayBase) {
              
                $parsedRoute          = trim($routeDefinition, "/");
                $routeSegments        = explode("/", $parsedRoute);
                  
                if ($urlTotalSegments == 1){
                  // if route difinition is not dynamic skip
                  if ($this->isDynamicRoute($routeSegments[0])){
                    //try matching th regular expression
                    if ($this->matchForDynamicRoute($routeSegments[0])){
                      $parsedRouteSegment = rtrim($routeSegments[0], ":");
                      $routeBase = $displayBase.$this->routes["/".$parsedRouteSegment];
                      
                      //check and display default file, if true pass dynamic segment as data
                      $this->displayDefaultBaseFile($routeBase, [$urlFragments[0]]);
                    }else{
                      continue;
                    }
                  }else{
                    continue;
                  }
                }else if($urlTotalSegments > 1){
                
                    if ($this->isDynamicRoute($parsedRoute)){

                      // check if against all route trails
                      $this->checkForDynamicRoute($displayBase);
 
                    }else{
                      //one or both segments may be dynamic
                      //extract the last segment from url, and check if the route is defined without the last trail in url
                      $lastURLSegment = $urlFragments[$urlTotalSegments-1];
                      $temp = $urlFragments;
                      unset($temp[$urlTotalSegments-1]);
                      $extractedURL = implode("/", $temp);

                      if(isset($this->routes["/".$extractedURL])){//has a defined route
                        //check if last trail exist as file, then display it, else 404 as route is not dynamic
                        $routeBase = $displayBase.$this->routes["/".$extractedURL];
                        $this->checkTargetFile($routeBase, $lastURLSegment, []);
                      }
                      
                    }

                }
                
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
            trigger_error("No ".Style::color($type, "black")." error file found");
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