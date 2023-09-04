<?php

namespace Lihoy\Moysklad;

use Lihoy\Moysklad\Base;
use Lihoy\Moysklad\Components\Http\Connection;
use Lihoy\Moysklad\Entity;
use Lihoy\Moysklad\Exceptions\NotFound as NotFoundException;
use Lihoy\Moysklad\Exceptions\NotSupported as NotSupportedException;

class Client extends Base
{

    /**
     * 
     * @var string
     */
    public const BASE_URI = 'https://online.moysklad.ru/api/remap/1.2';

    /**
     * 
     * @var string
     */
    public const ENTITY_URI = '/entity';

    /**
     * 
     * @var string
     */
    public const HOOK_URI = '/entity/webhook';

    /**
     * 
     * @var string
     */
    public const METADATA_URI = '/metadata';

    /**
     * 
     * @var int
     */
    public const ENTITIES_QUERY_LIMIT_MAX = 1000;

    /**
     * 
     * @var int
     */
    public const EVENTS_QUERY_LIMIT_MAX = 100;

    /**
     * 
     * @var Connection
     */
    public $connection;
    
    /**
     * 
     * @var array
     */
    protected $metadata;
    
    /**
     * 
     * @var int
     */
    protected $entities_query_limit_max;
    
    /**
     * 
     * @var int
     */
    protected $events_query_limit_max;

    /**
     * @param string $login
     * @param string $password
     */
    public function __construct(string $login, string $password) {
        $this->entities_query_limit_max = self::ENTITIES_QUERY_LIMIT_MAX;

        $this->events_query_limit_max = self::EVENTS_QUERY_LIMIT_MAX;

        $this->connection = new Connection($login, $password);

        $this->metadata = null;
    }

    /**
     * @param array|string $entity_data
     * @return Entity
     */
    public function createEntity($entity_data): Entity
    {
        return new Entity($entity_data, $this);
    }

    /**
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @param string $entity_type
     * @param string $id
     * @param string|null $expand
     * @return Entity
     */
    public function getEntityById(string $entity_type, string $id, ?string $expand = null): Entity
    {
        $href = static::BASE_URI . static::ENTITY_URI . "/" . $entity_type . '/' . $id;

        return $this->getEntityByHref($href, $expand);
    }

    /**
     * @param string $href
     * @param string|null $expand
     * @return Entity
     */
    public function getEntityByHref(string $href, ?string $expand = null): Entity
    {
        if ($expand) {
            $href = $href . '?expand=' . $expand;
        }

        $entity_data = $this->connection
            ->get($href);

        return new Entity($entity_data, $this);
    }

    /**
     * Get entities list with filtering
     *
     * @param string $entity_type
     * @param array $filter_list
     * @param int $limit
     * @param int $offset
     * @param array $params_list
     * @return array
     */
    public function getEntities(
        string $entity_type,
        array $filters_list = [],
        int $limit = null,
        int $offset = null,
        array $params_list = []
    ): array
    {
        return $this->getCollection('entity/' . $entity_type, $filters_list, $limit, $offset, $params_list);
    }

    /**
     * @param array $hrefs_list
     * @param string|null $expand
     * @return array
     */
    public function getEntitiesByHref(array $hrefs_list, ?string $expand = null): array
    {
        $entities_list = [];

        foreach ($hrefs_list as $href) {
            $entities_list[] = $this->getEntityByHref($href, $expand);
        }

        return $entities_list;
    }

    /**
     * @param string $entity_type
     * @param array $ids_list
     * @param string|null $expand
     * @return array
     */
    public function getEntitiesById(string $entity_type, array $ids_list, ?string $expand = null): array
    {
        $entities_list = [];

        foreach ($ids_list as $id) {
            $entities_list[] = $this->getEntityByHref($entity_type, $id, $expand);
        }

        return $entities_list;
    }

