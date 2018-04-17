<?php

namespace AveSystems\ObjectResolverBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity()
 */
class Event
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     *
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="string")
     *
     * @var string
     */
    private $title;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isActive = true;

    /**
     * @ORM\Column(type="datetime")
     */
    private $dateFrom;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $dateTo;

    /**
     * @ORM\OneToMany(targetEntity="Poll", mappedBy="event", cascade="all", orphanRemoval=true)
     *
     * @var ArrayCollection
     */
    private $polls;

    public function __construct()
    {
        $this->polls = new ArrayCollection();
        $this->dateFrom = new \DateTime();
        $this->dateTo = (new \DateTime())->add(new \DateInterval('PT1H'));
    }

    public function __toString()
    {
        return $this->title ?: 'Событие';
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return bool
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * @param bool $isActive
     *
     * @return $this
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDateFrom()
    {
        return $this->dateFrom;
    }

    /**
     * @param \DateTime $from
     *
     * @return $this
     */
    public function setDateFrom($from)
    {
        $this->dateFrom = $from;

        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDateTo()
    {
        return $this->dateTo;
    }

    /**
     * @param \DateTime $to
     *
     * @return $this
     */
    public function setDateTo($to)
    {
        $this->dateTo = $to;

        return $this;
    }

    /**
     * @param Poll $poll
     *
     * @return $this
     */
    public function addPoll($poll)
    {
        $this->polls->add($poll);
        $poll->setEvent($this);

        return $this;
    }

    /**
     * @param Poll $poll
     *
     * @return $this
     */
    public function removePoll($poll)
    {
        foreach ($this->polls as $key => $p) {
            if ($p->getId() === $poll->getId()) {
                $this->polls->remove($key);
            }
        }

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getPolls()
    {
        return $this->polls;
    }
}
