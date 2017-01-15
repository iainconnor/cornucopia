<?php

include(dirname(__FILE__) . "/vendor/autoload.php");

class Foo {

	/**
	 * @var string A string.
	 */
	public $lorem;

	/**
	 * @var string[] An array or strings.
	 */
	public $ipsum;

	/**
	 * @param array<int> $foo An array of ints.
	 * @param null|string bar A null value.
	 */
	public function dolor(array $foo) {

	}
}

$reflectedClass = new \ReflectionClass(Foo::class);
$reflectedClassInstance = $reflectedClass->newInstance();

\Doctrine\Common\Annotations\AnnotationRegistry::registerAutoloadNamespace('\IainConnor\Cornucopia\Annotations', dirname(__FILE__) . "/src");
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