<?php

class WooMailerLiteQueryBuilder extends WooMailerLiteDBConnection
{
    use WooMailerLiteResources;
    protected $model;

    private $select = "*";

    public function __construct($model)
    {
        $this->model = $model;
    }

    public function where($column, $operation = '=', $value = null)
    {
        if ($value === null) {
            $value = $operation;
            $operation = '=';
        }
        if ($this->model->isResource() || ((get_class($this->model) === 'WooMailerLiteCustomer') && !$this->customTableEnabled())) {
            $this->set_resource(get_class($this->model));
            $this->args[$column] = $value;
            return $this;
        }
        if (strpos($column, '.') !== false) {
            $column = $this->db()->prefix . $column;
        }
        if ($this->hasWhere) {
            $this->andWhere($column, $operation, $value);
        } else {
            $this->query .= " WHERE $column $operation '$value'";
        }
        $this->hasWhere = true;
        return $this;
    }

    public function get(int $count = -1)
    {
        if ($this->model->isResource() || ((get_class($this->model) === 'WooMailerLiteCustomer') && !$this->customTableEnabled())) {

            $this->set_resource(get_class($this->model));
            return $this->resource_get($count);
        }
        $collection = new WooMailerLiteCollection();

        $data = $this->buildQuery($count)->executeQuery();
        foreach ($data as $item) {
            if ((get_class($this->model) === 'WooMailerLiteCustomer') && empty($item->email)) {
                continue;
            }
            $model = new $this->model();

            if ($this->model->getCastsArray()) {
                if ($this->model->isResource()) {
                    $this->prepareResourceData(get_class($this->model), $item, $item->last_order_id ?? $item);
                }
                $model->attributes = array_intersect_key((array)$item, array_flip($this->model->getCastsArray() ?? []));
            } else {
                $model->attributes = (array)$item;
            }
            if (!empty($this->model->getFormatArray())) {
                foreach ($this->model->getFormatArray() as $key => $format) {
                    switch ($format) {
                        case 'array':
                            $model->attributes[$key] = json_decode($model->attributes[$key], true);
                            break;
	                    case 'boolean':
		                    $model->attributes[$key] = (bool) $model->attributes[$key];
		                    break;
                    }
                }
            }
            $collection->collect($model);
        }
        return $collection;
    }

    public function buildQuery($count = -1)
    {
        $this->query = "SELECT " . $this->select . " from " . $this->db()->prefix . $this->model->getTable() . $this->query;
        if ($count != -1) {
            $this->query .= " LIMIT $count";
        }

        $this->query .= ";" ;
        return $this;
    }

    public function whereIn($column, $values)
    {
        if ($this->model->isResource()) {
            $this->args[$column] = $values;
            return $this;
        }
        if (strpos($column, '.') !== false) {
            $column = $this->db()->prefix . $column;
        }
        $this->hasWhere = true;
        $values = "'" . implode("','", $values) . "'";
        $this->query .= " WHERE {$column} IN ($values)";
        return $this;
    }

    public function groupBy($column)
    {
        if (strpos($column, '.') !== false) {
            $column = $this->db()->prefix . $column;
        }
        $this->query .= " GROUP BY {$column}";
        return $this;
    }

    public function orderBy($column)
    {
        if (strpos($column, '.') !== false) {
            $column = $this->db()->prefix . $column;
        }
        $this->query .= " ORDER BY {$column}";
        return $this;
    }

    public function join(string $table, string $tableLeft, string $tableRight)
    {
        $table = $this->db()->prefix . $table;
        $tableLeft = $this->db()->prefix . $tableLeft;
        $tableRight = $this->db()->prefix . $tableRight;
        $this->query .= " INNER JOIN {$table} ON {$tableLeft} = {$tableRight}";
        return $this;
    }

    public function andWhere($column, $operation, $value)
    {
        if ($value === null) {
            $value = $operation;
            $operation = '=';
        }
        $this->query .= " AND {$column} {$operation} '{$value}'";
        return $this;
    }

    public function select($select)
    {
        $this->select = $select;
        return $this;
    }

    public function builder()
    {
        return new static($this->model);
    }

    public function all($arguments = [])
    {
        if ($this->model->isResource()) {
            $this->set_resource(get_class($this->model));
            return $this->resource_all();
        } else {
            return $this->get();
        }
    }

    public function create($data)
    {
        return $this->prepareQuery('create', $data);
    }

    public function update($data)
    {
        return $this->prepareQuery('update', $data, $this->model);
    }

    public function delete()
    {
        return $this->prepareQuery('delete', [], $this->model);
    }

    public function first()
    {
        return $this->get(1)->items[0] ?? null;
    }

    public function firstOrCreate($where, $data)
    {
        $exists = $this->where(array_key_first($where), $where[array_key_first($where)])->first();
        if ($exists) {
            return $exists;
        }
        $this->create(array_merge($where, $data));
        $this->query = '';
        $this->hasWhere = false;
        return $this->where(array_key_first($where), $where[array_key_first($where)])->first();
    }

    protected function prepareQuery(string $action, array $data, $model = null)
    {
        switch ($action) {
            case 'create':
                foreach ($data as &$value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                }
                $this->db()->insert($this->db()->prefix . $this->model->getTable(), $data);
                break;
            case 'update':
                foreach ($data as &$value) {
                    if (is_array($value)) {
                        $value = json_encode($value);
                    }
                }
                $this->db()->update(
                    $this->db()->prefix . $this->model->getTable(),
                    $data,
                    [
                        'id' => $model->id
                    ]
                );
                break;
            case 'delete':
                $this->db()->delete($this->db()->prefix . $this->model->getTable(), ['id' => $model->id] );
                break;
        }
        return true;
    }

    public function getFromOrder()
    {
        $this->model->setResource();
        return $this;
    }
}