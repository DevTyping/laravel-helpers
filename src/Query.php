<?php

namespace DevTyping\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Class GETParameters
 * @package App\Helpers
 */
final class Query
{
    use Str;

    private $request;
    private $parameters = [];
    private $searchFields = [];
    private $availableParameters = ['sort', 'limit', 'relations', 'q', 'trans_status', 'ids'];
    private $whereParameters = ['created_at', 'updated_at'];
    private $defaults = [
        'sorts' => [],
        'relations' => [],
        'status' => [
            'field' => null,
            'state' => null
        ]
    ];

    /**
     * GETParameters constructor.
     * @param Request $request
     * @param null $defaults
     */
    public function __construct(Request $request, $defaults = null)
    {
        $this->setRequest($request);
        $this->setSearchFields();
        $this->prepareParameters();

        if (!is_null($defaults)) {
            $this->setDefaults($defaults);
        }
    }


    /**
     * Get all parameters
     * @return array
     */
    public function get()
    {
        return $this->parameters;
    }


    /**
     * Get items limit from GET parameters
     * @return int|mixed
     */
    public function getLimit()
    {
        $limit = $this->getParameter('limit');
        return $limit ? $limit : 20;
    }


    /**
     * Set keys you can use in `where` condition
     * @param array $keys
     * @return self
     */
    public function setWhereKeys($keys = [])
    {
        $this->whereParameters = array_merge($this->whereParameters, $keys);
        return $this;
    }


    /**
     * Set database fields you want to search on
     *
     * @param array $fields
     * @return $this
     */
    public function setSearchFields($fields = [])
    {
        $this->searchFields = $fields;
        return $this;
    }


    /**
     * Prepare a laravel Query Builder
     * @param Model $model
     * @param array $options
     * @return mixed
     */
    public function prepareSQLQuery($model, array $options = [])
    {
        $defaultOptions = [
            'sort' => isset($options['sort']) ? $options['sort'] : true,
            'relations' => isset($options['relations']) ? $options['relations'] : true,
            'q' => isset($options['q']) ? $options['q'] : true,
            'filter' => isset($options['filter']) ? $options['filter'] : true,
        ];

        $queryBuilder = $model->query();

        foreach ($this->availableParameters as $parameter) {
            if ($parameter === "sort") {
                // Sort
                $sortParameters = $this->getParameter($parameter);

                if (!is_null($sortParameters)) {
                    $sorts = $this->getArray($sortParameters);
                    foreach ($sorts as $sort) {
                        $queryBuilder->orderBy($sort['key'], $sort['value']);
                    }
                } else {
                    $queryBuilder->orderBy('updated_at', 'desc');
                }
            } else if ($parameter === "relations" && $defaultOptions['relations'] === true) {
                // Relations
                $relationParameters = $this->getParameter($parameter);
                if (!is_null($relationParameters)) {
                    $relations = $this->getArray($relationParameters);

                    if (count($this->defaults['relations']) > 0) {
                        $relations = array_merge($relations, $this->defaults['relations']);
                    }

                    foreach ($relations as $relation) {
                        $queryBuilder->with($relation);
                    }
                } elseif (count($this->defaults['relations']) > 0) {
                    foreach ($this->defaults['relations'] as $relation) {
                        $queryBuilder->with($relation);
                    }
                }
            } else if ($parameter === "q") {
                // Search query string
                $searchParameter = $this->getParameter($parameter);
                if (!is_null($searchParameter) && !empty($this->searchFields)) {
                    if (str_contains($searchParameter, ':')) {
                        $extractSearch = explode(':', $searchParameter);

                        if ($extractSearch[0] === 'id') {
                            $queryBuilder->where($extractSearch[0], $extractSearch[1]);
                        } else {
                            $queryBuilder->where($extractSearch[0], 'LIKE', '%' . $extractSearch[1] . '%');
                        }
                    } else {
                        $totalFields = count($this->searchFields);

                        if ($totalFields > 1) {
                            $queryBuilder->where(function ($query) use ($totalFields, $searchParameter) {
                                for ($x = 0; $x < $totalFields; $x++) {
                                    $query->orWhere($this->searchFields[$x], 'LIKE', '%' . $searchParameter . '%');
                                }
                            });
                        } else {
                            $queryBuilder->where($this->searchFields[0], 'LIKE', '%' . $searchParameter . '%');
                        }
                    }
                }
            } else if ($parameter === 'ids') {
                // Get multiple records by id
                $ids = $this->getParameter($parameter);

                if (!is_null($ids)) {
                    if (str_contains($ids, ',')) {
                        $listOfIds = explode(',', $ids);
                        $queryBuilder->whereIn('id', $listOfIds);
                    } else {
                        $queryBuilder->where('id', $ids);
                    }
                }
            }
        }

        if (count($this->whereParameters) > 0) {
            foreach ($this->whereParameters as $parameter) {
                $value = $this->getParameterByKey($parameter);

                if ($this->defaults['status']['field'] === $parameter && $value === null) {
                    $value = $this->defaults['status']['state'];
                }

                if (!is_null($value)) {
                    if (is_array($value)) {
                        foreach ($value as $k => $v) {
                            $operator = $this->getOperator($k);
                            if (!is_null($v)) {
                                $queryBuilder->where($parameter, $operator, $v);
                            }
                        }
                    } else {
                        $queryBuilder->where($parameter, $value);
                    }
                }
            }
        }

        return $queryBuilder;
    }


