<?php
namespace chaos\source\mongo;

use MongoId;
use MongoCode;
use MongoDate;
use MongoRegex;
use MongoBinData;
use set\Set;

class Schema extends \chaos\source\Schem
{
    public function __construct($config = []) {
        $defaults = [
            'fields' => ['_id' => ['type' => 'id']],
            'handlers' => [
                'cast' => [
                    'id' => function($v) {
                        return is_string($v) && preg_match('/^[0-9a-f]{24}$/', $v) ? new MongoId($v) : $v;
                    },
                    'date' => function($v) {
                        $v = is_numeric($v) ? intval($v) : strtotime($v);
                        return !$v ? new MongoDate() : new MongoDate($v);
                    },
                    'regex'   => function($v) { return new MongoRegex($v); },
                    'integer' => function($v) { return (integer) $v; },
                    'float'   => function($v) { return (float) $v; },
                    'boolean' => function($v) { return (boolean) $v; },
                    'code'    => function($v) { return new MongoCode($v); },
                    'binary'  => function($v) { return new MongoBinData($v); }
                ]
            ],
        ];
        $config = Set::merge($defaults, $config);
        parent::__construct($config);
    }
}
