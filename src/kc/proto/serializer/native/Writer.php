<?php
namespace kc\proto\serializer\native;
use \kc\proto\Message;
use \Exception;
use \kc\proto\serializer\Native;
/**
* 
*/
class Writer
{
	private    $data = '', $pos = 0, $size = 0;

    function getData(){
        return $this->data;
    }

    function writeToFile($filename)
    {
        $f = fopen($filename, 'w');
        $writen = fwrite($f, $this->data);
        fclose($f);
        return $writen;
    }

    function getSize(){
        return strlen($this->data);
    }

    function write($value)
    {
        $this->data .= $value;
        return strlen($value);
    }

    function writeByte($value)
    {
        $this->data .= chr($value);
        return 1;
    }

    function writeBytes(Array $values)
    {
        $writen = 0;
        for($i = 0; $i < count($values); $i++) {
            $this->data .= chr($values[$i]);
            $writen++;
        }
        return $writen;
    }

    function writeTag($number, $wire_type)
    {
        return $this->writeVarint( $number << 3 | $wire_type, true);
    }

    function writeVarint($value, $trim = false)
    {
        $high = 0;
        $low = 0;
        if (PHP_INT_SIZE == 4) {
            Util::divideInt64ToInt32($value, $high, $low, $trim);
        } else {
            $low = $value;
        }
        $counter = 0;

        while (($low >= 0x80 || $low < 0) || $high != 0) {
            $counter += $this->writeByte($low | 0x80);
            $value = ($value >> 7) & ~(0x7F << ((PHP_INT_SIZE << 3) - 7));
            $carry = ($high & 0x7F) << ((PHP_INT_SIZE << 3) - 7);
            $high = ($high >> 7) & ~(0x7F << ((PHP_INT_SIZE << 3) - 7));
            $low = (($low >> 7) & ~(0x7F << ((PHP_INT_SIZE << 3) - 7)) | $carry);
        }
        return $this->writeByte($low) + $counter;
    }

    function writeValue($number, $type, $value, $write_tag = true)
    {
        $writen = 0;
        if($write_tag){
            $writen += $this->writeTag($number, Util::getWireType($type));
        }

		switch ($type) {
            case Message::TYPE_DOUBLE:
                return $this->write(pack("d", $value)) + $writen;

            case Message::TYPE_FLOAT:
                return $this->write(pack("f", $value)) + $writen;

            case Message::TYPE_INT64:
            case Message::TYPE_UINT64:
                return $this->writeVarint($value) + $writen;

            case Message::TYPE_INT32:
            case Message::TYPE_ENUM:
                if($value > 0x7fffffff)
                    throw new Exception("Int32 Overflow on value $value");
                    
                return $this->writeVarint($value, false) + $writen;

            case Message::TYPE_FIXED32:
                return $this->writeLittleEndian32($value) + $writen;

            case Message::TYPE_FIXED64:
                return $this->writeLittleEndian64($value) + $writen;

            case Message::TYPE_BOOL:
                return $this->writeVarint($value ? 1 : 0, true) + $writen;

            case Message::TYPE_BYTES:
            case Message::TYPE_STRING:
                $len    = strlen($value);
                $len    = $this->writeVarint($len);
                return $this->write($value) + $len + $writen;

            case Message::TYPE_GROUP:
                 Native::export( $value, $this );
                 $this->writeTag($number, Util::WIRETYPE_END_GROUP);
                 
                 break;
            case Message::TYPE_MESSAGE:
                $value  = $value->toString();
                $size = strlen($value);
                $size = $this->writeVarint($size, true);
                return $this->write($value) + $size + $writen;

            case Message::TYPE_UINT32:
                if (PHP_INT_SIZE === 8 && $value < 0) {
                    $value += 4294967296;
                }
                return $this->writeVarint($value, true) + $writen;

            case Message::TYPE_SFIXED32:
                return $this->writeLittleEndian32($value) + $writen;

            case Message::TYPE_SFIXED64:
                return $this->writeLittleEndian64($value) + $writen;

            case Message::TYPE_SINT32:
                $value  = Util::zigZagEncode32($value);
                return $this->writeVarint($value, true) + $writen;

            case Message::TYPE_SINT64:
                $value  = Util::zigZagEncode64($value);
                return $this->writeVarint($value) + $writen;

            default:
                throw new Exception("Unsupported type.");
                break;
        }
        return false;
	}
    function writeLittleEndian32($value)
    {
        return $this->writeBytes([
            $value & 0x000000FF, 
            ($value >> 8) & 0x000000FF,
            ($value >> 16) & 0x000000FF,
            ($value >> 24) & 0x000000FF ]);
    }
    function writeLittleEndian64($value)
    {
        $high = 0;
        $low = 0;
        if (PHP_INT_SIZE == 4) {
            Util::divideInt64ToInt32($value, $high, $low);
        } else {
            $low = $value & 0xFFFFFFFF;
            $high = ($value >> 32) & 0xFFFFFFFF;
        }

        return $this->writeBytes([$low & 0x000000FF,
            ($low >> 8) & 0x000000FF,
            ($low >> 16) & 0x000000FF,
            ($low >> 24) & 0x000000FF,
            $high & 0x000000FF,
            ($high >> 8) & 0x000000FF,
            ($high >> 16) & 0x000000FF,
            ($high >> 24) & 0x000000FF,
            ]);
    }
}