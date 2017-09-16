<?php

namespace kc\proto\serializer;

use \kc\proto\Message;
use \Exception;

/**
* 
*/
class NativeArray implements Serializer
{
	
	public static function export(Message $message)
	{
		if(!function_exists('json_encode'))
            throw new Exception("Require JSON PHP Extension");
        //echo "\nexporting " . get_class($message);
        $result = [];

        $fields = $message->getProtoFields();

        foreach ($fields as $number => &$proto) {
            $name   = $proto[ Message::PROTO_NAME ];
            $rule   = $proto[ Message::PROTO_RULE ];
            
            $value  = $message->get($name);
            if( $rule == Message::RULE_REPEATED )
            {
                if(!is_array($value))
                    throw new Exception("Field: $name must be array");

                $new_value = [];
                foreach ($value as &$val)
                {
                	$tmp = self::exportField($proto, $val);
                	if(!is_null($tmp))
                		$new_value[] = $tmp;
                }

                if(empty($new_value))
                	continue;

                $result[ $name ] = $new_value;
            }else
            {
	            $value = self::exportField($proto, $value);
	            if( is_null($value) )
	            {
		            if( $rule == Message::RULE_REQUIRED )
		                throw new Exception("Missing required field: $name");
		            else continue;
	            }
            	$result[ $name ] = $value;
            }
        }
        if($message->__hasUnknown())
        {
        	$result['__unknown'] = $message->__getUnknown();
        }
        return $result;
	}

	public static function exportField( &$field, &$value)
	{
		if(is_null($value)) return null;

		switch ($field[ Message::PROTO_TYPE ]) {
			case Message::TYPE_ENUM:
				$class = $field[ Message::PROTO_CLASS ];
           		return $class::getName($value);
				break;
			case Message::TYPE_GROUP:
			case Message::TYPE_MESSAGE:
				if(! is_subclass_of($value, 'kc\\proto\\Message', false) )
					throw new Exception($field[ Message::PROTO_NAME ] . " is not instance of \\kc\\proto\\Message");
				return self::export($value);//->toArray();
				break;
			default:
				return $value;
				break;
		}
	}

	public static function import($data, Message $message)
	{
		if(!is_array($data))
			throw new Exception("Incompactible import");
		$fields = $message->getProtoFields();
		foreach ($fields as $number => &$proto) {
            $name   = $proto[ Message::PROTO_NAME ];
            $rule   = $proto[ Message::PROTO_RULE ];
            $value	= isset($data[$name]) ? $data[$name] : null;

            if( $rule == Message::RULE_REPEATED ){
            	
            	if( is_null($value) )
            		continue;

            	if( !is_array($value) )
            		continue;
            		//throw new Exception("Invalid data on $name");

            	foreach ($value as $k => &$v) {
            		$val = self::importField($proto, $v);

            		if(!is_null($val))
            			$message->add($name, $val);
            	}

            }else{

	            $value	= self::importField($proto, $value);

	            if( is_null($value) ){
	            	if( $rule == Message::RULE_REQUIRED)
	            		throw new Exception("Mising required field $name");
	            }else{
	            	$message->set($name, $value);
	            }
            }
            //clear to detect unknown;
            unset($data[$name]);


        }
        if( isset($data['__unknown']) && is_array($data['__unknown']) ){
        	$message->__setUnknown($data['__unknown']);
        	unset($data['__unknown']);
        }
        if( !empty($data) )
        {
        	foreach ($data as $key => $value)
        	{
        		$message->__addUnknown($key, $value);
        	}
        }

	}

	public static function importField( &$field, &$value)
	{
		if(is_null($value)) return null;

		switch ($field[ Message::PROTO_TYPE ]) {
			case Message::TYPE_ENUM:
				$class = $field[ Message::PROTO_CLASS ];
				if(!defined("$class::$value"))
					throw new Exception("Unknown enum value $value");
           		return constant("$class::$value");
				break;
			case Message::TYPE_GROUP:
			case Message::TYPE_MESSAGE:
				$class = $field[ Message::PROTO_CLASS ];
				$new = new $class( $value );
				return $new;
				break;
			default:
				return $value;
				break;
		}
	}

}