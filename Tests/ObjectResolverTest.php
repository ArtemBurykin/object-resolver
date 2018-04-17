<?php

namespace AveSystems\ObjectResolverBundle\Tests;

use AveSystems\ObjectResolverBundle\Exception\ClassNotFoundException;
use AveSystems\ObjectResolverBundle\Exception\UnableToSetIdException;
use AveSystems\ObjectResolverBundle\Exception\UnableToSetValueException;
use AveSystems\ObjectResolverBundle\ObjectResolver;
use AveSystems\ObjectResolverBundle\Tests\app\AppKernel;
use AveSystems\ObjectResolverBundle\Tests\Entity\Event;
use AveSystems\ObjectResolverBundle\Tests\Entity\Poll;
use AveSystems\ObjectResolverBundle\Tests\Entity\PollOption;
use AveSystems\ObjectResolverBundle\Tests\Entity\Reference;
use AveSystems\ObjectResolverBundle\Tests\Entity\Tag;
use AveSystems\ObjectResolverBundle\Tests\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Command\Proxy\UpdateSchemaDoctrineCommand;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Proxy;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Security\Core\User\UserInterface;

class ObjectResolverTest extends KernelTestCase
{
    /** @var ObjectResolver */
    private $resolver;
    /** @var EntityManagerInterface */
    private $em;

    protected function setUp()
    {
        parent::setUp();
        $kernel = static::bootKernel();
        $this->resolver = $kernel->getContainer()->get(ObjectResolver::class);
        $this->em = $kernel->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->getConfiguration()->setSQLLogger(null);
        $this->migrateSchema();
    }

    public function invokeMethod($methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass($this->resolver);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->resolver, $parameters);
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getClass
     */
    public function testGetClass()
    {
        $proxy = $this->em->getProxyFactory()->getProxy(User::class, ['id' => 1]);
        $this->assertInstanceOf(Proxy::class, $proxy);
        $this->assertEquals(User::class, $this->invokeMethod('getClass', [UserInterface::class]));
        $this->assertEquals(User::class, $this->invokeMethod('getClass', [$proxy]));
        $this->assertEquals(User::class, $this->invokeMethod('getClass', [User::class]));
    }

