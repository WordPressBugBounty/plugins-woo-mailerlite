<?php

class WooMailerLiteAdminWizardController extends WooMailerLiteController
{

    public function handleConnectAccount()
    {
        $this->validate([
            'apiKey' => ['required', 'string'],
            'nonce',
        ]);
        if (!WooMailerLiteCache::get('table_check')) {
            WooMailerLiteMigration::migrate();
            WooMailerLiteCache::set('table_check', true, 86400);
        }
        $response = $this->apiClient($this->validated['apiKey'])->validateKey($this->validated['apiKey']);
        if ($response->success) {
            WooMailerLiteCache::set('valid_api', true, 86400);
            WooMailerLiteOptions::updateMultiple([
                'apiKey' => $this->validated['apiKey'],
                'wizardStep' => 1,
            ]);
            if ($this->apiClient()->isClassic()) {
                $response->data = $response->data->account;
            }
            WooMailerLiteOptions::updateMultiple([
                'accountName' => $response->data->name,
                'accountId' => $response->data->id,
                'accountSubdomain' => $response->data->subdomain ?? ''
            ]);
            $response->addData('mlPlatform', $this->apiClient()->getApiType());
        }

        return $this->response($response, $response->status);
    }

    public function getGroups()
    {
        $this->validate('nonce');
        $params = [
            'limit' => 50,
            'page' => $this->request('page') ?? 1,
        ];

        if ($this->apiClient()->isRewrite()) {
            if ($this->requestHas('page') && ($this->request['page'] !== '1')) {
                $params['offset'] = ($this->request['page'] - 1)  * $params['limit'];
            }
        }

        if ($this->requestHas('filter')) {
            $params['filter'] = [
                'name' => $this->request('filter')
            ];
            if ($this->apiClient()->isClassic()) {
                $params['filters'] = ['name' => ['$like' => '%' . $this->request['filter'] . '%']];
            }
        }
        $response = $this->apiClient()->getGroups($params);
        $groups = [];
        if ($response->success) {
            if (isset($response->data)) {
                foreach ($response->data as $group) {
                    $groups['data'][] = [
                        'id' => $group->id,
                        'name' => $group->name
                    ];
                }
            }
            if ($response->links && isset($response->links->next)) {
                $groups['pagination'] = [
                    'next' => (bool)$response->links->next
                ];
            } elseif ($this->apiClient()->isClassic() && (count($groups['data']) > 0)) {
                $groups['pagination'] = [
                    'next' => WooMailerLiteApi::CLASSIC_API
                ];
            }
        }
        return $this->response(!empty($groups) ? $groups : $response, $response->status);
    }

    public function shopSetup()
    {
        $this->validate([
            'group' => ['required', 'string'],
            'syncFields' => ['required', 'array'],
            'consumerKey' => ['sometimes', 'string'],
            'consumerSecret' => ['sometimes', 'string'],
            'nonce'
        ]);

        if (!$this->apiClient()->isReWrite() && !$this->validateClassicConsumerKeys($this->request['consumerKey'], $this->request['consumerSecret'])) {
            return $this->response(['message' => 'Invalid consumer key or secret'], 400);
        }

        if (!WooMailerLiteOptions::get('group') || (!empty(WooMailerLiteOptions::get('group', [])) && WooMailerLiteOptions::get('group')['id'] != $this->validated['group'])) {
            $response = $this->apiClient()->getGroupById($this->validated['group']);
            if (!$response->success) {
                return $this->response($response, $response->status);
            }
            WooMailerLiteOptions::update('group', ['id' => $response->data->id, 'name' => $response->data->name]);
        }

        $shopName = get_bloginfo('name');
        $shopName = !empty($shopName) ? $shopName : home_url();
        $shopId = WooMailerLiteOptions::get('shopId');
        $popupsEnabled = WooMailerLiteOptions::get('popupsEnabled');
        $currency = get_option('woocommerce_currency');
        $store = home_url();
        if ($this->apiClient()->isReWrite()) {
            if ($shopId === false) {
                $shops = $this->apiClient()->getShops();
                if ($shops->success) {
                    foreach ($shops->data as $shop) {
                        if ($shop->url == home_url()) {
                            $shopId = $shop->id;
                            WooMailerLiteOptions::update('shopId', $shopId);
                            break;
                        }
                    }
                }
            }
            $data = [
                'name'               => $shopName,
                'url'                => $store,
                'currency'           => $currency,
                'platform'           => 'woocommerce',
                'group_id'           => $this->validated['group'],
                'enable_popups'      => $popupsEnabled,
                'enable_resubscribe' => $this->request('resubscribe'),
                'enabled'            => true,
                'access_data'        => '-'
            ];
        } else {
            $data = [
                'consumer_key'    => $this->validated['consumerKey'],
                'consumer_secret' => $this->validated['consumerSecret'],
                'store'           => $store,
                'currency'        => $currency,
                'group_id'        => $this->validated['group'],
                'resubscribe'     => $this->request('resubscribe') ?? 0,
                'ignore_list'     => $this->request('ignoreList') ?? '',
                'create_segments' => $this->request('createSegments') ?? ''
            ];
        }

        $this->apiClient()->toggleShop($store, 1);
        $response = $this->apiClient()->setConsumerData($data);

        if ($response->success) {
            WooMailerLiteOptions::updateMultiple([
                'wizardStep'=> 2,
                'shopId' => $response->data->id,
                'popupsEnabled' => $response->data->enable_popups,
                'syncFields' => $this->validated['syncFields'],
                'enabled' => true,
                'consumerKey' => $this->validated['consumerKey'],
                'consumerSecret' => $this->validated['consumerSecret'],
            ]);
            if (!$response->data->group) {
                $response->data->group = WooMailerLiteOptions::get('group');
            }
            WooMailerLiteProductSyncJob::dispatch();
        }
        return $this->response($response, $response->status);
    }

    protected function validateClassicConsumerKeys($consumerKey, $consumerSecret) {
        $endpoint = home_url() . '/wp-json/wc/v2/products';
        $oauth_params = [
            'oauth_consumer_key' => $consumerKey,
            'oauth_nonce'        => uniqid(mt_rand(1, 1000)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp'    => time(),
            'oauth_version'      => '1.0',
        ];
        ksort($oauth_params);
        $base_string = 'GET&' . rawurlencode($endpoint) . '&' . rawurlencode(http_build_query($oauth_params, '', '&', PHP_QUERY_RFC3986));
        $signing_key = rawurlencode($consumerSecret) . '&';
        $oauth_params['oauth_signature'] = base64_encode(hash_hmac('sha1', $base_string, $signing_key, true));
        $url_with_params = $endpoint . '?' . http_build_query($oauth_params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_with_params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($http_code == 200) {
            return true;
        }
        return false;
    }
    public function getDebugLogs()
    {
        if (!function_exists('shell_exec')) {
            return $this->response(['log' => 'shell_exec function not enabled in php config.'], 400);
        }
        $this->validate('nonce');
        $errorPath = ini_get('error_log');
        $lines = '';
        if(!empty($errorPath)) {
            $lines = `tail -500 {$errorPath}`;
        }

        if(!empty($lines)) {
            return $this->response(['log' => $lines], 200);
        } else {
            $log_file = ABSPATH . 'wp-content/debug.log';
            if (file_exists($log_file) && file_get_contents($log_file)) {
                return $this->response(['log' => file_get_contents($log_file)], 200);
            }
        }
        return $this->response(['log' => 'No logs found'], 400);
    }
}
