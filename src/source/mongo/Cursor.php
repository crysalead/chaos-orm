<?php
namespace chaos\source\mongo;

/**
 * This class is a wrapper around database result and can be used to iterate over it.
 */
class Cursor extends chaos\source\Cursor
{
    /**
     * Fetches the result from the resource attribute.
     *
     * @return boolean Return `true` on success or `false` otherwise.
     */
    protected function _fetchResource()
    {
        if (!$this->_resource) {
            return false;
        }
        if (!$this->_started) {
            $this->_resource->rewind();
        }
        if (!$this->_resource->valid()) {
            return false;
        }
        $this->_current = $this->_resource->current();
        $this->_key = $this->_resource->key();
        $this->_resource->next();
        return true;
    }

}
