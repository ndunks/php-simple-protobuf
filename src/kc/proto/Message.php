<?php

namespace kc\proto;
use \kc\proto\serializer\native\Reader;
use \Exception;

/**
* Extends this class to make proto message
*/
abstract class Message
{
	const   RULE_OPTIONAL = 1,
            RULE_REQUIRED = 2,
            RULE_REPEATED = 3,
            RULE_UNKNOWN  = -1;

    const   TYPE_DOUBLE   = 1,
            TYPE_FLOAT    = 2,
            TYPE_INT64    = 3,
            TYPE_UINT64   = 4,
            TYPE_INT32    = 5,
            TYPE_FIXED64  = 6,
            TYPE_FIXED32  = 7,
            TYPE_BOOL     = 8,
            TYPE_STRING   = 9,
            TYPE_GROUP    = 10,
            TYPE_MESSAGE  = 11,
            TYPE_BYTES    = 12,
            TYPE_UINT32   = 13,
            TYPE_ENUM     = 14,
            TYPE_SFIXED32 = 15,
            TYPE_SFIXED64 = 16,
            TYPE_SINT32   = 17,
            TYPE_SINT64   = 18,
            TYPE_UNKNOWN  = -1;
	
    const   PROTO_NAME  = 0,
            PROTO_TYPE  = 1,
            PROTO_RULE  = 2,
            PROTO_PACKED= 3,
            PROTO_CLASS = 4;
    /**
     *  Each sub-class of message must declare constant of FIELDS:
     *  const FIELDS    = [
     *      1 => [ 'name',  self::TYPE_STRING,  self::RULE_REQUIRED,    false,  'string'],
     *      2 => [ 'address',   self::TYPE_STRING,  self::RULE_OPTIONAL,    false,  'string'],
     *      3 => [ 'age',   self::TYPE_INT32,   self::RULE_OPTIONAL,    false,  'int'],
     *     
     *   ];
     */
	function __construct($data = null)
	{
        if( is_null($data) || empty($data) )
            return;

        if($data instanceof Reader ){
            serializer\Native::import($data, $this);
        }elseif(is_array($data)){
            serializer\NativeArray::import($data, $this);
        }elseif(is_string($data))
        {
            if($data[0] == '{')
                serializer\Json::import($data, $this);
            else 
                serializer\Native::import(new Reader($data), $this);
        }
    
	}
    /**
     * Auto get property. (get | set | add | has | clear | is)
     * with some validation before execution, this function to user action
     * 
     * call $obj->getName() to get $obj->name;
     * call $obj->isName() to check $obj->name = true;
     * call $obj->hasName() to check isset($obj->name) || count($obj->name) > 0;
     * call $obj->clearName() to clear $obj->name = null || [];
     * call $obj->addName( $value ) to set $obj->name[] = $value;
     * 
     * call $obj->setName( $name ) to set $obj->name = $name;
     * call $obj->setNameLong( $name ) to set $obj->nameLong = $name;
     */
    public function __call(String $function, Array $arguments)
    {

        $call;
        $name;
        for($i = 2; $i < strlen($function); $i++)
        {
            $char = ord($function[$i]);
            //Check is uppercase A-Z, or number, or '_'
            if(    ($char >= 65 && $char <= 90) // A-Z
                || ($char >= 48 && $char <= 57) // 0-9
                || $char == 95 ) { // _
                $call = substr($function, 0, $i);
                $name = lcfirst( substr($function, $i) );
                break;
            }
        }
            
        if(empty($call) || empty($name))
            throw new Exception("Invalid call", 1);

        if(!property_exists($this, $name)){
            switch ( $call ) {
                case 'get':
                    return null;
                    break;
                case 'has':
                case 'is':
                    return false;
                    break;
                default:
                    throw new Exception("Undefined $name on " . get_called_class());
                    break;
            }
        }

        switch ( $call ) {
            case 'get':
                return $this->$name;
                break;

            case 'has':
                return is_array($this->$name) ? count($this->$name) > 0 : isset($this->$name);
                break;
                
            case 'is':
                return $this->$name == true;
                break;

            case 'add':
                    if(empty($arguments))
                        throw new Exception("Missing value when calling $function");
                    if(!isset($this->$name))
                        $this->__setDefaultValue($name);

                    if(is_array($this->$name))
                        $this->$name[] = $arguments[0];
                    else{
                        if(is_string($this->$name))
                            $this->$name    .= $arguments[0];
                        elseif(is_numeric($this->$name))
                            $this->$name    += $arguments[0];
                        else
                            throw new Exception("Not addable $name");
                    }
                break;

            case 'set':
                if(empty($arguments))
                    throw new Exception("Missing value when calling $function");
                $field  = $this->getProtofield($name);
                $value  = $arguments[0];
                if($field[self::PROTO_RULE] == self::RULE_REPEATED)
                {
                    if(!is_array($value))
                        throw new Exception("set value of $name must be array");
                    foreach ($value as &$val){
                        if($this->__isValidType($field, $val))
                        {
                            $this->$name[] = $val;
                        }else{ 
                            throw new Exception("value of $name must have type '" . $field[self::PROTO_CLASS] . 
                                    "' but found '" . (is_object($val) ? get_class($val) : gettype($val) ) . "'");
                        }  
                    }
                        
                }else{
                    if($this->__isValidType($field, $value))
                    {
                        $this->$name =  $value;
                    }else{ 
                        throw new Exception("value of $name must have type '" . $field[self::PROTO_CLASS] . 
                                "' but found '" . (is_object($value) ? get_class($value) : gettype($value) ) . "'");
                    }
                }
                    
                break;

            case 'clear':
                $this->$name = is_array($this->$name) ? [] : null;
                break;
            
            default:
                throw new Exception("Invalid call", 1);
                break;
        }

    }

