<?php

namespace Swilen\Database;

use Swilen\Database\Concerns\DetectLostConnections;
use Swilen\Database\Contract\ConnectionContract;
use Swilen\Database\Exception\LostConnectionException;
use Swilen\Database\Exception\QueryException;

class Connection implements ConnectionContract
{
    use DetectLostConnections;

    /**
     * The PDO instance.
     *
     * @var \PDO|null
     */
    protected $pdo;

    /**
     * The PDO instance resolver.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * The default fetch mode for PDO.
     *
     * @var int
     */
    protected $fetchMode = \PDO::FETCH_OBJ;

    /**
     * Indicates if record has been modified.
     *
     * @var bool
     */
    protected $recordsModified;

    /**
     * The scheme to which it has been connected.
     *
     * @var string
     */
    protected $schema;

    /**
     * The database config array.
     *
     * @var array
     */
    protected $config;

    /**
     * Reconnected attempts.
     *
     * @var int
     */
    protected $attempts = 0;

    /**
     * Max attempts for reconection.
     *
     * @var int
     */
    protected $maxAttempts = 3;

    /**
     * Create a new database connection instance.
     *
     * @param \Closure $pdo
     * @param string   $schema
     * @param array    $config
     *
     * @return void
     */
    public function __construct(\Closure $pdo, $schema = '', array $config = [])
    {
        $this->resolver = $pdo;
        $this->schema   = $schema;
        $this->config   = $config;
    }

    /**
     * Get the PDO connection instance.
     *
     * @return \PDO|null
     */
    public function getPdo()
    {
        if ($this->pdo === null) {
            return $this->pdo = call_user_func($this->resolver, $this);
        }

        return $this->pdo;
    }

    /**
     * Get database connection.
     *
     * @return \PDO|null
     */
    public function getConnection()
    {
        return $this->getPdo();
    }

    /**
     * Select all rows with prepare statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed[]
     */
    public function select(string $query, array $bindings = [])
    {
        return $this->partial($query, $bindings)->fetchAll();
    }

    /**
     * Select one row with prepare statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     */
    public function selectOne(string $query, array $bindings = [])
    {
        return $this->partial($query, $bindings)->fetch();
    }

    /**
     * Insert data to row with prepare statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int|false
     */
    public function insert(string $query, array $bindings = [])
    {
        $this->statement($query, $bindings);

        return $this->getInsertId();
    }

    /**
     * Update data to row with prepare statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function update(string $query, array $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Delete data to row with prepare statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function delete(string $query, array $bindings = [])
    {
        return $this->affectingStatement($query, $bindings);
    }

    /**
     * Execute statement and return TRUE on success or FALSE on failure.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement(string $query, array $bindings = [])
    {
        return $this->handle($query, $bindings, function ($query, $bindings) {
            // Prepare SQL statement
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $this->recordsHaveBeenModified();

            return $statement->execute();
        });
    }

    /**
     * Run an SQL statement and get the number of rows affected.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return int
     */
    public function affectingStatement($query, $bindings = [])
    {
        return $this->handle($query, $bindings, function ($query, $bindings) {
            // Prepare SQL statement
            $statement = $this->getPdo()->prepare($query);

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            $this->recordsHaveBeenModified(
                ($count = $statement->rowCount()) > 0
            );

            return $count;
        });
    }

    /**
     * Prepare a partial statement from given query.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return \PDOStatement
     */
    public function partial(string $query, array $bindings = [])
    {
        return $this->handle($query, $bindings, function ($query, $bindings) {
            // Prepare SQL statement with fetch mode
            $statement = $this->withFetchMode($this->getPdo()->prepare($query));

            $this->bindValues($statement, $this->prepareBindings($bindings));

            $statement->execute();

            return $statement;
        });
    }

    /**
     * Handle a SQL statement.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return mixed
     *
     * @throws \Swilen\Database\Exception\QueryException
     */
    protected function handle($query, $bindings, \Closure $callback)
    {
        $this->reconnectIfMissingConnection();

        try {
            $result = $this->runQueryCallback($query, $bindings, $callback);
        } catch (QueryException $e) {
            $result = $this->tryAgainIfCausedByLostConnection(
                $e, $query, $bindings, $callback
            );
        }

        return $result;
    }

    /**
     * Run a raw, unprepared query against the PDO connection.
     *
     * @param string $query
     *
     * @return bool
     */
    public function unprepared($query)
    {
        if (empty($query) || !is_string($query)) {
            throw new \InvalidArgumentException('Query must be a non-empty string');
        }

        return $this->handle($query, [], function ($query) {
            $this->recordsHaveBeenModified(
                $change = $this->getPdo()->exec($query) !== false
            );

            return $change;
        });
    }

    /**
     * Set fetch mode PDO statement.
     *
     * @param \PDOStatement $statement
     *
     * @return \PDOStatement
     */
    public function withFetchMode(\PDOStatement $statement)
    {
        $statement->setFetchMode($this->fetchMode);

        return $statement;
    }

