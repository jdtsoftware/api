<?php

declare(strict_types=1);

namespace JDT\Api\Field;

/**
 * Class FieldList.
 */
class FieldList
{
    protected $fields = [];
    protected $payloadValidation = [];
    protected $sort = [];
    protected $filters = [];

    /**
     * Add a field to the list.
     * @param \JDT\Api\Field\Field $field
     * @return \JDT\Api\Field\FieldList
     */
    public function addField(Field $field):self
    {
        $this->fields[$field->getField()] = $field;

        $payloadValidation = $field->getPayloadValidation();

        if (!empty($payloadValidation)) {
            $this->payloadValidation[$field->getField()] = $payloadValidation;
        }

        if ($field->canFilter() === true) {
            $filterValueValidation = $field->getFilterValueValidation();

            $this->filters[$field->getField()] = (strlen($filterValueValidation) > 0) ? $filterValueValidation : true;
        }

        if ($field->canSort() === true) {
            $this->sort[$field->getField()] = true;
        }

        return $this;
    }

    /**
     * Has fields.
     * @return bool
     */
    public function hasFields():bool
    {
        return !empty($this->fields);
    }

    /**
     * Has payload validation.
     * @return bool
     */
    public function hasPayloadValidations():bool
    {
        return !empty($this->payloadValidation);
    }

    /**
     * Has filters.
     * @return bool
     */
    public function hasFilters():bool
    {
        return !empty($this->filters);
    }

    /**
     * Has Sort.
     * @return bool
     */
    public function hasSort():bool
    {
        return !empty($this->sort);
    }

    /**
     * Get fields.
     * @return \JDT\Api\Field\Field[]
     */
    public function getFields():array
    {
        return $this->fields;
    }

    /**
     * Get payload validation.
     * @return array
     */
    public function getPayloadValidations():array
    {
        return $this->payloadValidation;
    }

    /**
     * Get filters.
     * @return array
     */
    public function getFilters():array
    {
        return $this->filters;
    }

    /**
     * Get Sort.
     * @return array
     */
    public function getSort():array
    {
        return $this->sort;
    }

    /**
     * Get field keys.
     * @return array
     */
    public function getFieldKeys():array
    {
        return array_keys($this->fields);
    }

    /**
     * Get payload validation keys.
     * @return array
     */
    public function getPayloadValidationKeys():array
    {
        return array_keys($this->payloadValidation);
    }

    /**
     * Get filter keys.
     * @return array
     */
    public function getFilterKeys():array
    {
        return array_keys($this->filters);
    }

    /**
     * Get Sort keys.
     * @return array
     */
    public function getSortKeys():array
    {
        return array_keys($this->sort);
    }
}
