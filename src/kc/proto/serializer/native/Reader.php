<?php
namespace kc\proto\serializer\native;
use \kc\proto\serializer\Native;
use \kc\proto\Message;
use \Exception;
/**
* 
*/
class Reader
{
	const	MAX_VARINT_BYTES = 10;

	private	$data = '';
	public	$size, $pos = 0, $limit = false, $stack = [],
            $tag, $marker = false, $marker_stack = [];

	function __construct(String $data){
		$this->data	= $data;
		$this->size	= strlen($data);
	}

    static function fromFile($file): Reader {
        return new Reader(file_get_contents($file));
    }


	function pushLimit(int $size){
        
        if($size > $this->available())
            throw new Exception("Limit max is: " . $this->available());

		array_push($this->stack, [$this->pos, $this->size]);
        $this->size = $this->pos + $size;
	}

    function popLimit(){
        //restore size only, position still move forward
        $this->size = array_pop($this->stack)[1];
    }

    function pushMarker($tag)
    {
        array_push($this->marker_stack, $this->marker);
        $this->marker = $tag;
    }

    function popMarker()
    {
        $this->marker = array_pop($this->marker_stack);
    }

    function available(){
        return $this->size - $this->pos;
    }

	function reset(){
        if( count($this->stack) > 0)
        {
            $limit = array_slice($this->stack, -1)[0];
            $this->pos = $limit[0];
        }else $this->pos = 0;
	}

	function eof(){
		return $this->pos >= $this->size;
	}

    function canRead(int $size)
    {
        return $this->pos + $size <= $this->size;
    }

	function getData(){
		return $this->data;
	}

	function getSize(){
		return $this->size;
	}

    //Get Current tag
    function getTag()
    {
        return $this->tag;
    }

	function read(){
		return $this->eof() ? false : $this->data[ $this->pos++ ];
	}

    function readBytes(int $size)
    {
        if(!$this->canRead($size))
            throw new Exception("Not enough buffer to read pos: {$this->pos}, limit: {$this->limit}, avail: " . $this->available());
            
        $data = substr($this->data, $this->pos, $size);
        $this->pos += $size;
        return $data;

    }

	function readByte(){
		return $this->eof() ? false : ord($this->data[ $this->pos++ ]);
	}

    function readTag()
    {
        $this->tag = $this->readVarint32();
        //Force return false by marker to end tag loop
        if( $this->marker !== false && $this->tag == $this->marker ){
            return false;
        }else return $this->tag;
    }
    function readUnknown($number, $wire_type)
    {
        switch ($wire_type) {
            case Util::WIRETYPE_VARINT:
                return $this->readVarint32();
                
            case Util::WIRETYPE_FIXED64:
                return $this->readLittleEndian64();
                
            case Util::WIRETYPE_LENGTH_DELIMITED:
                $size = $this->readVarint64();
                return $this->readBytes($size);
                
            case Util::WIRETYPE_START_GROUP:

                $this->pushMarker($number << 3 | Util::WIRETYPE_END_GROUP);
                $value    = new \kc\proto\Unknown();
                Native::import($this, $value);
                if($this->tag !== $this->marker)
                    throw new Exception("Read group failed");
                $this->popMarker();
                return $value;
            case Util::WIRETYPE_FIXED32:
                return $this->readLittleEndian32();
        }
    }