    function __isValidType(&$field, &$value)
    {
        
        $proto_type = $field[self::PROTO_TYPE];
        $value_type = gettype($value);

        if( $proto_type == self::TYPE_MESSAGE || $proto_type == self::TYPE_GROUP)
        {
            if($value_type != 'object')
                return false;

            if( !is_a($value, $field[self::PROTO_CLASS]) )
                return false;

            else return true;

        }

        //primitive type

        if($value_type == $field[self::PROTO_CLASS])
            return true;

        switch ($proto_type) {
            case self::TYPE_DOUBLE:
            case self::TYPE_FLOAT:

                if(!is_numeric($value))
                    return false;

                $value = doubleval($value);
                return true;
                break;

            case self::TYPE_INT64:
            case self::TYPE_UINT64:
            case self::TYPE_INT32:
            case self::TYPE_FIXED64:
            case self::TYPE_FIXED32:
            case self::TYPE_UINT32:
            case self::TYPE_ENUM:
            case self::TYPE_SFIXED32:
            case self::TYPE_SFIXED64:
            case self::TYPE_SINT32:
            case self::TYPE_SINT64:
                
                if(!is_numeric($value))
                    return false;
                $value = intval($value);
                return true;

                break;

            case self::TYPE_BOOL:

                if(strtolower($value) == 'true')
                {
                    $value = true;
                    return true;
                }elseif(strtolower($value) == 'false')
                {
                    $value = false;
                    return true;
                }else return false;
                break;

            case self::TYPE_STRING:
            case self::TYPE_BYTES:
                $value = strval($value);
                return true;
                break;

            default:
                throw new Exception("Invalid proto type $proto_type");
                
                break;
        }

    }

    function __setDefaultValue($name)
    {
        $fields = $this->getProtoFields();
        $number = $this->getProtoNumber($name);

        switch ($fields[$number][self::PROTO_TYPE]){
            case self::TYPE_DOUBLE:
            case self::TYPE_FLOAT:
                $this->$name = 0.0;
                break;
            case self::TYPE_INT64:
            case self::TYPE_UINT64:
            case self::TYPE_INT32:
            case self::TYPE_FIXED64:
            case self::TYPE_FIXED32:
            case self::TYPE_UINT32:
            case self::TYPE_ENUM:
            case self::TYPE_SFIXED32:
            case self::TYPE_SFIXED64:
            case self::TYPE_SINT32:
            case self::TYPE_SINT64:
                $this->$name = 0;
                break;

            case self::TYPE_BOOL:
                $this->$name = false;
                break;

            case self::TYPE_STRING:
            case self::TYPE_BYTES:
                $this->$name = "";
                break;

            default:
                $this->$name = null;
                break;
        }

    }
    
    function __addUnknown($number, $raw)
    {
        
        if(!isset($this->__unknown))
        {
            $this->__unknown = [$number => $raw];

        }elseif(!isset( $this->__unknown[$number] ))
        {
            $this->__unknown[$number]   = $raw;

        }elseif(is_array( $this->__unknown[$number] ))
        {   //Add Unknown repeated field
            $this->__unknown[$number][] = $raw;
        }else
        {   //New Unknown repeated field
            $this->__unknown[$number] = [ $this->__unknown[$number], $raw ];
        }
    }

    function getProtoFields(): Array {
        $child = get_called_class();
        return $child::FIELDS;
    }


    function getProtoNumber(String $name)
    {
        $class  = get_called_class();

        if(! property_exists($class, $name) )
            throw new Exception("Field $name not exists on $class");

        foreach ($class::FIELDS as $number => &$field) {
            if( $field[ self::PROTO_NAME ] == $name )
                return $number;
        }

        throw new Exception("Corrupted message structure on $class");
    }

    function getProtoField(String $name)
    {
         $class  = get_called_class();

        if(! property_exists($class, $name) )
            throw new Exception("Field $name not exists on $class");

        foreach ($class::FIELDS as $number => &$field) {
            if( $field[ self::PROTO_NAME ] == $name )
                return $field;
        }

        throw new Exception("Corrupted message structure on $class");

    }

    function __setUnknown($data)
    {
        $this->__unknown = $data;
    }

    function __getUnknown()
    {
        return $this->__unknown;   
    }

    function __hasUnknown()
    {
        return isset($this->__unknown) && count($this->__unknown) > 0;
    }

    function toString(): String {
       return serializer\Native::export($this);
    }

    function __toString()
    {
        return serializer\Native::export($this);
    }

    function toArray(): Array {
        return serializer\NativeArray::export($this);
    }

    function toJson(): String {
        return serializer\Json::export($this);
    }

    function get($name)
    {
        return $this->$name;
    }

    function add($name, $data)
    {
        $this->$name[]  = $data;
    }

    function set($name, $value)
    {
        $this->$name    = $value;
    }

    function has($name)
    {
        return is_array($this->$name) ? count($this->$name) > 0 : isset($this->$name);
    }
    
    function clear($name)
    {
        $this->$name = is_array($this->$name) ? [] : null;
    }

    function is($name)
    {
        return $this->$name;
    }

}