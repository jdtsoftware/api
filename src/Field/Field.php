<?php

declare(strict_types=1);

namespace JDT\Api\Field;

/**
 * Class Field.
 */
class Field
{
    protected $field;
    protected $payloadValidation;
    protected $filter;
    protected $sort;
    protected $filterValueValidation;

    /**
     * Field constructor.
     * @param string $field
     * @param string|array|null $payloadValidation
     * @param bool $filter
     * @param bool $sort
     * @param string|null $filterValueValidation
     */
    public function __construct(
        string $field,
        $payloadValidation = '',
        bool $filter = false,
        bool $sort = false,
        string $filterValueValidation = ''
    ) {
        $this->field = $field;
        $this->payloadValidation = $payloadValidation;
        $this->filter = $filter;
        $this->sort = $sort;
        $this->filterValueValidation = $filterValueValidation;
    }

    /**
     * @return string
     */
    public function getField():string
    {
        return $this->field;
    }

    /**
     * @return null|string
     */
    public function getPayloadValidation()
    {
        return $this->payloadValidation;
    }

    /**
     * @return bool
     */
    public function canFilter():bool
    {
        return $this->filter;
    }

    /**
     * @return null|string
     */
    public function getFilterValueValidation():string
    {
        return $this->filterValueValidation;
    }

    /**
     * @return bool
     */
    public function canSort():bool
    {
        return $this->sort;
    }
}
