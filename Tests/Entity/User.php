<?php

namespace AveSystems\ObjectResolverBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\Entity()
 */
class User implements UserInterface
{
    /**
     * @ORM\Id()
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;
    /**
     * @ORM\Column(type="string")
     */
    private $username;
    /**
     * @ORM\Column(type="string")
     */
    private $email;
    /**
     * @ORM\Column(type="array")
     */
    private $roles;
    /**
     * @ORM\Column(type="date")
     */
    private $birthDate;
    /**
     * @ORM\ManyToMany(targetEntity="AveSystems\ObjectResolverBundle\Tests\Entity\User")
     */
    private $siblings;

    /**
     * @ORM\ManyToMany(targetEntity="AveSystems\ObjectResolverBundle\Tests\Entity\Tag", cascade="all")
     */
    private $tags;

    /**
     * @ORM\OneToOne(targetEntity="AveSystems\ObjectResolverBundle\Tests\Entity\User")
     */
    private $closestRelative;

    public function __construct()
    {
        $this->siblings = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return mixed
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param mixed $name
     */
    public function setUsername($name)
    {
        $this->username = $name;
    }

    /**
     * @return mixed
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param mixed $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @return mixed
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param mixed $roles
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

    /**
     * @return mixed
     */
    public function getBirthDate()
    {
        return $this->birthDate;
    }

    /**
     * @param mixed $birthDate
     */
    public function setBirthDate($birthDate)
    {
        $this->birthDate = $birthDate;
    }

    /**
     * @return ArrayCollection
     */
    public function getSiblings()
    {
        return $this->siblings;
    }

    /**
     * @param mixed $sibling
     */
    public function addSibling($sibling)
    {
        $this->siblings->add($sibling);
    }

    public function removeSibling($sibling)
    {
        $this->siblings->remove($sibling);
    }

    /**
     * @param Tag $tag
     */
    public function addTag($tag)
    {
        $this->tags->add($tag);
    }

    /**
     * @return ArrayCollection
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @param Tag $tag
     */
    public function removeTag($tag)
    {
        $this->tags->remove($tag);
    }

    /**
     * @return mixed
     */
    public function getClosestRelative()
    {
        return $this->closestRelative;
    }

    /**
     * @param mixed $closestRelative
     */
    public function setClosestRelative(self $closestRelative)
    {
        $this->closestRelative = $closestRelative;
    }

    public function getPassword()
    {
        return '';
    }

    public function getSalt()
    {
        return '';
    }

    public function eraseCredentials()
    {
    }

    public static function getUser($id)
    {
        $name = uniqid();
        $user = new self();
        $user->setId($id);
        $user->setEmail($name.'@gmail.com');
        $user->setBirthDate(new \DateTime());
        $user->setRoles(['role1', 'role2']);
        $user->setUsername($name);

        return $user;
    }
}
