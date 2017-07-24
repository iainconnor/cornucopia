<?php


namespace IainConnor\Cornucopia;


use IainConnor\Cornucopia\Annotations\TypeHint;

class Type {
    public $type;
    public $genericType;

    /**
     * Returns developer readable typehint.
     * @return string
     */
    public function __toString()
    {
        return $this->type == TypeHint::ARRAY_TYPE ? ($this->genericType ? $this->genericType . TypeHint::ARRAY_TYPE_SHORT : TypeHint::ARRAY_TYPE) : ($this->type ?: TypeHint::NULL_TYPE);
    }

    /**
     * @return string|null
     */
    public function getTypeOfInterest()
    {
        return $this->genericType ?: $this->type;
    }
}