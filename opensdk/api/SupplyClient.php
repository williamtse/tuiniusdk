<?php
/**
 * Created by PhpStorm.
 * User: stbz
 * Date: 2020/6/12
 * Time: 2:46 PM
 */

namespace xingdian\opensdk\api;

use think\Cache;
use think\Log;
use xingdian\opensdk\api\core\ClientException;
use xingdian\opensdk\api\core\Base;
use xingdian\opensdk\api\http\RequestClint;

class SupplyClient extends Base
{
    public $params;


    protected $getBody = [
        '/v2/Goods/GetBulkGoodDetail',
        '/v2/Goods/GetBulkGoodsMessage',
        '/v2/order/beforeCheck',
        '/v2/order/createOrder',
        '/v2/logistic',
        '/v2/logistic/firms'
    ];

    /**
     * 构造函数
     *
     * 构造函数有几种情况：
     * 一般的时候初始化使用 $supplyClient = new SupplyClient($appKey, $appSecret)
     * 初始化使用 $supplyClient = new SupplyClient($appKey, $appSecret)
     *
     * @param string $appKey 从Open平台获得的appKey
     * @param string $appSecret 从Open平台获得的appSecret
     */
    public function __construct($config)
    {
        extract($config);
        $appKey = trim($appKey);
        $appSecret = trim($appSecret);

        if (empty($appKey)) {
            throw new ClientException("app key id is empty");
        }
        if (empty($appSecret)) {
            throw new ClientException("app secret is empty");
        }
        parent::__construct($server, $appSecret, $appKey);

        self::checkEnv();
    }

    public function getAccessToken()
    {
        $key = 'open:access_token';
        $token = Cache::store('redis')->get($key);
        if (!$token) {
            $serverUrl = RequestClint::richRequest('v2/index/gettoken', $this);
            $serverUrl .= '?appid=' . $this->app_key . '&appsecret=' . $this->app_secret;
            $response = RequestClint::curl_request($serverUrl, false);
            Log::record('获取达人宝access_token:' . $response);
            //清空请求参数
            $this->removeAllParam();
            if ($response) {
                $obj = json_decode($response);
                if ($obj->code) {
                    $token = $obj->data->token;
                    Cache::store('redis')->set($key, $token, $obj->data->expires_in);
                }
            }
        }
        return $token;
    }

    public function getApiResponse($method, $action, $params = [], $access_token = '')
    {
        Log::record('access_token加入参数中：' . $access_token);
        if ($access_token) {
            $this->addParam('token', $access_token);
        }
        if (in_array($action, $this->getBody) && strtolower($method) == "get") {
            $method = "getbody";
        }

        $this->params = $params;
        switch (strtolower($method)) {
            case "get":
                foreach ($this->params as $k => $v) {
                    $this->addParam($k, $v);
                }
                break;
            case "post":
                $this->addBody(json_encode($this->params));
                break;
            case "getbody":
                $this->addBody(json_encode($this->params));
                break;
            case "patch":
                $this->addBody(json_encode($this->params));
                break;
            default:
                break;
        }
        $response = RequestClint::$method($action, $this);
        //清空请求参数
        $this->removeAllParam();
        return $response;
    }


    /**
     * 用来检查sdk所以来的扩展是否打开
     *
     * @throws OssException
     */
    public static function checkEnv()
    {
        if (function_exists('get_loaded_extensions')) {
            //检测curl扩展
            $enabled_extension = array("curl");
            $extensions = get_loaded_extensions();
            if ($extensions) {
                foreach ($enabled_extension as $item) {
                    if (!in_array($item, $extensions)) {
                        throw new ClientException("Extension {" . $item . "} is not installed or not enabled, please check your php env.");
                    }
                }
            } else {
                throw new ClientException("function get_loaded_extensions not found.");
            }
        } else {
            throw new ClientException('Function get_loaded_extensions has been disabled, please check php config.');
        }
    }
}