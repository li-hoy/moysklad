<?php

namespace Lihoy\Moysklad;

use GuzzleHttp\Client as  HttpClient;
use Lihoy\Moysklad\Entity;

class Client extends \Lihoy\Moysklad\Base
{
    private $queryDelay, $requestOptions, $credentials;

    const
        BASE_URI = "https://online.moysklad.ru/api/remap/1.2",
        ENTITY_URI = "/entity",
        HOOK_URI = "/entity/webhook",
        METADATA_URI = "/metadata",
        POST_DATA_FORMAT = 'json',
        DEFAULT_REQUEST_DELAY = 0.2,
        DEFAULT_REQUEST_TIMEOUT = 10.0;

    public function __construct($login, $pass)
    {
        $this->token = base64_encode($login.':'.$pass);
        $this->requestOptions = [
            'headers' => [
                'Authorization' => "Basic $this->token",
            ],
            'delay' => static::DEFAULT_REQUEST_DELAY,
            'timeout' => static::DEFAULT_REQUEST_TIMEOUT
        ];
        $this->httpClient = new HttpClient($this->requestOptions);
    }

    public function getToken()
    {
        return $this->token;
    }

    public function getRequestOptions()
    {
        return $this->requestOptions;
    }

    public function setRequestOption(string $key, $value)
    {
        $this->requestOptions[$key] = $value;
        if (is_null($value)) {
            unset($this->requestOptions[$key]);
        }
        $this->httpClient = new HttpClient($this->requestOptions);
        return $this;
    }

    public function getEntityById(
        string $entityType,
        string $id,
        ?string $expand = null
    ) {
        $href = static::BASE_URI.static::ENTITY_URI."/".$entityType.'/'.$id;
        return $this->getEntityByHref($href, $expand);
    }

    public function getEntityByHref(
        string $href,
        ?string $expand = null
    ) {
        if ($expand) {
            $href = $href.'?expand='.$expand;
        }
        $response = $this->httpClient->get($href)->getBody()->getContents();
        $entityData = json_decode($response);
        $entity = new Entity($this, $entityData);
        return $entity;
    }

    /**
     * Get entities list with filtering
     * 
     * filterList example: [['sum', '>' '100'], ['sum', '<' '200']]
     * opaerators: ['=', '>', '<', '>=', '<=', '!=', '~', '~=', '=~']
     */
    public function getEntities(
        string $entityType,
        array $filterList = [],
        int $limit = null,
        string $expand = null
    ) {
        $queryLimit = 1000;
        if ($queryLimit > $limit) {
            $queryLimit = $limit;
        }
        if ($queryLimit <= 0) {
            return [];
        }
        $href_base = static::BASE_URI.static::ENTITY_URI."/".$entityType."?limit=".$queryLimit;
        if ($expand) {
            $href_base = $href_base.'&expand='.$expand;
        }
        if ($filterList) {
            $filterURI = "filter=";
            for ($i = 0; $i < count($filterList); $i++) {
                $filter = $filterList[$i];
                $filterURI = $filterURI.$filter[0].$filter[1].$filter[2];
                if ($i === (count($filterList) - 1)) {
                    break;
                }
                $filterURI = $filterURI.';';
            }
            $href_base = $href_base."&".$filterURI;
        }
        $offset = 0;
        $list = [];
        do {
            $href = $href_base."&offset=".$offset;
            $response = $this->httpClient->get($href)->getBody()->getContents();
            $entityDataList = json_decode($response)->rows;
            if (is_null($limit)) {
                $limit = $response->meta->size - $offset;
            }
            $remainder = empty($remainder)
                ? $limit - count($entityDataList)
                : $remainder - count($entityDataList);
            foreach ($entityDataList as $entityData) {
                $list[] = new Entity($this, $entityData);
            }
            $offset = $offset + $queryLimit;
            if ($remainder < $queryLimit) {
                $queryLimit = $remainder;
            }
        } while (count($list) < $limit && count($list) === $queryLimit);
        return $list;
    }

    public function getEmployeeByUid(string $uid)
    {
        $employeeList = $this->getEntities('employee', [['uid', '=', $uid]]);
        if (empty($employeeList)) {
            throw new Exception("Employee with $uid doesn`t exist.");
        }
        return $employeeList[0];
    }

