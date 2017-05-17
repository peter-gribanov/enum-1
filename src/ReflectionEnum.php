<?php

/**
 * GpsLab component.
 *
 * @author    Peter Gribanov <info@peter-gribanov.ru>
 * @copyright Copyright (c) 2011, Peter Gribanov
 * @license   http://opensource.org/licenses/MIT
 */

namespace GpsLab\Component\Enum;

use GpsLab\Component\Enum\Exception\BadMethodCallException;
use GpsLab\Component\Enum\Exception\OutOfEnumException;

abstract class ReflectionEnum implements Enum, \Serializable
{
    /**
     * @var mixed
     */
    private $value = '';

    /**
     * @var Enum[]
     */
    private static $instances = [];

    /**
     * @var mixed[][]
     */
    private static $create_methods = [];

    /**
     * @var mixed[][]
     */
    private static $is_methods = [];

    /**
     * @var mixed[][]
     */
    private static $keys = [];

    /**
     * @param mixed $value
     */
    protected function __construct($value)
    {
        if (!static::isValid($value)) {
            throw OutOfEnumException::create($value, static::class);
        }

        $this->value = $value;
    }

    /**
     * @param mixed $value
     *
     * @return Enum
     */
    public static function create($value)
    {
        $key = static::class.'|'.$value;

        // limitation of count object instances
        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new static($value);
        }

        return self::$instances[$key];
    }

    /**
     * @return mixed
     */
    public function value()
    {
        return $this->value;
    }

    /**
     * Available values.
     *
     * @return Enum[]
     */
    public static function values()
    {
        $values = [];
        foreach (self::keys() as $key => $value) {
            $values[$key] = static::create($value);
        }

        return $values;
    }

    /**
     * @param Enum $enum
     *
     * @return bool
     */
    public function equals(Enum $enum)
    {
        return $this->value() === $enum->value() && static::class == get_class($enum);
    }

    /**
     * Is value supported.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public static function isValid($value)
    {
        return in_array($value, self::createMethods(), true);
    }

    /**
     * Get choices for radio group.
     *
     * <code>
     * {
     *   value1: 'Readable value 1',
     *   value2: 'Readable value 2',
     * }
     * </code>
     *
     * @return array
     */
    public static function choices()
    {
        $choices = [];
        foreach (self::createMethods() as $value) {
            $choices[$value] = (string) static::create($value);
        }

        return $choices;
    }

    /**
     * Return readable value.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->key();
    }

    final public function __clone()
    {
        throw new \LogicException('Enumerations are not cloneable');
    }

    /**
     * @return mixed
     */
    public function serialize()
    {
        return $this->value;
    }

    /**
     * @param mixed $data
     */
    public function unserialize($data)
    {
        static::create($this->value = $data);
    }

    /**
     * @param string $class
     */
    private static function detectConstants($class)
    {
        if (!isset(self::$create_methods[$class])) {
            self::$create_methods[$class] = [];
            self::$is_methods[$class] = [];
            self::$keys[$class] = [];

            $constants = [];
            $reflection = new \ReflectionClass($class);

            if (PHP_VERSION_ID >= 70100) {
                // Since PHP-7.1 visibility modifiers are allowed for class constants
                // for enumerations we are only interested in public once.
                foreach ($reflection->getReflectionConstants() as $refl_constant) {
                    if ($refl_constant->isPublic()) {
                        $constants[$refl_constant->getName()] = $refl_constant->getValue();
                    }
                }
            } else {
                // In PHP < 7.1 all class constants were public by definition
                foreach ($reflection->getConstants() as $constant => $constant_value) {
                    $constants[$constant] = $constant_value;
                }
            }

            foreach ($constants as $constant => $constant_value) {
                self::$keys[$class][$constant] = $constant_value;

                // second parameter of ucwords() is not supported on HHVM
                $constant = str_replace(' ', '', ucwords(str_replace('_', ' ', strtolower($constant))));

                self::$is_methods[$class]['is'.$constant] = $constant_value;
                self::$create_methods[$class][lcfirst($constant)] = $constant_value;
            }
        }
    }

    /**
     * @return array
     */
    private static function createMethods()
    {
        self::detectConstants(static::class);

        return self::$create_methods[static::class];
    }

    /**
     * @return array
     */
    private static function isMethods()
    {
        self::detectConstants(static::class);

        return self::$is_methods[static::class];
    }

    /**
     * @return array
     */
    private static function keys()
    {
        self::detectConstants(static::class);

        return self::$keys[static::class];
    }

    /**
     * @return string
     */
    private function key()
    {
        return array_search($this->value(), self::keys());
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return bool
     */
    public function __call($method, array $arguments = [])
    {
        if (!isset(self::isMethods()[$method])) {
            throw BadMethodCallException::noMethod($method, static::class);
        }

        return $this->value === self::isMethods()[$method];
    }

    /**
     * @param string $method
     * @param array  $arguments
     *
     * @return Enum
     */
    public static function __callStatic($method, array $arguments = [])
    {
        if (!isset(self::createMethods()[$method])) {
            throw BadMethodCallException::noStaticMethod($method, static::class);
        }

        return static::create(self::createMethods()[$method]);
    }
}