    function readValue(array $field)
    {
        switch ( $field[Message::PROTO_TYPE] ) {
            case Message::TYPE_DOUBLE:
                $value = $this->readBytes(8);
                return unpack('d', $value)[1];
                
            case Message::TYPE_FLOAT:
                $value = $this->readBytes(4);
                return unpack('f', $value)[1];
                
            case Message::TYPE_UINT64:
            case Message::TYPE_INT64:
                $value = $this->readVarint64();
                if (PHP_INT_SIZE == 4 && bccomp($value, "9223372036854775807") > 0) {
                    $value = bcsub($value, "18446744073709551616");
                }
                return $value;

            case Message::TYPE_INT32:
                return $this->readVarint32();
                
            case Message::TYPE_FIXED64:
                return $this->readLittleEndian64();
                
            case Message::TYPE_FIXED32:
                return $this->readLittleEndian32();
                
            case Message::TYPE_BOOL:
                return $this->readVarint64() == 0 ? false : true;
                
            case Message::TYPE_STRING:
                $size = $this->readVarint64();
                return $this->readBytes($size);
                
            case Message::TYPE_GROUP:

                $number = Util::getTagFieldNumber($this->tag);
                $wire   = Util::getTagWireType($this->tag);
                if($wire != Util::WIRETYPE_START_GROUP)
                    throw new Exception("Invalid start group number $number, wire $wire at offset {$this->pos}");

                //Mark the tag of end group
                $this->pushMarker($number << 3 | Util::WIRETYPE_END_GROUP);
                $class = $field[Message::PROTO_CLASS];
                $value    = new $class();
                Native::import($this, $value);
                if($this->tag !== $this->marker)
                    throw new Exception("Read group failed");
                //Remove marker
                $this->popMarker();
                return $value;

            case Message::TYPE_MESSAGE:
                $size = $this->readVarint64();
                $this->pushLimit($size);
                $class= $field[Message::PROTO_CLASS];
                $obj  = new $class();

                Native::import($this, $obj);
                $this->popLimit();
                return $obj;
                break;
            case Message::TYPE_BYTES:
                $size = $this->readVarint64();
                return $this->readBytes($size);

            case Message::TYPE_ENUM:
            case Message::TYPE_UINT32:
                return $this->readVarint32();

            case Message::TYPE_SFIXED32:
                $value  = $this->readLittleEndian32();
                if (PHP_INT_SIZE === 8) {
                    $value |= (-($value >> 31) << 32);
                }
                return $value;
                
            case Message::TYPE_SFIXED64:
                $value  = $this->readLittleEndian64();
                if (PHP_INT_SIZE == 4 && bccomp($value, "9223372036854775807") > 0) {
                    $value = bcsub($value, "18446744073709551616");
                }
                return $value;
                
            case Message::TYPE_SINT32:
                return Util::zigZagDecode32($this->readVarint32($value));
                
            case Message::TYPE_SINT64:
                return Util::zigZagDecode64($this->readVarint64());

            default:
                throw new Exception("Unsupported type.");
        }
    }

	/**
     * Read uint32 into $var. Advance buffer with consumed bytes. If the
     * contained varint is larger than 32 bits, discard the high order bits.
     * @param $var.
     */
    public function readVarint32()
    {
    	$var = $this->readVarint64();
        if (!$var)
        	return false;

        if (PHP_INT_SIZE == 4) {
            $var = bcmod($var, 4294967296);
        } else {
            $var &= 0xFFFFFFFF;
        }

        // Convert large uint32 to int32.
        if ($var > 0x7FFFFFFF) {
            if (PHP_INT_SIZE === 8) {
                $var = $var | (0xFFFFFFFF << 32);
            } else {
                $var = bcsub($var, 4294967296);
            }
        }

        return intval($var);
    }

    /**
     * Read Uint64 into $var. Advance buffer with consumed bytes.
     * @param $var.
     */
    public function readVarint64()
    {
        $count = 0;

        if (PHP_INT_SIZE == 4) {
            $high = 0;
            $low = 0;
            $b = 0;

            do {
                if ($this->eof())
                    return false;

                if ($count === self::MAX_VARINT_BYTES)
                    throw new Exception("Overflow");

                $b = $this->getByte();//ord($this->buffer[$this->current]);
                $bits = 7 * $count;
                if ($bits >= 32) {
                    $high |= (($b & 0x7F) << ($bits - 32));
                } else if ($bits > 25){
                    // $bits is 28 in this case.
                    $low |= (($b & 0x7F) << 28);
                    $high = ($b & 0x7F) >> 4;
                } else {
                    $low |= (($b & 0x7F) << $bits);
                }

                $count += 1;
            } while ($b & 0x80);

            $var = Util::combineInt32ToInt64($high, $low);
            if (bccomp($var, 0) < 0) {
                $var = bcadd($var, "18446744073709551616");
            }
            return $var;
        } else {
            $result = 0;
            $shift = 0;

            do {
                if ($this->eof())
                    return false;

                if ($count === self::MAX_VARINT_BYTES)
                    throw new Exception("Overflow");

                $byte = $this->readByte();
                $result |= ($byte & 0x7f) << $shift;
                $shift += 7;
                $count += 1;
            } while ($byte > 0x7f);

            return $result;
        }

    }
    public function readLittleEndian64()
    {
        $low = unpack( 'V', $this->readBytes(4) )[1];
        $high = unpack( 'V', $this->readBytes(4) )[1];
        if (PHP_INT_SIZE == 4) {
            return Util::combineInt32ToInt64($high, $low);
        } else {
            return ($high << 32) | $low;
        }
    }

    public function readLittleEndian32()
    {
        return unpack('V', $this->readBytes(4))[1];
    }
}