    /**
     * Run a SQL statement from callback.
     *
     * @param string   $query
     * @param array    $bindings
     * @param \Closure $callback
     *
     * @return mixed
     *
     * @throws \Swilen\Database\Exception\QueryException
     */
    protected function runQueryCallback($query, $bindings, \Closure $callback)
    {
        // Try to execute the callback with the prepared SQL query
        try {
            return $callback($query, $bindings);
        }

        // Handle SQL query execution exceptions
        catch (\Throwable $e) {
            throw new QueryException($query, $this->prepareBindings($bindings), $e);
        }
    }

    /**
     * Handle a query exception that occurred during query execution.
     *
     * @param QueryException $e
     * @param string         $query
     * @param array          $bindings
     * @param \Closure       $callback
     *
     * @return mixed
     *
     * @throws \Swilen\Database\Exception\QueryException
     */
    protected function tryAgainIfCausedByLostConnection(QueryException $e, $query, $bindings, \Closure $callback)
    {
        if ($this->causedByLostConnection($e->getPrevious())) {
            $this->reconnect();

            return $this->runQueryCallback($query, $bindings, $callback);
        }

        throw $e;
    }

    /**
     * Bind values to their parameters in the given statement.
     *
     * @param \PDOStatement $statement
     * @param array         $bindings
     *
     * @return void
     */
    public function bindValues(\PDOStatement $statement, array $bindings = [])
    {
        foreach ($bindings as $key => $value) {
            $statement->bindValue(
                is_string($key) ? $key : $key + 1,
                $value,
                is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR
            );
        }
    }

    /**
     * Prepare the query bindings for execution.
     *
     * @param array $bindings
     *
     * @return array
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            // Transform value time as string
            // Format if value id datetime
            if ($value instanceof \DateTimeInterface) {
                $bindings[$key] = $value->format('Y-m-d H:i:s');
            }

            // Tranform boolean values to equivant in integer
            // { True: 1, False: 0 }
            elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }

    /**
     * Indicate if any records have been modified.
     *
     * @param bool $value
     *
     * @return void
     */
    public function recordsHaveBeenModified(bool $value = true)
    {
        if (!$this->recordsModified) {
            $this->recordsModified = $value;
        }
    }

    /**
     * Reconnect to the database if a PDO connection is missing.
     *
     * @return void
     */
    protected function reconnectIfMissingConnection()
    {
        if (is_null($this->pdo)) {
            $this->reconnect();
        }
    }

    /**
     * Attempt to reconnect after failed to connect.
     *
     * @return void
     *
     * @throws \Throwable
     */
    protected function reconnect()
    {
        $connected = false;
        $started   = microtime(true);
        $exception = null;

        while (!$connected && $this->attempts < $this->maxAttempts) {
            ++$this->attempts;
            try {
                if ($this->tryToReconnect()) {
                    $connected = true;
                }
            } catch (\Throwable $exception) {
                // Prevent error in reconnect attempts
            }
        }

        if ($connected === false) {
            throw new LostConnectionException($this->attempts, $this->getElapsedTime($started), $exception);
        }
    }

    /**
     * Try to reconnect pdo.
     *
     * @return \PDO|null
     */
    protected function tryToReconnect()
    {
        $this->disconnect();

        return $this->getPdo();
    }

    /**
     * Get PDO insertId.
     *
     * @return int|false
     */
    public function getInsertId()
    {
        $id = $this->getPdo()->lastInsertId();

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Return the connections made during the process.
     *
     * @return int
     */
    public function reconnectAttempts()
    {
        return $this->attempts;
    }

    /**
     * Get the name of the connected database.
     *
     * @return string
     */
    public function getSchema()
    {
        return $this->schema;
    }

    /**
     * Start a new database transaction.
     *
     * @return void
     */
    public function beginTransaction()
    {
        $this->reconnectIfMissingConnection();

        $this->getPdo()->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit()
    {
        $this->reconnectIfMissingConnection();

        $this->getPdo()->commit();
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollBack()
    {
        $this->reconnectIfMissingConnection();

        $this->getPdo()->rollBack();
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     *
     * @return float
     */
    protected function getElapsedTime(int $start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    /**
     * Disconnect from the underlying PDO connection.
     *
     * @return void
     */
    public function disconnect()
    {
        $this->pdo = null;
    }

    /**
     * Close database connection.
     *
     * @return void
     */
    public function close()
    {
        $this->pdo      = null;
        $this->resolver = null;
    }

    /**
     * Close database connection - PDO.
     *
     * Called automatically when there are no further references to object
     *
     * @return void
     */
    public function __destruct()
    {
        try {
            $this->close();
        } catch (\Throwable $e) {
            // Log error but don't throw in destructor to prevent fatal errors
            if (function_exists('error_log')) {
                error_log('Database connection close error: ' . $e->getMessage());
            }
        }
    }
}