    /**
     * Get stock
     *
     * groupBy values: product, variant, consignment
     * groupBy default value: variant
     *
     * @param bool $is_by_store
     * @param int|null $limit
     * @param int|null $offset
     * @param string|null $group_by
     * @param array $filters_list
     * @return array
     */
    public function getStock(
        bool $is_by_store = false,
        ?int $limit = null,
        ?int $offset = null,
        ?string $group_by = null,
        array $filters_list = []
    ): array
    {
        $end_point = 'report/stock/' . ($is_by_store ? 'bystore' : 'all');

        return $this->getCollection($end_point, $filters_list, $limit, $offset, ['groupBy' => $group_by]);
    }

    /**
     * 
     * @param bool $is_by_store
     * @param string|null $stock_type
     * @param bool $is_zero_lines_included
     * @param array $filters_list
     * @return array
     * @throws NotSupportedException
     */
    public function getCurrentStock(
        bool $is_by_store = false,
        ?string $stock_type = null,
        bool $is_zero_lines_included = false,
        array $filters_list = []
    ): array
    {
        if (is_null($stock_type)) {
            $stock_type = 'stock';
        }

        if (!in_array($stock_type, ['stock', 'freeStock', 'quantity'])) {
            throw new NotSupportedException("Wrong stock_type value $stock_type.");
        }

        $stock_partition_by = ($is_by_store ? 'bystore' : 'all');
        
        $end_point = "report/stock/{$stock_partition_by}/current?"
            . "stockType={$stock_type}";

        if ($is_zero_lines_included) {
            $end_point = $end_point . '&include=zeroLines';
        }

        $filter_uri = '';

        for ($i = 0; $i < count($filters_list); $i++) {
            $filter = $filters_list[$i];

            $filter_uri .= $filter[0]
                . $filter[1]
                . $filter[2];

            if ($i === (count($filters_list) - 1)) {
                break;
            }

            $filter_uri .= ';';
        }

        if (!empty($filter_uri)) {
            $end_point .= "&filter=" . $filter_uri;
        }
        
        return $this->connection
            ->get(static::BASE_URI . '/' . $end_point);
    }

    /**
     * 
     * @param string $uid
     * @return Entity
     * @throws NotSupportedException
     */
    public function getEmployeeByUid(string $uid): Entity
    {
        $employees_list = $this->getEntities('employee', [['uid', '=', $uid]]);

        if (empty($employees_list)) {
            throw new NotSupportedException("Employee with $uid doesn`t exist.");
        }

        return $employees_list[0];
    }

    /**
     * 
     * @param string $audit_href
     * @param string $entity_type
     * @param string $event_type
     * @param string|null $uid
     * @return Entity
     * @throws NotFoundException
     * @throws NotSupportedException
     */
    public function getAuditEvent(
        string $audit_href,
        string $entity_type,
        string $event_type,
        ?string $uid = null
    ): Entity
    {
        $event_type = mb_strtolower($event_type);

        $entity_type = mb_strtolower($entity_type);

        $event_list = $this->connection
            ->get($audit_href . '/events')
            ->rows;

        $filtered_event_list = array_filter($event_list, function($event) use ($entity_type, $event_type, $uid) {
            return $event->eventType === $event_type
                && $event->entityType === $entity_type
                && (is_null($uid) || $event->uid === $uid);
        });

        $filtered_event_list = array_values($filtered_event_list);

        if (count($filtered_event_list) > 1) {
            throw new NotSupportedException("Events filtering error.");
        }

        if (empty($filtered_event_list[0])) {
            throw new NotFoundException("No event matching parameters: {$entity_type}, {$event_type}, {$uid}, {$audit_href}");
        }

        return $filtered_event_list[0];
    }

