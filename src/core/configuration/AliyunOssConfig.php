<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 12:12
 */

namespace sinri\Alderamin\core\configuration;


class AliyunOssConfig
{
    protected $akId;
    protected $akSecret;
    protected $endpoint;
    protected $bucket;

    public function __construct($akId, $akSecret, $endpoint, $bucket)
    {
        $this->akId = $akId;
        $this->akSecret = $akSecret;
        $this->endpoint = $endpoint;
        $this->bucket = $bucket;
    }

    /**
     * @return mixed
     */
    public function getAkId()
    {
        return $this->akId;
    }

    /**
     * @param mixed $akId
     */
    public function setAkId($akId)
    {
        $this->akId = $akId;
    }

    /**
     * @return mixed
     */
    public function getAkSecret()
    {
        return $this->akSecret;
    }

    /**
     * @param mixed $akSecret
     */
    public function setAkSecret($akSecret)
    {
        $this->akSecret = $akSecret;
    }

    /**
     * @return mixed
     */
    public function getEndpoint()
    {
        return $this->endpoint;
    }

    /**
     * @param mixed $endpoint
     */
    public function setEndpoint($endpoint)
    {
        $this->endpoint = $endpoint;
    }

    /**
     * @return mixed
     */
    public function getBucket()
    {
        return $this->bucket;
    }

    /**
     * @param mixed $bucket
     */
    public function setBucket($bucket)
    {
        $this->bucket = $bucket;
    }
}