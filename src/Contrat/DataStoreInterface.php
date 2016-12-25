<?php
namespace Chaos\ORM\Contrat;

interface DataStoreInterface
{
    public function get($name = null);
    public function set($name, $data = []);
}
