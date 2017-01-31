<?php


namespace IainConnor\Cornucopia\Annotations;


use IainConnor\Cornucopia\Type;

class TypeHint {

	const ARRAY_TYPE = 'array';

	const ARRAY_TYPE_SHORT = '[]';

	const TYPE_SEPARATOR = '|';

	/**
	 * Map of possible names to a sanitized version.
	 *
	 * @var array
	 */
	public static $basicTypes = [
		'string' => 'string',
		'int' => 'int',
		'integer' => 'int',
		'float' => 'float',
		'bool' => 'bool',
		'boolean' => 'bool',
		'null' => NULL
	];

	/** @var Type[] */
	public $types;

	/** @var string|null */
	public $description;

	/** @var string */
	public $variableName;

	/**
	 * TypeHint constructor.
	 *
	 * @param Type[] $types
	 * @param $variableName
	 * @param null|string $description
	 */
	public function __construct(array $types, $variableName, $description = null) {

		$this->types = $types;
		$this->variableName = $variableName;
		$this->description = $description;
	}

    /**
     * Sanitizes the given type into a known value, if possible, handling checking for arrays.
     *
     * @param string $destinationClass
     * @param string $typeString
     * @param array $imports
     * @param null $variableName
     * @return bool|TypeHint
     */
	public static function parseToInstanceOf($destinationClass, $typeString, array $imports, $variableName = null) {
		$typeParts = array_map(function($element) {
			return trim($element);
		}, explode(" ", $typeString, $variableName === null && $destinationClass == InputTypeHint::class ? 3 : 2));

		$typeInfoStrings = explode(TypeHint::TYPE_SEPARATOR, $typeParts[0]);

		if ( $variableName === null && $destinationClass == InputTypeHint::class ) {
			$variableName = $typeParts[1];
			$description = count($typeParts) == 3 ? $typeParts[2] : null;
		} else {
			$description = count($typeParts) == 2  ? $typeParts[1] : null;
		}

		/** @var Type[] $types */
		$types = [];
		foreach ( $typeInfoStrings as $typeInfoString ) {
			if ( false !== $sanitizedBaseType = TypeHint::getSanitizedName($typeInfoString, $imports) ) {
				$type = new Type();
				$type->type = $sanitizedBaseType;

				if ($sanitizedBaseType == TypeHint::ARRAY_TYPE) {
					$genericType = null;

					// Check for array generic type indicators.
					if (substr($typeInfoString, -2) == TypeHint::ARRAY_TYPE_SHORT) {
						$genericType = trim(substr($typeInfoString, 0, -2));
					} else if (preg_match("/array<(.*?)>/", $typeString, $matches)) {
						$genericType = trim($matches[1]);
					}

					if (false !== $sanitizedGenericType = TypeHint::getSanitizedName($genericType, $imports)) {
						$type->genericType = $sanitizedGenericType;
					}
				}

				$types[] = $type;
			}
		}

		if ( count($types) ) {

			return new $destinationClass($types, $destinationClass == OutputTypeHint::class ? null : ltrim($variableName, '$'), $description);
		}

		return false;
	}

	/**
	 * Sanitizes the given type string into a known value, if possible.
	 *
	 * @param $string
	 * @param array $imports
	 * @return bool|string
	 */
	private static function getSanitizedName($string, array $imports) {
		if (is_null($string)) {

			return false;
		}

		if (array_key_exists($string, TypeHint::$basicTypes)) {

			return TypeHint::$basicTypes[$string];
		}

		if ($string == TypeHint::ARRAY_TYPE) {

			return TypeHint::ARRAY_TYPE;
		}

		if (substr($string, -2) == TypeHint::ARRAY_TYPE_SHORT) {

			return TypeHint::ARRAY_TYPE;
		}

		if (preg_match("/array<(.*?)>/", $string, $matches)) {

			return TypeHint::ARRAY_TYPE;
		}

		if (class_exists($string, false)) {

			return $string;
		}

		if (class_exists($imports['__NAMESPACE__'] . '\\' . $string)) {

			return $imports['__NAMESPACE__'] . '\\' . $string;
		}

		foreach ($imports as $import) {
			if (substr($import, strrpos($import, '\\') + 1) == $string) {

				return $import;
			}
		}

		return false;
	}
}