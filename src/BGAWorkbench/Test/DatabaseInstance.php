<?php

namespace BGAWorkbench\Test;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\ConnectionException;

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

    /** @var bool */
    private $tablesCreated;

    /**
     * @param string $name
     * @param string $host
     * @param int $port
     * @param string $username
     * @param string $password
     * @param string[] $tableSchemaPathnames
     * @param bool $externallyManaged Is the database created and managed by another system? (CI)
     */
    public function __construct(string $name, string $host, int $port, string $username, string $password, array $tableSchemaPathnames, bool $externallyManaged = false)
    {
        $this->name = $name;
        $this->serverConnectionParams = [
            'user' => $username,
            'password' => $password,
            'host' => $host,
            'port' => $port,
            'driver' => 'pdo_mysql'
        ];
        $this->externallyManaged = $externallyManaged;
        $this->isCreated = false;
        $this->config = new Configuration();
        $this->tableSchemaPathnames = $tableSchemaPathnames;
        $this->tablesCreated = false;
    }

    /**
     * @param string $tableName
     * @param array $conditions
     * @return array
     * @throws DBALException
     */
    public function fetchRows(string $tableName, array $conditions = [])
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
     * @throws DBALException
     */
    public function fetchValue(string $sql)
    {
        return $this->getOrCreateConnection()
            ->executeQuery($sql)
            ->fetchColumn();
    }

    /**
     * @return Connection
     * @throws DBALException
     */
    public function getOrCreateConnection(): Connection
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
     * @throws DBALException
     */
    private function getOrCreateSchemaConnection(): Connection
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
        if ($this->isCreated) {
            throw new \LogicException('Database already created');
        }

        $schema = $this->getOrCreateSchemaConnection()->getSchemaManager();
        if ($this->externallyManaged) {
            // we cant drop DB, so drop all tables instead
            $this->dropTables();
        } else {
            $schema->dropAndCreateDatabase($this->name);
        }
        $this->createTables();

        $this->isCreated = true;
        return $this;
    }

    private function createTables(): DatabaseInstance
    {
        if ($this->tablesCreated) { return $this; }

        try {
            foreach ($this->tableSchemaPathnames as $schemaPathname) {
                $sql = @file_get_contents($schemaPathname);
                if ($sql === false) {
                    throw new \RuntimeException("Couldn't read table schema from {$schemaPathname}");
                }
                $this->getOrCreateConnection()->executeUpdate($sql);
            }
        } catch (ConnectionException $e) {
            $errorMessage = $e->getMessage();
            $dbName = $this->name;
            $dbConfig = $this->serverConnectionParams;
            $dbConfig['password'] = str_repeat('*', mb_strlen($dbConfig['password']));
            $dbConfig = print_r($dbConfig, true);
            $message = "$errorMessage - $dbName - $dbConfig";
            throw new \RuntimeException("Failed to connect to DB: $message");
        }
        $this->tablesCreated = true;
        return $this;
    }

    public function dropTables(): DatabaseInstance
    {
        $connection = $this->getOrCreateConnection();
        $connection->beginTransaction();
        try {
            $connection->prepare("SET FOREIGN_KEY_CHECKS = 0;")->execute();

            foreach ($connection->getSchemaManager()->listTableNames() as $tableNames) {
                $sql = 'DROP TABLE ' . $tableNames;
                $connection->prepare($sql)->execute();
            }
            $connection->prepare("SET FOREIGN_KEY_CHECKS = 1;")->execute();
            $connection->commit();
        } catch (\Exception $e) {
            $connection->rollBack();
        }
        $this->tablesCreated = false;
        return $this;
    }

    public function drop(): DatabaseInstance
    {
        if (!$this->isCreated) {
            throw new \LogicException('Database not created');
        }

        if ($this->externallyManaged) {
            $this->dropTables();
        } else {
            $this->getOrCreateSchemaConnection()->getSchemaManager()->dropDatabase($this->name);
        }

        $this->isCreated = false;
        return $this;
    }

    /**
     * Truncate the DB
     *
     * @return $this
     * @throws DBALException
     * @throws \Doctrine\DBAL\ConnectionException
     */
    public function truncate(): DatabaseInstance
    {
        $connection = $this->getOrCreateSchemaConnection();
        $schema = $connection->getSchemaManager();
        $tableNames = $schema->listTableNames();
        foreach ($tableNames as $tableName) {
            $connection->beginTransaction();
            try {
                $connection->query('SET FOREIGN_KEY_CHECKS=0');
                $q = $connection->getDatabasePlatform()->getTruncateTableSQL($tableName);
                $connection->executeUpdate($q);
                $connection->query('SET FOREIGN_KEY_CHECKS=1');
                $connection->commit();
            }
            catch (\Exception $e) {
                $connection->rollback();
            }
        }
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
