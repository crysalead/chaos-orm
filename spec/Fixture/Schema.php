<?php
namespace Chaos\ORM\Spec\Fixture;

class Schema extends \Chaos\ORM\Schema
{
    public function __construct($config = [])
    {
        parent::__construct($config);
        $handlers = $this->_handlers;
        $this->formatter('array', 'id',     $handlers['array']['integer']);
        $this->formatter('array', 'serial', $handlers['array']['integer']);

        $this->formatter('cast', 'id',      $handlers['cast']['integer']);
        $this->formatter('cast', 'serial',  $handlers['cast']['integer']);
    }
}