    /**
     * Set default options
     * @param $options
     */
    private function setDefaults($options)
    {
        if (isset($options['relations']) && is_array($options['relations']) && !empty($options['relations'])) {
            $this->defaults['relations'] = $options['relations'];
        }

        if (isset($options['sorts']) && is_array($options['sorts']) && !empty($options['sorts'])) {
            $this->defaults['sorts'] = $options['sorts'];
        }
    }


    /**
     * Prepare parameters
     */
    private function prepareParameters()
    {
        foreach ($this->availableParameters as $parameter) {
            $this->setParameter($parameter);
        }
    }


    /**
     * Convert operator from human language to SQL language
     * @param $symbol
     * @return string
     */
    private function getOperator($symbol)
    {
        switch ($symbol) {
            case 'gte':
                return ">=";

            case 'lte':
                return "<=";

            case 'lt':
                return "<";

            case 'gt':
                return ">";

            default:
                return "=";
        }
    }


    /**
     * @param $parameter
     * @return array|string[]
     */
    private function getArray($parameter)
    {
        $data = [];
        if (strpos($parameter, ',') !== false) {
            $fields = explode(',', $parameter);
            foreach ($fields as $field) {
                if (strpos($field, '|')) {
                    $fieldSort = explode('|', $field);
                    array_push($data, ['key' => $fieldSort[0], 'value' => $fieldSort[1]]);
                } else {
                    array_push($data, $field);
                }
            }
        } else if (strpos($parameter, '|')) {
            $fieldSort = explode('|', $parameter);
            array_push($data, ['key' => $fieldSort[0], 'value' => $fieldSort[1]]);
        } else if (is_string($parameter)) {
            $data = [$parameter];
        }

        return $data;
    }


    /**
     * @param Request $request
     */
    private function setRequest(Request $request)
    {
        $this->request = $request;
    }


    /**
     * @param $key
     */
    private function setParameter($key)
    {
        if ($this->isGood($key)) {
            /** @var Request $request */
            $request = $this->request;
            $this->parameters[$key] = $request->input($key);
        }
    }


    /**
     * Get GET parameter by a key
     * @param $key
     * @return array|string|null
     */
    private function getParameterByKey($key)
    {
        if ($this->isGood($key)) {
            /** @var Request $request */
            $request = $this->request;
            return $request->input($key);
        }
        return null;
    }


    /**
     * @param $key
     * @return mixed|null
     */
    private function getParameter($key)
    {
        if (isset($this->parameters[$key])) {
            return $this->parameters[$key];
        }

        return null;
    }


    /**
     * Check if a parameter is exists and not null
     *
     * @param $key
     * @return bool
     */
    private function isGood($key)
    {
        /** @var Request $request */
        $request = $this->request;
        return $request->has($key) && $request->filled($key);
    }
}
