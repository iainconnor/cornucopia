<?php
namespace IainConnor\Cornucopia;

use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\Common\Annotations\AnnotationException;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Annotations\DocParser;
use Doctrine\Common\Annotations\PhpParser;
use Doctrine\Common\Annotations\Reader;
use IainConnor\Cornucopia\Annotations\InputTypeHint;
use IainConnor\Cornucopia\Annotations\OutputTypeHint;
use IainConnor\Cornucopia\Annotations\ReturnPlaceholder;
use IainConnor\Cornucopia\Annotations\TypeHint;
use IainConnor\Cornucopia\Annotations\VarParamPlaceholder;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

class AnnotationReader implements Reader
{
	/**
	 * Global map for imports.
	 *
	 * @var array
	 */
	private static $globalImports = array(
		'ignoreannotation' => 'Doctrine\Common\Annotations\Annotation\IgnoreAnnotation',
		'var' => 'IainConnor\Cornucopia\Annotations\VarParamPlaceholder',
		'param' => 'IainConnor\Cornucopia\Annotations\VarParamPlaceholder',
        'return' => 'IainConnor\Cornucopia\Annotations\ReturnPlaceholder',
	);

	/**
	 * A list with annotations that are not causing exceptions when not resolved to an annotation class.
	 *
	 * The names are case sensitive.
	 *
	 * @var array
	 */
	private static $globalIgnoredNames = array(
		// Annotation tags
		'Annotation' => true, 'Attribute' => true, 'Attributes' => true,
		/* Can we enable this? 'Enum' => true, */
		'Required' => true,
		'Target' => true,
		// Widely used tags (but not existent in phpdoc)
		'fix' => true , 'fixme' => true,
		'override' => true,
		// PHPDocumentor 1 tags
		'abstract'=> true, 'access'=> true,
		'code' => true,
		'deprec'=> true,
		'endcode' => true, 'exception'=> true,
		'final'=> true,
		'ingroup' => true, 'inheritdoc'=> true, 'inheritDoc'=> true,
		'magic' => true,
		'name'=> true,
		'toc' => true, 'tutorial'=> true,
		'private' => true,
		'static'=> true, 'staticvar'=> true, 'staticVar'=> true,
		'throw' => true,
		// PHPDocumentor 2 tags.
		'api' => true, 'author'=> true,
		'category'=> true, 'copyright'=> true,
		'deprecated'=> true,
		'example'=> true,
		'filesource'=> true,
		'global'=> true,
		'ignore'=> true, /* Can we enable this? 'index' => true, */ 'internal'=> true,
		'license'=> true, 'link'=> true,
		'method' => true,
		'package'=> true, 'property' => true, 'property-read' => true, 'property-write' => true,
		'see'=> true, 'since'=> true, 'source' => true, 'subpackage'=> true,
		'throws'=> true, 'todo'=> true, 'TODO'=> true,
		'usedby'=> true, 'uses' => true,
		'version'=> true,
		// PHPUnit tags
		'codeCoverageIgnore' => true, 'codeCoverageIgnoreStart' => true, 'codeCoverageIgnoreEnd' => true,
		// PHPCheckStyle
		'SuppressWarnings' => true,
		// PHPStorm
		'noinspection' => true,
		// PEAR
		'package_version' => true,
		// PlantUML
		'startuml' => true, 'enduml' => true,
	);

	/**
	 * A list with annotations that are not causing exceptions when not resolved to an annotation class.
	 *
	 * The names are case sensitive.
	 *
	 * @var array
	 */
	private static $globalIgnoredNamespaces = array();
	/**
	 * Annotations parser.
	 *
	 * @var \Doctrine\Common\Annotations\DocParser
	 */
	private $parser;
	/**
	 * Annotations parser used to collect parsing metadata.
	 *
	 * @var \Doctrine\Common\Annotations\DocParser
	 */
	private $preParser;
	/**
	 * PHP parser used to collect imports.
	 *
	 * @var \Doctrine\Common\Annotations\PhpParser
	 */
	private $phpParser;
	/**
	 * In-memory cache mechanism to store imported annotations per class.
	 *
	 * @var array
	 */
	private $imports = array();
	/**
	 * In-memory cache mechanism to store ignored annotations per class.
	 *
	 * @var array
	 */
	private $ignoredAnnotationNames = array();
    /** @var string[] */
    private $ignoredInputTypes;
    /** @var string[] */
    private $ignoredOutputTypes;

