<?php
namespace Chaos\ORM\Contrat;

interface DataStoreInterface
{
    public function get($name = null);
    public function set($name, $data = []);
    public function setAt($name, $data, $options = []);
    public function amend($data = null, $options = []);
}
