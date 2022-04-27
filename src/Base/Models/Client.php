<?php

namespace Lihoy\Moysklad\Base\Models;

class Client
{
    private $queryOptions, $credentials;

    const
        HREF_BASE = "https://online.moysklad.ru/api/remap/1.2",
        ENTITY_HREF = "https://online.moysklad.ru/api/remap/1.2/entity",
        HOOK_HREF = "https://online.moysklad.ru/api/remap/1.2/entity/webhook",
        QUERY_DELAY = 0.2;

    public function __construct($login, $pass)
    {
        $this->credentials = base64_encode($login.':'.$pass);
        $this->queryOptions = [
            'headers' => [
                'Authorization' => "Basic $this->credentials",
            ],
            'post_data_format' => 'json',
        ];
    }

    public function getCredentials()
    {
        return $this->credentials;
    }

    public function getQueryOptions()
    {
        return $this->queryOptions;
    }

    public function query(string $url)
    {
        msleep(static::QUERY_DELAY);
        $response = new Query($url, $this->queryOptions);
        if (isset($response->errors)) {
            throw new Exception("Get entity error.");
        }
        return $response;
    }

    public function getEntityById(
        string $entityType,
        string $id
    ) {
        $href = self::ENTITY_HREF."/".$entityType.'/'.$id;
        return $this->getEntityByHref($href);
    }

    public function getEntityByHref(string $href)
    {
        $entity = $this->query($href)->get()->parseJson();
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
        array $filterList = []
    ) {
        $limit = 1000;
        $offset = 0;
        $href_base = self::ENTITY_HREF."/".$entityType."?limit=".$limit;
        if (false === empty($filterList)) {
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
        $list = [];
        do {
            $href = $href_base."&offset=".$offset;
            $responseList = $this->query($href)->get()->parseJson()->rows ?? [];
            $list = array_merge($list, $responseList);
            $offset = $offset + $limit;
        } while (count($responseList) === $limit);
        return $list;
    }

    public function getMetadata(string $entityType)
    {
        return $this->query(static::ENTITY_HREF."/{$entityType}/metadata")
                    ->get()
                    ->parseJson();
    }

    public function getWebhook(string $id)
    {
        return $this->query(static::HOOK_HREF."/{$id}")->get()->parseJson();
    }
    
    public function getWebhooks(array $filter = [])
    {
        $webhookList = $this->query(static::HOOK_HREF)->get()->parseJson()->rows;
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
            $filtered = array_values(array_filter($actualSubscriptionList, function ($hook)use($entityType, $action) {
                if ($hook->entityType === $entityType && $hook->action === mb_strtoupper($action)) {
                }
                return $hook->entityType === $entityType && $hook->action === mb_strtoupper($action);
            }));
            $isSigned = empty($filtered) ? false : true;
            if ($isSigned) {
                continue;
            }
            $responses[] = $this->query(static::HOOK_HREF)->post([
                'url' => $url,
                'action' => mb_strtoupper($action),
                'entityType' => $entityType
            ]);
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
                $responses[] = $this->query($hook->meta->href)->delete();
            }
        }
        return $responses;
    }
}
