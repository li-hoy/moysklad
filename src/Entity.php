<?php

namespace Lihoy\Moysklad;

use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Client;
use Lihoy\Moysklad\Exceptions\NotFound as NotFoundException;
use Lihoy\Moysklad\Exceptions\NotSupported as NotSupportedException;

class Entity extends Base
{
    
    /**
     * 
     * @var array
     */
    protected $additional_fields;

    /**
     * 
     * @var array
     */
    protected $data;

    /**
     * 
     * @var Client
     */
    protected $client;

    /**
     * 
     * @var array
     */
    protected $changed;

    /**
     * 
     * @var object
     */
    protected $metadata;

    /**
     * 
     * @var array
     */
    protected $readonly;

    /**
     * 
     * @var array
     */
    protected $required;

    /**
     * 
     * @var array
     */
    protected $system = [
        'meta',
    ];

    /**
     * 
     * @var string
     */
    protected $type;


    /**
     * 
     * @param array|string $entity_data
     * @param Client $client
     * @throws NotSupportedException
     */
    public function __construct($entity_data = null, Client $client = null)
    {
        $this->client = $client;

        if (is_object($entity_data)) {
            if (!isset($entity_data->meta)) {
                throw new NotSupportedException("'meta' field is required");
            }

            foreach ($entity_data as $field_name => $field_value) {
                $this->data[$field_name] = $this->updateField($field_value);
            }

            $this->type = $this->data['meta']->type ?? null;
        }

        if (is_string($entity_data)) {
            $this->type = $entity_data;
        }

        $this->changed = [];
    }

    /**
     *
     * @param string $field_name
     * @return mixed
     * @throws NotFoundException
     */
    public function __get($field_name)
    {
        if (array_key_exists($field_name, $this->data)) {
            return $this->data[$field_name];
        }

        if ($field_name === 'id') {
            return $this->parseId();
        }

        $additional_field_list = [];

        if (isset($this->data['attributes']) && is_array($this->data['attributes'])) {
            $additional_field_list = $this->data['attributes'];
        }

        foreach ($additional_field_list as $additional_field) {
            if (!isset($additional_field->name)) {
                continue;
            }

            if ($additional_field->name === $field_name) {
                return $additional_field;
            }
        }

        throw new NotFoundException("Trying to get a non-existent field '{$field_name}' value.");
    }

    /**
     *
     * @param string $field_name
     * @param mixed $field_value
     * @return void
     * @throws NotSupportedException
     */
    public function __set($field_name, $field_value): void
    {
        if (in_array($field_name, $this->readonly) && !is_null($this->changed)) {
            throw new NotSupportedException("Trying to set a value to a read-only field.");
        }

        if (!is_null($this->changed)) {
            $this->changed[] = $field_name;
        }

        $this->data[$field_name] = $this->updateField($field_value);
    }

    /**
     *
     * @param string $name
     * @return bool
     */
    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    /**
     * @param object $data
     * @return bool
     */
    protected function updateData(object $data): bool
    {
        $this->data = [];

        foreach ($data as $field_name => $field_value) {
            $this->data[$field_name] = $this->updateField($field_value);
        }

        return true;
    }

    /**
     * @param array|object $field
     * @return array|object
     */
    protected function updateField($field)
    {
        if (is_array($field)) {
            foreach ($field as &$sub_field) {
                $sub_field = $this->updateField($sub_field);
            }

            return $field;
        }

        if (is_object($field)) {
            foreach ($field as $sub_field_name => $sub_field_value) {
                $field->$sub_field_name =
                    $this->updateField($sub_field_value);
            }

            if (isset($field->meta) && !is_a($field, static::class)) {
                $field = new static($field, $this->client);
            }
        }

        return $field;
    }

