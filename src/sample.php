<?php
$emailRules = ["required"=>"aa", "minLength:5"=>"length too small", "maxLength:10"=>"length too large"];
$ttRules = ["required"=>"you must specify data", "minLength:4"=>"length too small", "email"=>"Please specify an email address"];
$this->validator->validateInput("email", $emailRules, $postData["email"]);
$this->validator->validateInput("tt", $ttRules, "smmms@dd.cc");
// ..
// ..
// ..
if($this->validator->status){
    //.........
}else{
    echo $this->validator->errors;
}  
?>