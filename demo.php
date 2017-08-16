<?php

include(dirname(__FILE__) . "/vendor/autoload.php");

class Foo {

	/**
	 * @var string A string.
	 */
	public $lorem = "Hello!";

	/**
	 * @var string[] An array or strings.
	 */
	public $ipsum;

    /**
     * @param array <int> $foo An array of ints.
     * @param null|string bar A null value.
     *
     * @return bool|null    Some default with a really
     *                      long multi-line description.
     */
	public function dolor(array $foo) {
        return rand(0,1) ? null : true;
	}
}

$reflectedClass = new \ReflectionClass(Foo::class);
$reflectedClassInstance = $reflectedClass->newInstance();

$annotationReader = new \IainConnor\Cornucopia\CachedReader(
	new \IainConnor\Cornucopia\AnnotationReader(),
	new \Doctrine\Common\Cache\ArrayCache()
);

foreach ($reflectedClass->getProperties() as $reflectedProperty) {
	foreach ($annotationReader->getPropertyAnnotations($reflectedProperty) as $propertyAnnotation) {
		var_dump ( $propertyAnnotation );
	}
}

foreach ($reflectedClass->getMethods() as $reflectionMethod) {
	foreach ($annotationReader->getMethodAnnotations($reflectionMethod) as $methodAnnotation) {
		var_dump ( $methodAnnotation );
	}
}