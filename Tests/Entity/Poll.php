<?php

namespace AveSystems\ObjectResolverBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\PollRepository")
 */
class Poll
{
    const NOT_STARTED = 0;

    const STARTED = 1;

    const RESULTS_SHOWN = 2;

    const FINISHED = 3;

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", nullable=false)
     */
    private $title;

    /**
     * @ORM\Column(type="boolean")
     */
    private $isMultiple = false;

    /**
     * @ORM\OneToMany(targetEntity="PollOption", mappedBy="poll", cascade="all", orphanRemoval=true)
     *
     * @var ArrayCollection
     */
    private $options;

    /**
     * @ORM\ManyToOne(targetEntity="Event", inversedBy="polls")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     */
    private $event;

    /**
     * @ORM\Column(type="integer")
     *
     * Available transitions:
     * not_started -> started -> show_result -> finished
     * not_started -> started -> finished
     * started -> not_started if there is no votes so far
     * show_result -> started always
     *
     * //TODO: add state machine
     */
    private $state = self::NOT_STARTED;

    /**
     * @ORM\Column(type="boolean")
     */
    private $onMonitor = false;

    public function ___construct()
    {
        $this->options = new ArrayCollection();
    }

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
     * @return $this
     */
    public function setTitle($title)
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getIsMultiple()
    {
        return $this->isMultiple;
    }

    /**
     * @param mixed $isMultiple
     *
     * @return $this
     */
    public function setIsMultiple($isMultiple)
    {
        $this->isMultiple = $isMultiple;

        return $this;
    }

    /**
     * @return ArrayCollection
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param PollOption $option
     *
     * @return Poll
     */
    public function addOption(PollOption $option)
    {
        if (!$option) {
            return $this;
        }

        $option->setPoll($this);

        $this->options[] = $option;

        return $this;
    }

    /**
     * @param PollOption $option
     */
    public function removeOption(PollOption $option)
    {
        $this->options->removeElement($option);
    }

    /**
     * @return Event
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param Event $event
     *
     * @return $this
     */
    public function setEvent(Event $event)
    {
        $this->event = $event;

        return $this;
    }

    /**
     * @return int
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param int $state
     *
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @return bool
     */
    public function getOnMonitor()
    {
        return $this->onMonitor;
    }

    /**
     * @param bool $onMonitor
     *
     * @return Poll
     */
    public function setOnMonitor($onMonitor)
    {
        $this->onMonitor = $onMonitor;

        return $this;
    }
}
