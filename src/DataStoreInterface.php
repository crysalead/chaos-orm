<?php
namespace Chaos;

interface DataStoreInterface
{
    public function get($name = null);
    public function set($name, $data = []);
}
