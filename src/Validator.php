<?php
  namespace vilshub\validator;
  use vilshub\helpers\Message;
  use \Exception;
  use \FileInfo;
  use vilshub\helpers\Get;
  use vilshub\helpers\Style;
  use vilshub\helpers\TextProcessor;
  /**
   *
   */
  class Validator
  {
    public function __get($propertyName){
      switch ($propertyName) {
        case 'status':
          if(count($this->errorLog["log"]) > 0){ //has error
            return false;
          }else{//no error
            return true;
          }
          break;
        case 'errors':
          return json_encode($this->errorLog);
          break;
        default:
          trigger_error(" unknown property ".Style::color(__CLASS__."->", "black").Style::color($propertyName, "red"), E_USER_NOTICE) ;
          break;
      }
    }
    private $supportedRules = [
      "required"  => 1, 
      "minLength" => 1, 
      "maxLength" => 1, 
      "alpha"     => 1, 
      "integer"   => 1, 
      "alphaNum"  => 1, 
      "float"     => 1, 
      "ext"       => 1,// done. file name extention
      "mimeExt"   => 1,//done encode file extension
      "minSize"   => 1,
      "maxSize"   => 1,
      "callBack"  => 1, 
      "email"     => 1, 
      "file"      => 1,
      "notIn"     => 1, 
      "trim"      => 1,
      "noSpace"   => 1,
      "fullName"  => 1
    ];
    private $errorLog = [
      "status"=>false,
      "log"=>[]
    ];
    private function getVal($rulesInUse, $key){
      return $rulesInUse[$key] == 1 ? "" : ":".$rulesInUse[$key];
    }
    private function getMessageDetails($msg){
      $ids = [
          "e" => "error",
          "w" => "warning",
          "s" => "success"
      ];

      $msplit = explode(":", $msg);
      if (count($msplit) == 2) {
          $key = strtolower($msplit[0]);
          if (array_key_exists($key, $ids)) { //Not Found
              return ["error", $msplit[1]];
          } else { //found
              return [$ids[$key], $msplit[1]];
          }
      } else {
          return ["error", $msplit[0]];
      }
    }
    private function checkRule($rule, &$rulesInUse, $sub, $rules){
      try {
        if ($sub == null) { //String and no sub
            if (array_key_exists($rule[0], $this->supportedRules)) {
                $rule[0] == "callBack"? $rulesInUse[$rule[0]] = $rules[$rule[0]] : $rulesInUse[$rule[0]] = 1;
            } else {
                throw new Exception("This rule " . Style::color($rule[0] , "black") . " is not supported");
            }
        } else if ($sub != null) { //string and has sub
            $fullKey = implode(":", $rule);
            if (array_key_exists($rule[0], $this->supportedRules)) {
                $rulesInUse[$rule[0]] = $rule[1];
            } else {
                throw new Exception("This rule " . Style::color($fullKey , "black") . " is not supported");
            }
        }
      } catch (\Exception $e) {
        trigger_error(Get::staticMethod(__CLASS__, __FUNCTION__). $e->getMessage());
      }
    }
    private function minimumLength($data, $min){
      if (strlen($data) < $min) {
          return false;
      } else {
          return true;
      }
    }
    private function maximumLength($data, $max){
      if (strlen($data) > $max) {
          return false;
      } else {
          return true;
      }
    }
    private function isAlpha($data){
      return preg_match("/^[a-zA-Z]+$/", $data);
    }
    private function logError($name, $message, $errorLocation, $csrfToken=null){
      $this->errorLog["status"] = false;
      $location = $errorLocation??"bottom";
      $this->errorLog["log"][] = ["name"=>$name, "message"=>$message, "location"=>$location];
      if($csrfToken != null){
        $this->errorLog["csrf"] = $csrfToken;
      }
    }
    private function mimeExtensionMatch($data, $extension){
      $fileInfo = new FileInfo($data["tmp_name"]);
      return in_array($extension, $fileInfo->extension());
    }
    private function fileExtensionMatch($data, $extension){
      $fileInfo = pathinfo($data["name"]);
      return strtolower($extension) == strtolower($fileInfo["extension"]);
    }
    public function error($name, $message, $errorLocation=null, $csrfToken=null){
      $this->logError($name, $message, $errorLocation, $csrfToken);
      return json_encode($this->errorLog);
    }
    public function errorsWithCSRF($csrfToken){
      $this->errorLog["csrf"] = $csrfToken;
      return json_encode($this->errorLog);
    }
    public function validateInput($name, $rules, $data, $errorLocation=null){
      /** @param string $name The name of the input element
       *  @param array $rules An associative array defining the validation rules
       *  @param mixType $data The data to be validated
       *  @param string $errorLocation Optional, the location to display the error being sent back (vUX compatible only) value are : left, right and bottom
       * 
       */
      $rulesInUse = [];
      $parseRules = array_keys($rules);
      $totalRules = count($parseRules);

      //build available contraints
      for ($x = 0; $x < $totalRules; $x++) {
          //check for sub value
          $check  = explode(":", $parseRules[$x]);
          $sub    = count($check) == 2 ? "sub" : null;
          $this->checkRule($check, $rulesInUse, $sub, $rules);
      }
      
      if(array_key_exists("required", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "required");
        $length = is_array($data)? $data["name"]:$data;
        if(strlen($length) == 0) {
            $matchMessage = $this->getMessageDetails($rules["required".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("minLength", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "minLength");
        if (!$this->minimumLength($data, ltrim($keyVal, ":"))) {
            $matchMessage = $this->getMessageDetails($rules["minLength".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("maxLength", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "maxLength");
        if (!$this->maximumLength($data, ltrim($keyVal, ":"))) {
            $matchMessage = $this->getMessageDetails($rules["maxLength".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("trim", $rulesInUse)){
        $data = trim($data);
      }
      if(array_key_exists("email", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "email");
        if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
            $matchMessage = $this->getMessageDetails($rules["email".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("alpha", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "alpha");
        if (!$this->isAlpha($data)){
            $matchMessage = $this->getMessageDetails($rules["alpha".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("file", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "file");
        if (!is_file($data["tmp_name"])){
            $matchMessage = $this->getMessageDetails($rules["file".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return;
        }
      }
      if(array_key_exists("mimeExt", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "mimeExt");
        if (!$this->mimeExtensionMatch($data, ltrim($keyVal, ":"))){
            $matchMessage = $this->getMessageDetails($rules["mimeExt".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return ;
        }
      }
      if(array_key_exists("ext", $rulesInUse)){
        $keyVal = $this->getVal($rulesInUse, "ext");
        if (!$this->fileExtensionMatch($data, ltrim($keyVal, ":"))){
            $matchMessage = $this->getMessageDetails($rules["ext".$keyVal]);
            $messageType = $matchMessage[0];
            $messageBody = $matchMessage[1];
            $this->logError($name, $messageBody, $errorLocation);
            return ;
        }
      }
    }
    public static function validateArray($value, $msg){
      if(is_array($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateString($value, $msg){
      if(is_string($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateBoolean($value, $msg){
      if(is_bool($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateFunction($value, $msg){
      if(is_callable($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateInteger($value, $msg){
      if(is_long($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateFile($value, $msg){
      if(file_exists($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
    public static function validateObject($value, $msg){
      if(is_Object($value)){
        return true;
      }else{
        trigger_error($msg);
      }
    }
  }
?>
