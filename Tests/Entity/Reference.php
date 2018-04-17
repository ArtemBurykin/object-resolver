<?php

namespace AveSystems\ObjectResolverBundle\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class Reference.
 *
 * @ORM\Entity
 */
class Reference
{
    /**
     * @ORM\Column(type="integer")
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     */
    private $title;

    private $description;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param mixed $title
     *
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @param mixed $description
     *
     * @throws \Exception
     *
     * @return self
     */
    public function setDescription($description)
    {
        throw new \Exception();
        $this->description = $description;

        return $this;
    }
}
