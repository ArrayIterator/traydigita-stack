<?php
declare(strict_types=1);

namespace TrayDigita\WP\Stack\Source\Libs\Collections\RegistryDefinitions;

use TrayDigita\WP\Stack\Source\Interfaces\Exceptions\ExceptionInterface;
use TrayDigita\WP\Stack\Source\Interfaces\Collections\RegistryInterface;

class Reference extends AbstractDefinition
{
    /**
     * @var ExceptionInterface $failingException The exception that caused the definition to fail
     */
    private ExceptionInterface $failingException;

    /**
     * @var mixed $result The result of the resolved definition
     */
    public readonly string $reference;

    /**
     * @param string $reference The reference to be used for resolving the definition
     */
    final public function __construct(string $reference)
    {
        $this->reference = $reference;
    }

    /**
     * Returns the reference name used for resolving the definition
     *
     * @return string The reference name
     */
    public function getReferenceName(): string
    {
        return $this->reference;
    }

    /**
     * Creates a new instance of the Reference class
     *
     * @param string $reference The reference to be used for resolving the definition
     * @return self
     */
    public static function create(string $reference): self
    {
        return new static($reference);
    }

    /**
     * @param RegistryInterface $registry
     * @param bool $autoResolveParameters
     * @return mixed
     * @throws ExceptionInterface
     */
    public function value(RegistryInterface $registry, bool $autoResolveParameters = true): mixed
    {
        if (isset($this->failingException)) {
            throw $this->failingException;
        }
        if ($this->isResolved()) {
            return $this->value??null;
        }
        try {
            $this->value = $registry->get($this->reference);
            $this->resolved = true;
            return $this->value;
        } catch (ExceptionInterface $e) {
            $this->failingException = $e;
            throw $e;
        }
    }
}