    /**
     * Additional fields
     * 
     * @param string|null $name
     * @param mixed $value
     * @return mixed
     * @throws NotFoundException
     */
    public function af(?string $name = null, $value = null)
    {
        $additional_field_list = isset($this->data['attributes'])
            ? $this->data['attributes']
            : [];

        // get all
        if (is_null($name)) {
            return $additional_field_list;
        }

        // get or update
        foreach ($additional_field_list as &$add_field) {
            if ($add_field->name !== $name) {
                continue;
            }

            if (is_null($value)) {
                return $add_field;
            }

            $add_field->value = $value;

            if (!in_array('attributes', $this->changed)) {
                $this->changed[] = 'attributes';
            }

            return true;
        }

        $meta_additional_fields_list = $this->getMetaAdditionalFields();

        $additional_field = null;

        foreach ($meta_additional_fields_list as $add_field) {
            if ($add_field->name !== $name) {
                continue;
            }

            $additional_field = $add_field;

            break;
        }

        if (is_null($additional_field)) {
            throw new NotFoundException("Trying to get non-existent additional field '{$name}' value.");
        }

        $additional_field->value = null;

        // get
        if (is_null($value)) {
            return $additional_field;
        }

        // set
        $additional_field->value = $value;

        if (!isset($this->data['attributes'])) {
            $this->data['attributes'] = [];
        }

        $this->data['attributes'][] = $additional_field;

        if (!in_array('attributes', $this->changed)) {
            $this->changed[] = 'attributes';
        }

        return $additional_field;
    }

    /**
     * 
     * @return array
     */
    public function getMetaAdditionalFields(): array
    {
        if (empty($this->additional_fields)) {
            $this->additional_fields = $this->client
                ->getEntities("{$this->type}/metadata/attributes");
        }

        return $this->additional_fields;
    }

    /**
     * 
     * @return object
     */
    public function getMetadata(): object
    {
        if (is_null($this->metadata)) {
            $this->metadata = $this->client->getMetadata($this->type);
        }

        return $this->metadata;
    }

    /**
     * 
     * @return string
     */
    protected function parseId(): string
    {
        $uri_parts = explode('/', $this->data['meta']->href);

        return end($uri_parts);
    }

    /**
     *
     * @param array $field_names_list
     * @return object
     */
    public function map(array $field_names_list): object
    {
        $out = (object) [];

        foreach ($field_names_list as $field_name) {
            if (isset($this->data[$field_name])) {
                $out->$field_name = $this->data[$field_name];
            }
        }
        
        return $out;
    }

    /**
     *  By default moysklad API return 25 events - max = 100
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getEvents(int $limit = null, int $offset = null): array
    {
        $query_limit = $this->client
            ->getEventsQueryLimitMax();

        if ($limit && $limit < $query_limit) {
            $query_limit = $limit;
        }

        $offset = is_null($offset) ? 0 : $offset;

        $total_count = 0;

        $event_list = [];

        do {
            // "offest" - ???
            $href = $this->data['meta']->href . '/audit?limit=' . $query_limit . '&offest=' . $offset;

            $response = $this->client
                ->connection
                ->get($href);

            $rows = $response->rows;

            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }

            $event_list = array_merge($event_list, $rows);

            $total_count = $total_count + count($rows);

            $offset = $offset + count($rows);

            $remainder = empty($remainder)
                ? $limit - count($rows)
                : $remainder - count($rows);

            $query_limit = ($query_limit <= $remainder) ? $query_limit : $remainder;

            if (($remainder + $query_limit) > $limit) {
                $query_limit = $limit - $total_count;
            }

        } while ($total_count < $limit);

        return $event_list;
    }

    /**
     * 
     * @param string $search_type
     * @param int|null $recursive
     * @param int $limit
     * @param string|null $expand
     * @return array
     */
    public function getLinkedEntities(
        string $search_type,
        ?int $recursive = null,
        int $limit = 1,
        ?string $expand = null
    ): array
    {
        $result_linked_entities_List = [];

        foreach($this->getData() as $linked_entities_type => $linked_entities_list) {
            if (in_array($linked_entities_type, ['attributes', 'positions', 'files'])) {
                continue;
            }

            if (is_a($linked_entities_list, static::class)) {
                $linked_entities_list = [$linked_entities_list];
            }

            if (!is_array($linked_entities_list)) {
                continue;
            }

            if (empty($linked_entities_list)) {
                continue;
            }

            $entityType = $linked_entities_list[0]->type ?? null;

            if (is_null($entityType)) {
                continue;
            }

            if ($entityType === $search_type) {
                $result_linked_entities_List = array_merge(
                    $result_linked_entities_List,
                    $linked_entities_list
                );

                $limit = $limit - count($linked_entities_list);

                if ($limit <= 0) {
                    return $result_linked_entities_List;
                }
            }

            if (!is_null($recursive) && $recursive > 0) {
                $recursive_linked_entities_list = [];

                $hrefs_list = [];

                foreach ($linked_entities_list as $linked_entity) {
                    if (!isset($linked_entity->meta->href)) {
                        continue;
                    }

                    $hrefs_list[] = $linked_entity->meta->href;
                }

                $linked_entities_list_expanded = $this->client
                    ->getEntitiesByHref($hrefs_list, $expand);

                foreach ($linked_entities_list_expanded as $linked_entity) {
                    $sub_linked_entities = call_user_func_array(
                        [$linked_entity, __FUNCTION__],
                        [$search_type, $recursive - 1, $limit, $expand]
                    );

                    $recursive_linked_entities_list = array_merge($recursive_linked_entities_list, $sub_linked_entities);
                }

                $result_linked_entities_List = array_merge($result_linked_entities_List, $recursive_linked_entities_list);
            }
        }

        return $result_linked_entities_List;
    }