    /**
     * @throws ClassNotFoundException
     * @throws UnableToSetIdException
     * @throws UnableToSetValueException
     * @throws \AveSystems\ObjectResolverBundle\Exception\UnableToGetClassNameException
     * @throws \AveSystems\ObjectResolverBundle\Exception\UnableToGetValueException
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::resolveObject
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::resolveNode
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::setSimpleFields
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::setSingleValuedAssociationFields
     */
    public function testSimpleCase()
    {
        $me = $this->makeUser();
        $brother = $this->makeUser('brother');
        $sister = $this->makeUser('sister');
        $mother = $this->makeUser('mother');
        $me->siblings = [$brother, $sister];
        $me->closestRelative = $mother;
        /** @var User $user */
        $user = $this->resolver->resolveObject($me, UserInterface::class);
        $this->assertEquals($me->username, $user->getUsername());
        $this->assertEquals('1990-06-06', $user->getBirthDate()->format('Y-m-d'));
        $this->assertEquals($brother->username, $user->getSiblings()->get(0)->getUsername());
        $this->assertEquals($sister->username, $user->getSiblings()->get(1)->getUsername());
        $this->assertEquals($mother->email, $user->getClosestRelative()->getEmail());
        $this->assertContains('role1', $user->getRoles());
        $this->assertContains('role2', $user->getRoles());
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getVariationsOfPropertyTitle
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::camelToSnake
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::snakeToCamel
     */
    public function testGetVariationsOfPropertyTitle()
    {
        list($camel, $snake) = $this->invokeMethod('getVariationsOfPropertyTitle', ['testProperty']);
        $this->assertEquals('testProperty', $camel);
        $this->assertEquals('test_property', $snake);
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getFromCollectionById
     */
    public function testGetFromCollectionById()
    {
        $collection = new ArrayCollection();
        $collection->add(Tag::makeTag(1));
        $collection->add(Tag::makeTag(2));
        $tag = $this->invokeMethod('getFromCollectionById', [$collection, 2]);
        $this->assertInstanceOf(Tag::class, $tag);
        $this->assertEquals(2, $tag->getId());
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getSerializedName
     */
    public function testGetSerializedName()
    {
        $names = $this->invokeMethod('getSerializedName', ['title', Tag::class]);
        $this->assertCount(2, $names);
        $this->assertContains('title', $names);
        $this->assertContains('name', $names);
        $names = $this->invokeMethod('getSerializedName', ['id', Tag::class]);
        $this->assertContains('id', $names);
        $this->assertCount(1, $names);
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::readProperty
     */
    public function testReadProperty()
    {
        $source = $this->makeUser('test');
        list($value, $valueSet) = $this->invokeMethod('readProperty', ['username', $source, User::class]);
        $this->assertEquals('test', $value);
        $this->assertTrue($valueSet);
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::toDate
     */
    public function testToDate()
    {
        $date1 = '2016-01-01T06:20+03:00';
        $date2 = '2016-01-01';
        $date3 = '2016-01-01 06:00:00UTC';
        /** @var \DateTime $date1obj */
        $date1obj = $this->invokeMethod('toDate', [$date1]);
        $date2obj = $this->invokeMethod('toDate', [$date2]);
        $date3obj = $this->invokeMethod('toDate', [$date3]);
        $this->assertEquals('2016-01-01T06:20:00+0300', $date1obj->format(\DateTime::ISO8601));
        $this->assertEquals('2016-01-01', $date2obj->format('Y-m-d'));
        $this->assertEquals('2016-01-01T06:00:00+0000', $date3obj->format(\DateTime::ISO8601));
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::updateCollection
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::setCollectionValuedAssociationFields
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getTarget
     */
    public function testMergeCollections()
    {
        $userSource = User::getUser(11);
        $userSource->addTag(Tag::makeTag(1));
        $userSource->addTag(Tag::makeTag(2));
        $this->em->persist($userSource);
        $this->em->flush();
        $name = $userSource->getUsername();
        $obj = $this->makeUser($name);
        $obj->id = 11;
        $obj->tags = [$this->makeTag(1), $this->makeTag(3, false)];
        $user = null;
        try {
            $user = $this->resolver->resolveObject($obj, UserInterface::class);
        } catch (\Exception $x) {
            $this->fail($x->getMessage());
        }

        $tags = $user->getTags();
        $this->assertEquals(2, $tags->count());
        $this->assertEquals(1, $tags->get(0)->getId());
        $this->assertTrue($tags->get(0)->getIsEnabled());
        $this->assertEquals(3, $tags->get(1)->getId());
        $this->assertFalse($tags->get(1)->getIsEnabled());
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::resolveObject
     */
    public function testExceptions()
    {
        $user = $this->makeUser();
        try {
            $this->resolver->resolveObject($user, 'SomeClassWhichDoesNotExists');
        } catch (\Exception $ex) {
            $this->assertInstanceOf(ClassNotFoundException::class, $ex);
        }
        $ref = new \stdClass();
        $ref->id = 4;
        $ref->title = 'test';
        try {
            $this->resolver->resolveObject($user, Reference::class);
        } catch (\Exception $ex) {
            $this->assertInstanceOf(UnableToSetIdException::class, $ex);
        }

        try {
            $this->resolver->resolveObject($user, Reference::class, null, 0);
        } catch (\Exception $ex) {
            $this->fail($ex->getMessage());
        }

        $ref->description = 'test';
        unset($ref->id);
        try {
            $this->resolver->resolveObject($user, Reference::class);
        } catch (\Exception $ex) {
            $this->assertInstanceOf(UnableToSetValueException::class, $ex);
        }
    }

    /**
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::getProperties
     * @covers \AveSystems\ObjectResolverBundle\ObjectResolver::collectPropertiesForClass
     */
    public function testGetProperties()
    {
        $collections = $this->invokeMethod('getProperties', [User::class, ObjectResolver::COLLECTION]);
        $this->assertCount(2, $collections);
        $collections = array_keys($collections);
        $this->assertContains('tags', $collections);
        $this->assertContains('siblings', $collections);

        $simple = $this->invokeMethod('getProperties', [User::class, ObjectResolver::SIMPLE]);
        $this->assertCount(5, $simple);
    }

    public function testActualCase()
    {
        $event = new Event();
        $event->setTitle('Test');
        $poll = new Poll();
        $opt1 = new PollOption();
        $opt1->setTitle('1');
        $opt2 = new PollOption();
        $opt2->setTitle('2');
        $poll->setTitle('Test')->addOption($opt1)->addOption($opt2);
        $event->addPoll($poll);
        $this->em->persist($event);
        $this->em->flush();
        $this->em->clear();
        /** @var Event $event */
        $event = $this->em->find(Event::class, 1);
        $this->assertFalse($event->getPolls()->get(0)->getOnMonitor());
        $this->em->clear();
        $content = '
        {
           "id":1,
           "title":"test",
           "isMultiple":false,
           "state":0,
           "onMonitor":true,
           "options":[
              {
                 "id":1,
                 "title":"1",
                 "$$hashKey":"object:22"
              },
              {
                 "id":2,
                 "title":"2",
                 "$$hashKey":"object:23"
              }
           ],
           "event":{
              "id":1
           },
           "$$hashKey":"object:16"
        }';
        try {
            $resolved = $this->resolver->resolveObject(json_decode($content), Poll::class);
            $this->assertEquals(1, $resolved->getId());
            $this->assertTrue($resolved->getOnMonitor());
        } catch (\Exception $ex) {
            $this->fail($ex->getMessage());
        }
    }

    protected static function getKernelClass()
    {
        return AppKernel::class;
    }

    protected function migrateSchema()
    {
        $app = new Application(self::$kernel);
        $app->add(new UpdateSchemaDoctrineCommand());
        $arguments = [
            'command' => 'doctrine:schema:update',
            '--force' => true,
        ];

        $input = new ArrayInput($arguments);

        $command = $app->find('doctrine:schema:update');
        $output = new BufferedOutput();
        $command->run($input, $output);
    }

    /**
     * @param string $name
     *
     * @return \stdClass
     */
    protected function makeUser($name = 'test')
    {
        $data = new \stdClass();
        $data->username = $name;
        $data->email = $name.'@mail.com';
        $data->roles = ['role1', 'role2'];
        $data->birthDate = '1990-06-06T18:00+03:00';

        return $data;
    }

    protected function makeTag($id, $enabled = true)
    {
        $tag = new \stdClass();
        $tag->id = $id;
        $tag->title = 'tag'.$id;
        $tag->isEnabled = $enabled;

        return $tag;
    }
}