    /**
     * filter operators =, !=, <, >, <=, >=
     *
     * @param array $list
     * @param array $filter
     * @return array
     */
    public function filter(array $list, array $filter): array
    {
        foreach ($list as $key => $value) {
            $filtered = false;

            foreach ($filter as $rule) {
                $field_key = $rule[0];

                $op = $rule[1];

                $field_value = $rule[2];

                $filtered = false;

                if ($op === '=' && $field_value != $value->$field_key) {
                    $filtered = true;

                    break;
                }

                if ($op === '!=' && $field_value == $value->$field_key) {
                    $filtered = true;

                    break;
                }

                if ($op === '<' && $field_value >= $value->$field_key) {
                    $filtered = true;

                    break;
                }

                if ($op === '>' && $field_value <= $value->$field_key) {
                    $filtered = true;

                    break;
                }

                if ($op === '<=' && $field_value > $value->$field_key) {
                    $filtered = true;

                    break;
                }

                if ($op === '>=' && $field_value < $value->$field_key) {
                    $filtered = true;

                    break;
                }
            }

            if ($filtered) {
                unset($list[$key]);
            }
        }

        return array_values($list);
    }

    /**
     * 
     * @param string|null $entity_type
     * @return object
     * @throws NotFoundException
     */
    public function getMetadata(?string $entity_type = null): object
    {
        if (is_null($this->metadata)) {
            $entities_pool = (array) $this->connection
                ->get(static::BASE_URI . static::ENTITY_URI . static::METADATA_URI);

            $this->metadata = array_map(function ($entity_metadata) {
                return new Entity($entity_metadata);
            }, $entities_pool);
        }

        if (is_null($entity_type)) {
            return $this->metadata;
        }

        if (!isset($this->metadata[$entity_type])) {
            throw new NotFoundException("No {$entity_type} metadata");
        }

        return $this->metadata[$entity_type];
    }

    /**
     * 
     * @param array|string $subscription_list
     * @param string $url
     * @return array
     */
    public function addWebhooks($subscription_list, string $url): array
    {
        if (is_string($subscription_list)) {
            $subscription_list = [$subscription_list];
        }

        $actual_subscription_list = $this->getWebhooks([['url', '=', $url]]);

        $responses = [];

        foreach ($subscription_list as $subscription) {
            $subscription_parts = explode('.', $subscription);

            $entity_type = $subscription_parts[0];

            $action = $subscription_parts[1];

            $filtered = array_filter($actual_subscription_list, function ($hook) use ($entity_type, $action) {
                return $hook->entityType === $entity_type && $hook->action === mb_strtoupper($action);
            });

            $filtered = array_values($filtered);

            if ($filtered) {
                continue;
            }

            $post_data = [
                'url' => $url,
                'action' => mb_strtoupper($action),
                'entityType' => $entity_type
            ];

            $responses[] = $this->connection
                ->post(static::BASE_URI . static::HOOK_URI, $post_data);
        }
        
        return $responses;
    }

    /**
     * 
     * @param string $id
     * @return object
     */
    public function getWebhook(string $id): object
    {
        return $this->connection
            ->get(static::BASE_URI . static::HOOK_URI . "/{$id}");
    }

    /**
     * 
     * @param array $filter
     * @return array
     */
    public function getWebhooks(array $filter = []): array
    {
        $webhook_list = $this->connection
            ->get(static::BASE_URI . static::HOOK_URI)
            ->rows;

        if (empty($filter)) {
            return $webhook_list;
        }
        
        return $this->filter($webhook_list, $filter);
    }

    /**
     * @param array|string $subscription_list
     * @param string $url
     * @return array
     */
    public function deleteWebhooks($subscription_list, string $url): array
    {
        if (is_string($subscription_list)) {
            $subscription_list = [$subscription_list];
        }

        $actual_subscription_list = $this->getWebhooks([['url', '=', $url]]);

        $responses = [];

        foreach ($subscription_list as $subscription) {
            $subscription_parts = explode('.', $subscription);

            $entity_type = $subscription_parts[0];

            $action = $subscription_parts[1];

            $filtered = array_filter(
                $actual_subscription_list,
                function ($hook) use ($entity_type, $action) {
                    return $hook->entityType === $entity_type
                        && $hook->action === mb_strtoupper($action);
                }
            );

            $filtered = array_values($filtered);

            $is_signed = empty($filtered) ? false : true;

            if ($is_signed) {
                $hook = $filtered[0];

                $responses[] = $this->connection
                    ->delete($hook->meta->href);
            }
        }

        return $responses;
    }

