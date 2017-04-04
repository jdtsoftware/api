<?php

namespace JDT\Api;

use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class InternalResult
{
    protected $data;
    protected $meta;
    protected $original;

    /**
     * InternalResult constructor.
     * @param array $result
     * @param mixed $original
     */
    public function __construct(array $result, $original)
    {
        if (isset($result['meta'])) {
            $this->meta = collect($result['meta']);
        }

        if (isset($result['data'])) {
            $this->data = collect($result['data']);
        }

        $this->original = $original;
    }

    /**
     * @return bool
     */
    public function hasData():bool
    {
        return $this->data !== null;
    }

    /**
     * @return \Illuminate\Support\Collection|null
     */
    public function getData():Collection
    {
        return $this->data;
    }

    /**
     * @return bool
     */
    public function hasMeta():bool
    {
        return $this->meta !== null;
    }

    /**
     * @return \Illuminate\Support\Collection|null
     */
    public function getMeta():Collection
    {
        return $this->meta;
    }

    /**
     * @return mixed
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * @return bool
     */
    public function hasPagination():bool
    {
        return isset($this->meta['pagination']);
    }

    /**
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPagination():LengthAwarePaginator
    {
        if ($this->hasPagination()) {
            $paginationData = $this->meta['pagination'];

            return new LengthAwarePaginator($this->getData(), $paginationData['total'], $paginationData['per_page'], $paginationData['current_page']);
        }

        return null;
    }
}
