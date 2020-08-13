<?php
namespace vilshub\router;
use vilshub\helpers\message;
use \Exception;
use vilshub\helpers\get;
use vilshub\helpers\style;
use vilshub\helpers\textProcessor;
use vilshub\validator\validate;
/**
 *
 */
  /**
  *
  */
  class router
  {
  private $displayBase     = null;
  private $error404URL     = null;
  private $displayFile     = null;
  private $routes          = null;
  private $callBack        = null;
  private $data            = null;
  private $baseRoute       = null;
  private $defaultBaseFile = "index.php";
  private $id              = null;
  private function checkDir($dir, $msg=null){
      if(is_dir($dir)){
        return true;
      }else{
        if($msg == null){
          die(message::write("error", " the directory ".style::color($dir, "black")." does not exist"));
        }else{
          die($msg);
        }
      };
  }
  private function getBaseFile($url, $route, $dirMsg){
    $rootURL = explode("/", str_replace($route, "", $url)); //strip out base route
    $length  = count($rootURL);
    if($length > 2){//unknown route
      return false;
    }else{
      $baseDir  = $this->displayBase[$route];
      $file     = $baseDir."/".$rootURL[$length-1].".php";
      $this->checkDir($baseDir, $dirMsg);
      
      if(file_exists($file)){
        if(array_key_exists($route, $this->callBacks)){
          $msg = message::write("error", " The route '".style::color($route, "black")."' callback ".style::color($this->callBacks[$route]."()", "blue")." does not exist");
          validate::functionVariable($this->callBacks[$route], $msg);
          $callBackRes = $this->callBacks[$route]($this);
          if($callBackRes == true){
            $this->displayFile = $file;
          }
        }else{
          $this->data = null;
          $this->displayFile = $file;
        }
        return true;
      }else{
        return false;
      };
    }
  }
  private function checkDefaultBaseFile($baseDir){
    $filePath = $baseDir."/".$this->defaultBaseFile;
    if(file_exists($filePath)){
      if($this->callBack != null){
        $callBackName = $this->callBack;
        $callBackRes = $callBackName($this);
        if($callBackRes){
          $this->displayFile = $filePath;
        }
      }else{
        $this->data = null;
        $this->displayFile = $filePath;
      }
      return true;
    }else{
      return false;
    }
  }
  private function checkTargetFile($fileName, $id=null, $displayBase){
    $targetFile = $fileName.".php";
    $filePath = $displayBase."/".$targetFile;
    if(file_exists($filePath)){
      $this->id = $id;
      //Set target file
      if($this->callBack != null){
        $callBackName = $this->callBack;
        $callBackRes = $callBackName($this);
        if($callBackRes){
          $this->displayFile = $filePath;
        }
      }else{
        $this->data = null;
        $this->displayFile = $filePath;
      }
      
      return true;
    }else{
      return false;
    }
  }
  public function __set($propertyName, $value){
    switch ($propertyName) {
      case 'error404URL':
        $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of URL e.g. ".style::color("'/error/e404'", "blue");
        validate::stringVariable($value, message::write("error", $msg));
        $this->error404URL = $value;
        break;
      case 'baseRoute':
        $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of base route";
        validate::stringVariable($value, message::write("error", $msg));
        $this->baseRoute = $value;
        break;
      case 'defaultBaseFile':
        $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be a string of file name e.g ".style::color("index.php", "blue");
        validate::stringVariable($value, message::write("error", $msg));
        $this->defaultBaseFile = $value;
        break;
      case 'callBack':
        $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be an array";
        validate::stringVariable($value, message::write("error", $msg));
        $this->callBack = $value;
        break;
      case 'data':
        $msg =  " Invalid property value, ".style::color(__CLASS__."->", "black").style::color($propertyName, "black")." value must be an array";
        validate::arrayVariable($value, message::write("error", $msg));
        $this->data = $value;
        break;
      default:
        die(message::write("error", " unknown property ".style::color(__CLASS__."->", "black").style::color($propertyName, "red")));
        break;
    }
  }
  public function __get($propertyName){
    switch ($propertyName) {
      case 'id':
        return $this->id;
        break;
      }
  }
  public function displayContent($displayBase){
      //validate argument
      $msg =  "Invalid argument type, ".style::color(__CLASS__."->", "black").style::color("displayContent()", "black")." method argument must be a string";
      validate::stringVariable($displayBase, message::write("error", $msg));

      $url = $_SERVER["REQUEST_URI"];
      if($url == "/"){//root
        $this->checkDir($displayBase);
        
        //check for default base
        if(!$this->checkDefaultBaseFile($displayBase)){
          echo $msg;
        }
      }else{
        $trimUrl  = rtrim(ltrim($url, $this->baseRoute), "/");
        $urlFragments = explode("/", $trimUrl);
        $totalSegments = count($urlFragments);
        if($totalSegments <= 2){
          //Check if ID type, file or variable
          if ($totalSegments == 2){
            if(!$this->checkTargetFile($urlFragments[1], null, $displayBase)){
              $id = $totalSegments == 1?null:$urlFragments[1];
              $this->checkTargetFile($urlFragments[0], $id, $displayBase);
            }
          }else{
            $this->checkTargetFile($urlFragments[0], null, $displayBase);
          }
        }
      }

      //Display approriate file
      if($this->displayFile == null){
        if($this->error404URL == null){
          $style = "width:98%; height:100%; margin:0 auto; text-align:center; padding-top: 25%; box-sizing: border-box;";
          echo "<div style='{$style}'>404 | File not found</div>";
        }else{
          header("location: {$this->error404URL}");
        } 
      }else{
        if($this->data != null){
          $this->data["router"] = $this;
          extract($this->data);
        }
        include($this->displayFile);
      }
  }
  public function showError($id){
    if($id == 404){
      header("location: {$this->error404URL}");
    }
  }
  static function include($files){
    validate::arrayVariable($files, message::write("error", "router::include() static method, expects an array as argument"));
    function tryFileInclude($key, $files){
      if(array_key_exists($key, $files)){
        $targetFile = $files[$key];
        if(file_exists($targetFile)){
          include($targetFile);
        }else{
          die(message::write("error", " The target file ".style::color($targetFile, "black")." not found for auto inclusion "));
        }
      }
    }
    $url = $_SERVER["REQUEST_URI"];
    if(count($files) > 0){
      if($url == "/"){
        //get file
        tryFileInclude("/", $files);
      }else{
        $fragments = explode("/", trim($url, "/"));
        $total = count($fragments);
        if($total <= 2){
          if($total == 1){
            $key = $fragments[0];
            if(isset($files[$key])){
              tryFileInclude($key, $files);
            }else{
              tryFileInclude("/", $files);
            }
          }else{
            $key = $fragments[1];
            if(isset($files[$key])){
              tryFileInclude($key, $files);
            }else{
              $key = $fragments[0];
              if(isset($files[$key])){
                tryFileInclude($key, $files);
              }else{
                tryFileInclude("/", $files);
              }
            }
          }
        }
      }
    }
  }
  }
?>