    /**
     * 
     * @param string $needle
     * @param string $by
     * @return $this
     */
    public function setState(string $needle, string $by = 'name'): self
    {
        $this->__set('state', $this->getState($needle, $by));

        return $this;
    }

    /**
     * 
     * @param array $filter
     * @return array
     */
    public function getStates(array $filter = []): array
    {
        $state_list = $this->client
            ->getMetadata($this->type)
            ->states;

        if (empty($filter)) {
            return $state_list;
        }
        
        return $this->client
            ->filter($state_list, $filter);
    }

    /**
     * 
     * @param string $needle
     * @param string $by
     * @return object
     * @throws NotFoundException
     */
    public function getState(string $needle, string $by = 'name'): object
    {
        $states_list = $this->getStates([
            [$by, '=', $needle],
        ]);

        if (empty($states_list)) {
            throw new NotFoundException("State with $by = $needle doesn`t exist");
        }

        return $states_list[0];
    }

    /**
     * 
     * @return $this
     * @throws NotSupportedException
     */
    public function remove(): self
    {
        if (!isset($this->data['meta']->href)) {
            throw new NotSupportedException('It is not possible to delete a non-existent entity.');
        }

        $this->client
            ->getConnection()
            ->query('DELETE', $this->data['meta']->href);

        return $this;
    }

    /**
     * 
     * @return $this
     */
    public function save(): self
    {
        $request_data = [];

        foreach ($this->required as $field_name) {
            $request_data[$field_name] = $this->getFieldData($this->data[$field_name]);
        }

        foreach ($this->changed as $field_name) {
            if (in_array($field_name, $this->required)) {
                continue;
            }

            $request_data[$field_name] = $this->getFieldData($this->data[$field_name]);
        }

        if (empty($request_data)) {
            return false;
        }

        $method = 'POST';

        $href = Client::BASE_URI . Client::ENTITY_URI . '/' . $this->type;

        if (isset($this->data['meta'])) {
            $request_data['meta'] = $this->data['meta'];

            $method = 'PUT';

            $href = $this->data['meta']->href;
        }

        $response = $this->client
            ->getConnection()
            ->query($method, $href, $request_data);

        $this->updateData($response);

        $this->changed = [];

        return $this;
    }

    /**
     * 
     * @return object
     */
    protected function getData(): object
    {
        return (object) $this->data;
    }

    /**
     * 
     * @param array|object
     * @return mixed
     */
    protected function getFieldData($field)
    {
        $field_data = $field;

        if (is_array($field_data)) {
            foreach ($field_data as &$sub_field) {
                $sub_field = $this->getFieldData($sub_field);
            }
        }

        if (is_object($field_data)) {
            if (is_a($field_data, static::class)) {
                $field_data = $field_data->getData();
            }

            foreach ($field_data as $sub_field_name => $sub_field_value) {
                $field_data->$sub_field_name = $this->getFieldData($sub_field_value);
            }
        }

        return $field_data;
    }

}
