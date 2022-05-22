<?php
namespace App\Install;

use App\Loggers\FileLogger;
use App\Support\Database;
use InvalidArgumentException;
use TRegx\CleanRegex\Pattern;

abstract class Migration
{
    protected Database $db;
    protected MigrationFiles $migrationFiles;
    protected FileLogger $fileLogger;

    public function __construct(
        Database $db,
        MigrationFiles $migrationFiles,
        FileLogger $fileLogger
    ) {
        $this->db = $db;
        $this->migrationFiles = $migrationFiles;
        $this->fileLogger = $fileLogger;
    }

    abstract public function up();

    protected function executeQueries($queries)
    {
        foreach ($queries as $query) {
            $this->db->query($query);
        }
    }

    protected function executeSqlFile($file)
    {
        $path = $this->migrationFiles->buildPath($file);
        $queries = $this->splitSQLFile($path);
        $this->executeQueries($queries);
    }

    protected function splitSQLFile($path, $delimiter = ";")
    {
        $queries = [];

        $path = fopen($path, "r");

        if (is_resource($path) !== true) {
            throw new InvalidArgumentException("Invalid path to queries");
        }

        $query = [];

        while (feof($path) === false) {
            $query[] = fgets($path);

            if (Pattern::inject('@\s*$', 'iS', [$delimiter])->test(end($query))) {
                $query = trim(implode("", $query));
                $queries[] = $query;
            }

            if (is_string($query) === true) {
                $query = [];
            }
        }

        fclose($path);

        return array_filter($queries, fn($query) => strlen($query));
    }
}
