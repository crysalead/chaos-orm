<?php
namespace Chaos\ORM\Contrat;

interface HasParentsInterface
{
    public function parents();
    public function setParent($parent, $from);
    public function removeParent($parent);
}