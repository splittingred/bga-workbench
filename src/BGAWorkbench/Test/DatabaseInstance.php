<?php

namespace BGAWorkbench\Test;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;

class DatabaseInstance
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $serverConnectionParams;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Connection
     */
    private $schemaConnection;

    /**
     * @var bool
     */
    private $isCreated;

    /**
     * @var bool
     */
    private $externallyManaged;

    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var string[]
     */
    private $tableSchemaPathnames;

    /**
     * @param string $name
     * @param string $username
     * @param string $password
     * @param string[] $tableSchemaPathnames
     * @param bool $externallyManaged Is the database created and managed by another system? (CI)
     */
    public function __construct(string $name, string $username, string $password, array $tableSchemaPathnames, bool $externallyManaged = false)
    {
        $this->name = $name;
        $this->serverConnectionParams = [
            'user' => $username,
            'password' => $password,
            'host' => '127.0.0.1',
            'driver' => 'pdo_mysql'
        ];
        $this->externallyManaged = $externallyManaged;
        $this->isCreated = $externallyManaged;
        $this->config = new Configuration();
        $this->tableSchemaPathnames = $tableSchemaPathnames;
    }

    /**
     * @param string $tableName
     * @param array $conditions
     * @return array
     */
    public function fetchRows($tableName, array $conditions = [])
    {
        if (!$this->isCreated) {
            throw new \RuntimeException('Database not created');
        }

        $qb = $this->getOrCreateConnection()
            ->createQueryBuilder()
            ->select('*')
            ->from($tableName);
        foreach ($conditions as $name => $value) {
            $qb = $qb->andWhere("{$name} = :{$name}")
                ->setParameter(":{$name}", $value);
        }
        return $qb->execute()->fetchAll();
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function fetchValue($sql)
    {
        return $this->getOrCreateConnection()
            ->executeQuery($sql)
            ->fetchColumn();
    }

    /**
     * @return Connection
     */
    public function getOrCreateConnection()
    {
        if ($this->connection === null) {
            $this->connection = DriverManager::getConnection(
                array_merge($this->serverConnectionParams, ['dbname' => $this->name]),
                $this->config
            );
        }

        return $this->connection;
    }

    /**
     * @return Connection
     */
    private function getOrCreateSchemaConnection()
    {
        if ($this->schemaConnection === null) {
            $this->schemaConnection = DriverManager::getConnection(
                $this->serverConnectionParams,
                $this->config
            );
        }

        return $this->schemaConnection;
    }

    public function create(): DatabaseInstance
    {
        if ($this->externallyManaged) {
            return $this;
        }

        if ($this->isCreated) {
            throw new \LogicException('Database already created');
        }

        $this->getOrCreateSchemaConnection()->getSchemaManager()->dropAndCreateDatabase($this->name);
        $this->createTables();

        $this->isCreated = true;
        return $this;
    }

    private function createTables()
    {
        foreach ($this->tableSchemaPathnames as $schemaPathname) {
            $sql = @file_get_contents($schemaPathname);
            if ($sql === false) {
                throw new \RuntimeException("Couldn't read table schema from {$schemaPathname}");
            }
            $this->getOrCreateConnection()->executeUpdate($sql);
        }
    }

    public function drop(): DatabaseInstance
    {
        if ($this->externallyManaged) {
            return $this;
        }

        if (!$this->isCreated) {
            throw new \LogicException('Database not created');
        }

        $this->getOrCreateSchemaConnection()->getSchemaManager()->dropDatabase($this->name);

        $this->isCreated = false;
        return $this;
    }

    public function disconnect()
    {
        if ($this->connection !== null) {
            $this->connection->close();
        }
        if ($this->schemaConnection !== null) {
            $this->schemaConnection->close();
        }
    }
}