    /**
     * Constructor.
     *
     * Initializes a new AnnotationReader.
     *
     * @param DocParser $parser
     * @param string[] $ignoredInputTypes
     * @param string[] $ignoredOutputTypes
     * @throws AnnotationException
     */
    public function __construct(DocParser $parser = null, array $ignoredInputTypes = [], array $ignoredOutputTypes = [])
	{
		if (extension_loaded('Zend Optimizer+') && (ini_get('zend_optimizerplus.save_comments') === "0" || ini_get('opcache.save_comments') === "0")) {
			throw AnnotationException::optimizerPlusSaveComments();
		}

		if (extension_loaded('Zend OPcache') && ini_get('opcache.save_comments') == 0) {
			throw AnnotationException::optimizerPlusSaveComments();
		}

		if (PHP_VERSION_ID < 70000) {
			if (extension_loaded('Zend Optimizer+') && (ini_get('zend_optimizerplus.load_comments') === "0" || ini_get('opcache.load_comments') === "0")) {
				throw AnnotationException::optimizerPlusLoadComments();
			}

			if (extension_loaded('Zend OPcache') && ini_get('opcache.load_comments') == 0) {
				throw AnnotationException::optimizerPlusLoadComments();
			}
		}

		$ignoreClass = new ReflectionClass(IgnoreAnnotation::class);
		AnnotationRegistry::registerFile($ignoreClass->getFileName());

		$dummyClass = new ReflectionClass(VarParamPlaceholder::class);
		AnnotationRegistry::registerFile($dummyClass->getFileName());

        $dummyClass = new ReflectionClass(ReturnPlaceholder::class);
        AnnotationRegistry::registerFile($dummyClass->getFileName());

        $this->ignoredInputTypes = $ignoredInputTypes;
        $this->ignoredOutputTypes = $ignoredOutputTypes;

		$this->parser = $parser ?: new DocParser();

		$this->preParser = new DocParser;

		$this->preParser->setImports(self::$globalImports);
		$this->preParser->setIgnoreNotImportedAnnotations(true);

		$this->phpParser = new PhpParser();
	}

    /**
     * Add a new annotation to the globally ignored annotation names with regard to exception handling.
     *
     * @param string $name
     */
    static public function addGlobalIgnoredName($name)
    {
        self::$globalIgnoredNames[$name] = true;
    }

    /**
     * Add a new annotation to the globally ignored annotation namespaces with regard to exception handling.
     *
     * @param string $namespace
     */
    static public function addGlobalIgnoredNamespace($namespace)
    {
        self::$globalIgnoredNamespaces[$namespace] = true;
    }

    public static function getVendorRoot()
    {

        return static::getProjectRoot() . "/vendor";
    }

    public static function getProjectRoot()
    {

        return static::getSrcRoot() . "/..";
    }

    public static function getSrcRoot()
    {

        $path = dirname(__FILE__);

        return $path . "/../..";
    }

