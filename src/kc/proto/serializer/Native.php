<?php

namespace kc\proto\serializer;

use \kc\proto\Message;
use \kc\proto\serializer\native\Reader;
use \kc\proto\serializer\native\Writer;
use \kc\proto\serializer\native\Util;
use \Exception;

/**
* Native serializer to binary file
*/
class Native implements Serializer
{
	public static function export(Message $message, Writer $writer = null)
	{
		if( is_null($writer) )
			$writer = new Writer();
		
		
		foreach ($message->getProtoFields() as $number => &$field)
		{
			$name   = $field[ Message::PROTO_NAME ];
	        $rule   = $field[ Message::PROTO_RULE ];
	        $type   = $field[ Message::PROTO_TYPE ];
	        
	        $value  = $message->get($name);
	        if(is_null($value))
	        {
	        	if($rule == Message::RULE_REQUIRED )
	        		throw new Exception("Field '$name' cannot be empty");
	        	else continue;
	        }
	        if( $rule == Message::RULE_REPEATED )
	        {
	        	if(!is_array($value))
	        		throw new Exception("Repeated fields must be array");

	        	if( count($value) == 0)
	        		continue;

	        	if( $field[ Message::PROTO_PACKED ] )
	        	{
	        		$tmpWriter = new Writer();
	        		foreach ($value as $val){
		        		$tmpWriter->writeValue($number, $type, $val, false );
		        	}
		        	$size = $tmpWriter->getSize();
		        	$value= $tmpWriter->getData();
	        		$writer->writeTag($number, Util::WIRETYPE_LENGTH_DELIMITED);
	        		$writer->writeVarint($size, true);
	        		$writer->write($value);
	        		$tmpWriter = null;
	        	}else{
		        	foreach ($value as $val){
		        		$writer->writeValue($number, $type, $val );
		        	}
	        	}
	        	

	        }else{
	        	$writer->writeValue($number, $type, $value);
	        }
		}
		return $writer->getData();
	}

	public static function import($reader, Message $message)
	{
		if( ! ($reader instanceof native\Reader) )
				throw new Exception("Invalid reader");


		$fields = is_null($message)? [] : $message->getProtoFields();
		while ( ($tag = $reader->readTag() ) != false) {
			$number		= Util::getTagFieldNumber($tag);
			$wire_type	= Util::getTagWireType($tag);

			$field	= isset($fields[ $number ]) ? $fields[ $number ] : null;
			//echo "\nnumber $number, wire $wire_type, ori " . Util::getWireType($field[Message::PROTO_TYPE]);

			//Check is field match with message structure or not
			if(	(is_null($field) ||
				$wire_type != Util::getWireType($field[Message::PROTO_TYPE]) ) &&
				! ($field[Message::PROTO_PACKED] && $wire_type == Util::WIRETYPE_LENGTH_DELIMITED) )
			{	
				// Handle unknown fields
				$message->__addUnknown($number, $reader->readUnknown($number, $wire_type) );

			}elseif( $field[Message::PROTO_PACKED] )
			{
				$length = $reader->readVarint32();
				$reader->pushLimit($length);
				while( !$reader->eof() )
				{
					$message->add( $field[Message::PROTO_NAME], $reader->readValue($field) );
				}
				$reader->popLimit();
			}else
			{
				if($field[Message::PROTO_RULE] == Message::RULE_REPEATED)
					$message->add( $field[Message::PROTO_NAME], $reader->readValue($field) );
				else
					$message->set( $field[Message::PROTO_NAME], $reader->readValue($field) );
			}
		}
	}

}