    /**
     * Get entities list with filtering
     *
     * filters_list example: [['sum', '>' '100'], ['sum', '<' '200']]
     * operators: ['=', '>', '<', '>=', '<=', '!=', '~', '~=', '=~']
     *
     * @param string $end_point
     * @param array $filters_list
     * @param int $limit
     * @param int $offset
     * @param array $params_list
     * @return array
     */
    public function getCollection(
        string $end_point,
        array $filters_list = [],
        int $limit = null,
        int $offset = null,
        array $params_list = []
    ): array
    {
        $query_limit = $this->entities_query_limit_max;

        if ($limit && $query_limit > $limit) {
            $query_limit = $limit;
        }

        if ($query_limit <= 0) {
            return [];
        }

        $href_base = static::BASE_URI . '/' . $end_point . '?limit=' . $query_limit;

        foreach ($params_list as $param_name => $param_value) {
            if (is_null($param_value)) {
                continue;
            }

            $href_base = $href_base . '&' . $param_name . '=' . $param_value;
        }

        if ($filters_list) {
            $filter_uri = 'filter=';

            for ($i = 0; $i < count($filters_list); $i++) {
                $filter = $filters_list[$i];

                $filter_uri = $filter_uri . $filter[0] . $filter[1] . $filter[2];

                if ($i === (count($filters_list) - 1)) {
                    break;
                }

                $filter_uri = $filter_uri . ';';
            }

            $href_base = $href_base . '&' . $filter_uri;
        }

        $offset = $offset ? $offset : 0;

        $list = [];

        do {
            $href = $href_base . '&offset=' . $offset;

            $response = $this->connection
                ->get($href);

            $entity_data_list = $response->rows;

            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }

            $remainder = empty($remainder)
                ? $limit - count($entity_data_list)
                : $remainder - count($entity_data_list);

            foreach ($entity_data_list as $entity_data) {
                $list[] = new Entity($entity_data, $this);
            }

            $offset = $offset + $query_limit;

            if ($remainder < $query_limit) {
                $query_limit = $remainder;
            }

        } while (count($list) < $limit && count($list) === $query_limit);
        
        return $list;
    }

    /**
     * 
     * @param string $type
     * @param string $id
     * @param bool $is_add_metadata
     * @return object
     */
    public function createLink(string $type, string $id, bool $is_add_metadata = false): object
    {
        $link = (object) [
            'meta' => (object) [
                'href' => static::BASE_URI . static::ENTITY_URI . "/{$type}/{$id}",
                'type' => $type,
                'mediaType' => "application/json"
            ]
        ];

        if ($is_add_metadata) {
            $link->meta->metadataHref = static::BASE_URI . static::ENTITY_URI . "/{$type}/metadata";
        }

        return $link;
    }

    /**
     * 
     * @param int|null $new_value
     * @return $this
     */
    public function setEntitiesQueryLimitMax(?int $new_value): self
    {
        $this->entities_query_limit_max = is_null($new_value)
            ? self::ENTITIES_QUERY_LIMIT_MAX
            : $new_value;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getEntitiesQueryLimitMax(): int
    {
        return $this->entities_query_limit_max;
    }

    /**
     * 
     * @param int|null $new_value
     * @return $this
     */
    public function setEventsQueryLimitMax(?int $new_value): self
    {
        $this->events_query_limit_max = is_null($new_value)
            ? self::EVENTS_QUERY_LIMIT_MAX
            : $new_value;

        return $this;
    }

    /**
     * 
     * @return int
     */
    public function getEventsQueryLimitMax(): int
    {
        return $this->events_query_limit_max;
    }
    
}