    public function getAuditEvent($auditHref, $entityType, $eventType, $uid)
    {
        $eventType = mb_strtolower($eventType);
        $entityType = mb_strtolower($entityType);
        $eventList = $this->httpClient->get($auditHref.'/events')->parseJson()->rows;
        $filteredEventList = array_values(array_filter(
            $eventList,
            function($event)use($entityType, $eventType, $uid) {
                return
                    $event->eventType === $eventType
                    && $event->entityType === $entityType
                    && $event->uid === $uid;
            }
        ));
        if (count($filteredEventList) > 1) {
            throw new \Exception("Events filtering error.");
        }
        if (empty($filteredEventList[0])) {
            throw new \Exception("No event matching parameters: $entityType, $eventType, $uid, $auditHref");
        }
        return $filteredEventList[0];
    }

    /**
     * filter operators =, !=, <, >, <=, >=
     */
    public function filter(
        array $list,
        array $filter
    ) {
        foreach ($list as $key=>$value) {
            $filtered = false;
            foreach ($filter as $rule) {
                $fieldKey = $rule[0];
                $op = $rule[1];
                $fieldValue = $rule[2];
                $filtered = false;
                if ($op === '=' && $fieldValue != $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '!=' && $fieldValue == $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '<' && $fieldValue >= $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '>' && $fieldValue <= $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '<=' && $fieldValue > $value->$fieldKey) {
                    $filtered = true;
                    break;
                }
                if ($op === '>=' && $fieldValue < $value->$fieldKey) {
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

    public function getMetadata(string $entityType)
    {
        return $this->httpClient
                    ->get(
                        static::BASE_URI.
                        static::ENTITY_URI.
                        "/{$entityType}".
                        static::METADATA_URI)
                    ->getBody()->getContents();
    }

    public function getWebhook(string $id)
    {
        return $this->httpClient
                    ->get(static::BASE_URI.static::HOOK_URI."/{$id}")
                    ->getBody()->getContents();
    }

    public function getWebhooks(array $filter = [])
    {
        $webhookList = $this->httpClient
                            ->get(static::BASE_URI.static::HOOK_URI)
                            ->getBody()->getContents();
        if (empty($filter)) {
            return $webhookList;
        }
        return $this->filter($webhookList, $filter);
    }

    public function addWebhooks($subscriptionList, string $url)
    {
        if (is_string($subscriptionList)) {
            $subscriptionList = [$subscriptionList];
        }
        $actualSubscriptionList = $this->getWebhooks([['url', '=', $url]]);
        $responses = [];
        foreach ($subscriptionList as $subscription) {
            $subscriptionParts = explode('.', $subscription);
            $entityType = $subscriptionParts[0];
            $action = $subscriptionParts[1];
            $filtered = array_filter(
                $actualSubscriptionList,
                function($hook)use($entityType, $action) {
                    return $hook->entityType === $entityType && $hook->action === mb_strtoupper($action);
                }
            );
            $filtered  = array_values($filtered);
            if ($filtered) {
                continue;
            }
            $responses[] = $this->httpClient->post(
                static::BASE_URI.static::HOOK_URI,
                [
                    'url' => $url,
                    'action' => mb_strtoupper($action),
                    'entityType' => $entityType
                ]
            );
        }
        return $responses;
    }

    public function deleteWebhooks($subscriptionList, string $url)
    {
        if (is_string($subscriptionList)) {
            $subscriptionList = [$subscriptionList];
        }
        $actualSubscriptionList = $this->getWebhooks([['url', '=', $url]]);
        $responses = [];
        foreach ($subscriptionList as $subscription) {
            $subscriptionParts = explode('.', $subscription);
            $entityType = $subscriptionParts[0];
            $action = $subscriptionParts[1];
            $filtered = array_values(array_filter($actualSubscriptionList, function ($hook)use($entityType, $action) {
                return $hook->entityType === $entityType && $hook->action === mb_strtoupper($action);
            }));
            $isSigned = empty($filtered) ? false : true;
            if ($isSigned) {
                $hook = $filtered[0];
                $responses[] = $this->httpClient->delete($hook->meta->href);
            }
        }
        return $responses;
    }

}
