<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Ovh\Api;

class OVHBackupManager
{
    private ?Api $api;
    private Filesystem $filesystem;
    private array $config = [];
    private const BACKUP_DIR = 'backups';

    public function __construct()
    {
        $this->loadEnvironment();
        $this->filesystem = new Filesystem();
        $this->initializeAPI();
        $this->ensureBackupDirectoryExists();
    }

    private function loadEnvironment(): void
    {
        $this->config = Yaml::parse(file_get_contents(__DIR__ . '/config.yml'));
    }

    private function initializeAPI(): void
    {
        $apiKey = $this->config['API_KEY'];
        $apiSecret = $this->config['API_SECRET'];
        $consumerKey = $this->config['CONSUMER_KEY'] ?? null;

        if (!$consumerKey) {
            $consumerKey = $this->requestConsumerKey($apiKey, $apiSecret);
            $this->updateConfigFile($consumerKey);
        }

        $this->api = new Api($apiKey, $apiSecret, 'https://eu.api.ovh.com/1.0', $consumerKey);
    }

    private function requestConsumerKey($apiKey, $apiSecret)
    {
        $access = [
            ["method" => "GET", "path" => "/domain/*"]
        ];

        $ovh = new Api($apiKey, $apiSecret, 'https://eu.api.ovh.com/1.0', '');
        $auth = $ovh->requestCredentials($access);
        echo "Validation URL: " . $auth['validationUrl'] . "\nPlease visit the URL and press Enter once done.\n";
        fgets(STDIN);

        return $auth['consumerKey'];
    }

    private function updateConfigFile($consumerKey): void
    {
        $this->config['CONSUMER_KEY'] = $consumerKey;
        file_put_contents(__DIR__ . '/config.yml', Yaml::dump($this->config));
    }

    private function ensureBackupDirectoryExists(): void
    {
        try {
            $this->filesystem->mkdir(__DIR__ . '/' . self::BACKUP_DIR);
        } catch (IOExceptionInterface $exception) {
            echo "An error occurred while creating your directory at " . $exception->getPath() . "\n";
            exit;
        }
    }

    public function backupDomains(): void
    {
        $domains = $this->api->get('/domain/zone');
        foreach ($domains as $domain) {
            $this->backupDomain($domain);
        }
    }

    private function backupDomain($domain): void
    {
        try {
            $this->filesystem->mkdir(__DIR__ . '/' . self::BACKUP_DIR . '/' . $domain);
            $export = $this->api->get("/domain/zone/$domain/export");
            $recordFile = __DIR__ . '/' . self::BACKUP_DIR . "/$domain/{$domain}_dns-export.txt";
            file_put_contents($recordFile, $export);
        } catch (\Exception $e) {
            echo "Error backing up $domain: " . $e->getMessage() . "\n";
        }
    }
}

// Bootstrapping
$backupManager = new OVHBackupManager();
$backupManager->backupDomains();
