<?php

namespace kc\proto;
use \kc\proto\serializer\native\Reader;

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

    function getProtoFields(): Array {
        $child = get_called_class();
        return $child::FIELDS;
    }

    function toString(): String {
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
        return isset($this->$name) ? $this->$name : null;
    }

    function add($name, $data)
    {
        $this->$name[]  = $data;
    }

    function set($name, $value)
    {
        $this->$name    = $value;
    }
    
    function clear($name)
    {
        $this->$name = is_array($this->$name) ? [] : null;
    }

}