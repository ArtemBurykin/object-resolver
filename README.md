### General

This bundle provides class ObjectResolver is useful for API endpoints.
ObjectResolver can simultaneously perform tasks:
* To convert decoded json or plain object to object of appropriate class
* To merge plain object to it's persisted copy
* To convert datetime value in ISO8601 to DateTime

You can configure behaviour of this class, using binary flags:
```php
/**
 * If set RESOLVER throws an Exception if impossible to set ID to target
 */
const ERROR_IF_CANT_SET_ID_FLAG = 0b0001;

/**
 * If set RESOLVER merges object with it's persistent copy else just performs convertion
 */
const PERSIST_FLAG = 0b0010;

/**
 * If set RESOLVER will not try to resolve provided class name using ORM
 */
const SKIP_RESOLVING_CLASS_NAME_FLAG = 0b0100;
```

Usage:
```php
//Converts object to class Object, if there is no setter for ID nothing will happen
$resolver->resolveObject($object, Object::class, null, 0);

//Merges plain object to Entity, no attempts to resolve entity name needed
$resolver->resolveObject($object, Entity::class, null, ObjectResolver::SKIP_RESOLVING_CLASS_NAME_FLAG );

//Merges plain object to Entity, entity name resolving needed
$resolver->resolveObject($user, UserInterface::class);

```

###Installation
