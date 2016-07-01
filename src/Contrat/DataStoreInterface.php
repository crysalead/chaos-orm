<?php
namespace Chaos\Contrat;

interface DataStoreInterface
{
    public function get($name = null);
    public function set($name, $data = []);
}