    /**
     * {@inheritDoc}
     */
    public function getClassAnnotation(ReflectionClass $class, $annotationName)
    {
        $annotations = $this->getClassAnnotations($class);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof $annotationName) {
                return $annotation;
            }
        }

        return null;
    }

	/**
	 * {@inheritDoc}
	 */
	public function getClassAnnotations(ReflectionClass $class)
	{
		$this->parser->setTarget(Target::TARGET_CLASS);
		$this->parser->setImports($this->getClassImports($class));
		$this->parser->setIgnoredAnnotationNames($this->getIgnoredAnnotationNames($class));
		$this->parser->setIgnoredAnnotationNamespaces(self::$globalIgnoredNamespaces);

		return $this->parser->parse($class->getDocComment(), 'class ' . $class->getName());
	}

    /**
     * Retrieves imports.
     *
     * @param \ReflectionClass $class
     *
     * @return array
     */
    public function getClassImports(ReflectionClass $class)
    {
        if (isset($this->imports[$name = $class->getName()])) {
            return $this->imports[$name];
        }

        $this->collectParsingMetadata($class);

        return $this->imports[$name];
    }

    /**
     * Collects parsing metadata for a given class.
     *
     * @param \ReflectionClass $class
     */
    private function collectParsingMetadata(ReflectionClass $class)
    {
        $ignoredAnnotationNames = self::$globalIgnoredNames;
        $annotations = $this->preParser->parse($class->getDocComment(), 'class ' . $class->name);

        foreach ($annotations as $annotation) {
            if ($annotation instanceof IgnoreAnnotation) {
                foreach ($annotation->names AS $annot) {
                    $ignoredAnnotationNames[$annot] = true;
                }
            }
        }

        $name = $class->getName();

        $this->imports[$name] = array_merge(
            self::$globalImports,
            $this->phpParser->parseClass($class),
            array('__NAMESPACE__' => $class->getNamespaceName())
        );

        $this->ignoredAnnotationNames[$name] = $ignoredAnnotationNames;
    }

    /**
     * Returns the ignored annotations for the given class.
     *
     * @param \ReflectionClass $class
     *
     * @return array
     */
    private function getIgnoredAnnotationNames(ReflectionClass $class)
    {
        if (isset($this->ignoredAnnotationNames[$name = $class->getName()])) {
            return $this->ignoredAnnotationNames[$name];
        }

        $this->collectParsingMetadata($class);

        return $this->ignoredAnnotationNames[$name];
    }

	/**
	 * {@inheritDoc}
	 */
    public function getPropertyAnnotation(ReflectionProperty $property, $annotationName)
	{
        $annotations = $this->getPropertyAnnotations($property);

		foreach ($annotations as $annotation) {
			if ($annotation instanceof $annotationName) {
				return $annotation;
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getPropertyAnnotations(ReflectionProperty $property)
	{
		$class   = $property->getDeclaringClass();
		$context = 'property ' . $class->getName() . "::\$" . $property->getName();
		$defaultProperties = $class->getDefaultProperties();

		$this->parser->setTarget(Target::TARGET_PROPERTY);
		$propertyImports = $this->getPropertyImports($property);
		$this->parser->setImports($propertyImports);
		$this->parser->setIgnoredAnnotationNames($this->getIgnoredAnnotationNames($class));
		$this->parser->setIgnoredAnnotationNamespaces(self::$globalIgnoredNamespaces);

		$propertyComment = $property->getDocComment();

		$results = $this->parser->parse($propertyComment, $context);

		if (false !== strpos($propertyComment, '@var') && preg_match('/@var\s+(.*+)/', $propertyComment, $matches)) {
            if (false !== $typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $matches[1], $propertyImports, $property->getName(), (array_key_exists($property->getName(), $defaultProperties) ? $defaultProperties[$property->getName()] : null), $this->ignoredInputTypes)) {
				foreach ( $results as $key => $result ) {
				    // VarParamPlaceholder is used as a placeholder until we replace it with an InputTypeHint.
					if ( $result instanceof VarParamPlaceholder ) {
						$results[$key] = $typeHint;
						break;
					}
				}
			}
		}

		return $results;
	}

    /**
     * Retrieves imports for properties.
     *
     * @param \ReflectionProperty $property
     *
     * @return array
     */
    public function getPropertyImports(ReflectionProperty $property)
    {
        $class = $property->getDeclaringClass();
        $classImports = $this->getClassImports($class);
        if (!method_exists($class, 'getTraits')) {
            return $classImports;
        }

        $traitImports = array();

        foreach ($class->getTraits() as $trait) {
            if ($trait->hasProperty($property->getName())) {
                $traitImports = array_merge($traitImports, $this->phpParser->parseClass($trait));
            }
        }

        return array_merge($classImports, $traitImports);
    }

	/**
	 * {@inheritDoc}
	 */
    public function getMethodAnnotation(ReflectionMethod $method, $annotationName)
	{
        $annotations = $this->getMethodAnnotations($method);

		foreach ($annotations as $annotation) {
			if ($annotation instanceof $annotationName) {
				return $annotation;
			}
		}

		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getMethodAnnotations(ReflectionMethod $method)
	{
		$class   = $method->getDeclaringClass();
		$context = 'method ' . $class->getName() . '::' . $method->getName() . '()';

		$this->parser->setTarget(Target::TARGET_METHOD);
		$methodImports = $this->getMethodImports($method);
		$this->parser->setImports($methodImports);
		$this->parser->setIgnoredAnnotationNames($this->getIgnoredAnnotationNames($class));
		$this->parser->setIgnoredAnnotationNamespaces(self::$globalIgnoredNamespaces);

		$defaultValues = [];
		foreach ( $method->getParameters() as $parameter ) {
		    if ( $parameter->isDefaultValueAvailable() && $parameter->getDefaultValue() ) {
		        $defaultValues[$parameter->getName()] = $parameter->getDefaultValue();
            }
        }

		$methodComment = $method->getDocComment();

		$results = $this->parser->parse($methodComment, $context);

		if (false !== strpos($methodComment, '@param') && preg_match_all('/@param\s+(.*+)/', $methodComment, $matches)) {
			foreach ( $matches[1] as $match ) {
                if (false !== $typeHint = TypeHint::parseToInstanceOf(InputTypeHint::class, $match, $methodImports, null, null, $this->ignoredInputTypes)) {
					foreach ( $results as $key => $result ) {
                        // VarParamPlaceholder is used as a placeholder until we replace it with a InputTypeHint.
						if ( $result instanceof VarParamPlaceholder ) {
                            if ( array_key_exists($typeHint->variableName, $defaultValues) ) {
                                $typeHint->defaultValue = $defaultValues[$typeHint->variableName];
                            }

							$results[$key] = $typeHint;
							break;
						}
					}
				}
			}
		}

        if (false !== strpos($methodComment, '@return') && preg_match_all('/@return\s+(.*+)/', $methodComment, $matches)) {
            foreach ( $matches[1] as $match ) {
                if (false !== $typeHint = TypeHint::parseToInstanceOf(OutputTypeHint::class, $match, $methodImports, null, null, $this->ignoredOutputTypes)) {
                    foreach ( $results as $key => $result ) {
                        // ReturnPlaceholder is used as a placeholder until we replace it with a OutputTypeHint.
                        if ( $result instanceof ReturnPlaceholder ) {
                            $results[$key] = $typeHint;
                            break;
                        }
                    }
                }
            }
        }

		return $results;
	}

	/**
	 * Retrieves imports for methods.
	 *
	 * @param \ReflectionMethod $method
	 *
	 * @return array
	 */
	public function getMethodImports(ReflectionMethod $method)
	{
		$class = $method->getDeclaringClass();
		$classImports = $this->getClassImports($class);
		if (!method_exists($class, 'getTraits')) {
			return $classImports;
		}

		$traitImports = array();

		foreach ($class->getTraits() as $trait) {
			if ($trait->hasMethod($method->getName())
				&& $trait->getFileName() === $method->getFileName()
			) {
				$traitImports = array_merge($traitImports, $this->phpParser->parseClass($trait));
			}
		}

		return array_merge($classImports, $traitImports);
	}
}
