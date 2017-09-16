<?php
namespace kc\proto\serializer\native;

use \kc\proto\Message;
use \Exception;
/**
* 
*/
class Util
{
    const   TAG_TYPE_BITS = 3,
            TAG_TYPE_MASK = (1 << self::TAG_TYPE_BITS) - 1;

    const   WIRETYPE_VARINT  = 0,
            WIRETYPE_FIXED64 = 1,
            WIRETYPE_LENGTH_DELIMITED = 2,
            WIRETYPE_START_GROUP = 3,
            WIRETYPE_END_GROUP = 4,
            WIRETYPE_FIXED32 = 5;

	public static function getTagFieldNumber($tag)
    {
        return ($tag >> self::TAG_TYPE_BITS) &
            (1 << ((PHP_INT_SIZE * 8) - self::TAG_TYPE_BITS)) - 1;
    }
    
    public static function getTagWireType($tag)
    {
        return $tag & self::TAG_TYPE_MASK;
    }

    public static function getWireType($type)
    {
        switch ($type) {
            case Message::TYPE_FLOAT:
            case Message::TYPE_FIXED32:
            case Message::TYPE_SFIXED32:
                return self::WIRETYPE_FIXED32;
            case Message::TYPE_DOUBLE:
            case Message::TYPE_FIXED64:
            case Message::TYPE_SFIXED64:
                return self::WIRETYPE_FIXED64;
            case Message::TYPE_UINT32:
            case Message::TYPE_UINT64:
            case Message::TYPE_INT32:
            case Message::TYPE_INT64:
            case Message::TYPE_SINT32:
            case Message::TYPE_SINT64:
            case Message::TYPE_ENUM:
            case Message::TYPE_BOOL:
                return self::WIRETYPE_VARINT;
            case Message::TYPE_STRING:
            case Message::TYPE_BYTES:
            case Message::TYPE_MESSAGE:
                return self::WIRETYPE_LENGTH_DELIMITED;
            case Message::TYPE_GROUP:
                return self::WIRETYPE_START_GROUP;
            default:
                throw new Exception("Unsupported type $type.");
                return 0;
        }
    }

	
    public static function divideInt64ToInt32($value, &$high, &$low, $trim = false)
    {
        $isNeg = (bccomp($value, 0) < 0);
        if ($isNeg) {
            $value = bcsub(0, $value);
        }

        $high = bcdiv($value, 4294967296);
        $low = bcmod($value, 4294967296);
        if (bccomp($high, 2147483647) > 0) {
            $high = (int) bcsub($high, 4294967296);
        } else {
            $high = (int) $high;
        }
        if (bccomp($low, 2147483647) > 0) {
            $low = (int) bcsub($low, 4294967296);
        } else {
            $low = (int) $low;
        }

        if ($isNeg) {
            $high = ~$high;
            $low = ~$low;
            $low++;
            if (!$low) {
                $high = (int)($high + 1);
            }
        }

        if ($trim) {
            $high = 0;
        }
    }

    public static function combineInt32ToInt64($high, $low)
    {
        $isNeg = $high < 0;
        if ($isNeg) {
            $high = ~$high;
            $low = ~$low;
            $low++;
            if (!$low) {
                $high = (int) ($high + 1);
            }
        }
        $result = bcadd(bcmul($high, 4294967296), $low);
        if ($low < 0) {
            $result = bcadd($result, 4294967296);
        }
        if ($isNeg) {
          $result = bcsub(0, $result);
        }
        return $result;
    }
    public static function zigZagEncode32($int32)
  {
      if (PHP_INT_SIZE == 8) {
          $trim_int32 = $int32 & 0xFFFFFFFF;
          return (($trim_int32 << 1) ^ ($int32 << 32 >> 63)) & 0xFFFFFFFF;
      } else {
          return ($int32 << 1) ^ ($int32 >> 31);
      }
  }

    public static function zigZagDecode32($uint32)
    {
        // Fill high 32 bits.
        if (PHP_INT_SIZE === 8) {
            $uint32 |= ($uint32 & 0xFFFFFFFF);
        }

        $int32 = (($uint32 >> 1) & 0x7FFFFFFF) ^ (-($uint32 & 1));

        return $int32;
    }

    public static function zigZagEncode64($int64)
    {
        if (PHP_INT_SIZE == 4) {
            if (bccomp($int64, 0) >= 0) {
                return bcmul($int64, 2);
            } else {
                return bcsub(bcmul(bcsub(0, $int64), 2), 1);
            }
        } else {
            return ($int64 << 1) ^ ($int64 >> 63);
        }
    }

    public static function zigZagDecode64($uint64)
    {
        if (PHP_INT_SIZE == 4) {
            if (bcmod($uint64, 2) == 0) {
                return bcdiv($uint64, 2, 0);
            } else {
                return bcsub(0, bcdiv(bcadd($uint64, 1), 2, 0));
            }
        } else {
            return (($uint64 >> 1) & 0x7FFFFFFFFFFFFFFF) ^ (-($uint64 & 1));
        }
    }
}