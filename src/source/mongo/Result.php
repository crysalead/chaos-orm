<?php
namespace chaos\source\mongo;

use MongoGridFSFile;

class Result extends \chaos\source\Result
{
    /**
     * Fetches the result from the resource and caches it.
     *
     * @return boolean Return `true` on success or `false` if it is not valid.
     */
    protected function _fetchFromResource()
    {
        if ($this->_resource && $this->_resource->hasNext()) {
            $result = $this->_resource->getNext();
            $isFile = ($result instanceof MongoGridFSFile);
            $result = $isFile ? array('file' => $result) + $result->file : $result;
            $this->_key = $this->_iterator;
            $this->_current = $result;
            return true;
        }
        return false;
    }
}
