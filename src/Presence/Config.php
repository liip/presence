<?php

namespace Presence;

/**
 * Sets and loads configuration data.
 */
class Config
{
    /**
     * Get configuration property.
     *
     * @param string $name  The property name.
     * @param mixed  $value The property value.
     *
     * @return void
     */
    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    /**
     * Get configuration  property.
     *
     * @param string $name The property name.
     *
     * @return mixed $data The property value.
     */
    public function __get($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }
    }
}
