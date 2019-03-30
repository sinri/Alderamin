<?php
/**
 * Created by PhpStorm.
 * User: sinri
 * Date: 2019-03-30
 * Time: 12:13
 */

namespace sinri\Alderamin\core\configuration;


use Psr\Log\LogLevel;
use sinri\ark\core\ArkHelper;
use sinri\ark\database\mysqli\ArkMySQLiConfig;
use sinri\ark\database\pdo\ArkPDOConfig;
use sinri\ark\email\ArkSMTPMailerConfig;

class AlderaminConfig
{
    const KEY_WRITE_NODE = "write_node";
    const KEY_READ_NODE = "read_node";
    /**
     * @var ArkPDOConfig
     */
    protected $corePdoConfig;
    /**
     * @var string
     */
    protected $sqlStore;
    /**
     * @var string path to store XLSX
     */
    protected $reportStore;
    /**
     * @var string path to store CSV
     */
    protected $craftStore;

    /**
     * @return string
     */
    public function getCraftStore(): string
    {
        return $this->craftStore;
    }

    /**
     * @param string $craftStore
     */
    public function setCraftStore(string $craftStore)
    {
        $this->craftStore = $craftStore;
    }

    /**
     * @var int
     */
    protected $cronMax;
    /**
     * @var string must not contains tail '\', e.g.  "XXX\\YYY"
     */
    protected $unitStoreNamespace;

    /**
     * @return string
     */
    public function getUnitStoreNamespace(): string
    {
        return $this->unitStoreNamespace;
    }

    /**
     * @param string $unitStoreNamespace
     */
    public function setUnitStoreNamespace(string $unitStoreNamespace)
    {
        $this->unitStoreNamespace = $unitStoreNamespace;
    }

    /**
     * @var string
     */
    protected $logDirPath;
    /**
     * @var string
     */
    protected $logBaseLevel;
    /**
     * @var ArkSMTPMailerConfig
     */
    protected $smtpConfig;
    /**
     * @var ArkMySQLiConfig[]
     */
    protected $nodesMySQLiConfigList;

    public function __construct()
    {
        $this->logBaseLevel = LogLevel::INFO;
    }

    /**
     * @return ArkPDOConfig
     */
    public function getCorePdoConfig(): ArkPDOConfig
    {
        return $this->corePdoConfig;
    }

    /**
     * @param ArkPDOConfig $corePdoConfig
     */
    public function setCorePdoConfig(ArkPDOConfig $corePdoConfig)
    {
        $this->corePdoConfig = $corePdoConfig;
    }

    /**
     * @return string
     */
    public function getSqlStore(): string
    {
        return $this->sqlStore;
    }

    /**
     * @param string $sqlStore
     */
    public function setSqlStore(string $sqlStore)
    {
        $this->sqlStore = $sqlStore;
    }

    /**
     * @return string
     */
    public function getReportStore(): string
    {
        return $this->reportStore;
    }

    /**
     * @param string $reportStore
     */
    public function setReportStore(string $reportStore)
    {
        $this->reportStore = $reportStore;
    }

    /**
     * @return int
     */
    public function getCronMax(): int
    {
        return $this->cronMax;
    }

    /**
     * @param int $cronMax
     */
    public function setCronMax(int $cronMax)
    {
        $this->cronMax = $cronMax;
    }

    /**
     * @return string
     */
    public function getLogDirPath(): string
    {
        return $this->logDirPath;
    }

    /**
     * @param string $logDirPath
     */
    public function setLogDirPath(string $logDirPath)
    {
        $this->logDirPath = $logDirPath;
    }

    /**
     * @return string
     */
    public function getLogBaseLevel(): string
    {
        return $this->logBaseLevel;
    }

    /**
     * @param string $logBaseLevel
     */
    public function setLogBaseLevel(string $logBaseLevel)
    {
        $this->logBaseLevel = $logBaseLevel;
    }

    /**
     * @return ArkSMTPMailerConfig
     */
    public function getSmtpConfig(): ArkSMTPMailerConfig
    {
        return $this->smtpConfig;
    }

    /**
     * @param ArkSMTPMailerConfig $smtpConfig
     */
    public function setSmtpConfig(ArkSMTPMailerConfig $smtpConfig)
    {
        $this->smtpConfig = $smtpConfig;
    }

    /**
     * @return ArkMySQLiConfig[]
     */
    public function getNodesMySQLiConfigList(): array
    {
        return $this->nodesMySQLiConfigList;
    }

    /**
     * @param ArkMySQLiConfig[] $nodesMySQLiConfigList
     */
    public function setNodesMySQLiConfigList(array $nodesMySQLiConfigList)
    {
        $this->nodesMySQLiConfigList = $nodesMySQLiConfigList;
    }

    /**
     * @param string $key
     * @return ArkMySQLiConfig|false
     */
    public function getMySQLiNodeConfig($key)
    {
        return ArkHelper::readTarget($this->nodesMySQLiConfigList, [$key], false);
    }
}