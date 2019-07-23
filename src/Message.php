<?php
namespace xingwenge\canal_php;

use Com\Alibaba\Otter\Canal\Protocol\Entry;

class Message
{
    /** @var int */
    private $id;
    /** @var array */
    private $entries;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId( $id )
    {
        $this->id = $id;
    }

    /**
     * @return array
     */
    public function getEntries()
    {
        return $this->entries;
    }

    /**
     * @param Entry $entry
     */
    public function addEntries( $entry )
    {
        $this->entries[] = $entry;
    }
}