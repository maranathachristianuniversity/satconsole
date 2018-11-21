<?php
/**
 * pukoconsole.
 * Advanced console util that make pukoframework get things done on the fly.
 * Copyright (c) 2018, Didit Velliz
 *
 * @author Didit Velliz
 * @link https://github.com/velliz/pukoconsole
 * @since Version 0.1.0
 */

namespace pukoconsole;

use Exception;
use PDO;
use pukoconsole\util\Echos;
use pukoconsole\util\Input;

class Database
{

    use Input, Echos;

    /**
     * @var PDO
     */
    var $PDO;

    /**
     * @var string
     */
    var $query = '';

    /**
     * Database constructor.
     * @param null $root
     * @throws Exception
     */
    public function __construct($root = null)
    {
        if ($root === null) {
            die(Echos::Prints('Base url required'));
        }

        $db = Input::Read('Database Type (mysql, oracle, sqlsrv, mongo)');

        $host = Input::Read('Hostname (Default: localhost)');
        $port = Input::Read('Port (Default: 3306)');
        $dbName = Input::Read('Database Name');

        $user = Input::Read('Username');
        $pass = Input::Read('Password');

        $configuration = file_get_contents(__DIR__ . "/template/config/database");

        $configuration = str_replace('{{type}}', $db, $configuration);
        $configuration = str_replace('{{host}}', $host, $configuration);
        $configuration = str_replace('{{user}}', $user, $configuration);
        $configuration = str_replace('{{pass}}', $pass, $configuration);
        $configuration = str_replace('{{dbname}}', $dbName, $configuration);
        $configuration = str_replace('{{port}}', $port, $configuration);

        file_put_contents("{$root}/config/database.php", $configuration);

        switch ($db) {
            case 'mysql':
                $this->PDO = $this->GenerateMySQL($host, $port, $dbName, $user, $pass);
                break;
            case 'oracle':
                $this->GenerateOracle();
                break;
            case 'sqlsrv':
                $this->GenerateSqlServer();
                break;
            case 'mongo':
                die(Echos::Prints(sprintf("Sorry, database '%s' not yet supported.", $db)));
                break;
            default:
                die(Echos::Prints(sprintf("Sorry, database '%s' not yet supported.", $db)));
        }

        $statement = $this->PDO->prepare($this->query);
        $statement->execute();

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $key => $val) {

            echo Echos::Prints(sprintf("Creating model %s.php", $val['TABLE_NAME']), false);

            $statement = $this->PDO->prepare("DESC " . $val['TABLE_NAME']);
            $statement->execute();

            $column = $statement->fetchAll(PDO::FETCH_ASSOC);
            $property = "";
            $primary = "";

            foreach ($column as $k => $v) {
                $initValue = 'null';

                if ($v['Key'] === 'PRI') {
                    $primary = $v['Field'];
                }

                if (strpos($v['Type'], 'char') !== false) {
                    $initValue = "''";
                }
                if (strpos($v['Type'], 'text') !== false) {
                    $initValue = "''";
                }
                if (strpos($v['Type'], 'int') !== false) {
                    $initValue = 0;
                }
                if (strpos($v['Type'], 'double') !== false) {
                    $initValue = 0;
                }

                $property .= file_get_contents(__DIR__ . "/template/model/model_vars");
                $property = str_replace('{{field}}', $v['Field'], $property);
                $property = str_replace('{{type}}', $v['Type'], $property);
                $property = str_replace('{{value}}', $initValue, $property);
            }

            $model_file = file_get_contents(__DIR__ . "/template/model/model");
            $model_file = str_replace('{{table}}', $val['TABLE_NAME'], $model_file);
            $model_file = str_replace('{{primary}}', $primary, $model_file);
            $model_file = str_replace('{{variables}}', $property, $model_file);

            if (!is_dir("{$root}/plugins/model")) {
                mkdir("{$root}/plugins/model");
            }
            file_put_contents($root . "/plugins/model/" . $val['TABLE_NAME'] . ".php", $model_file);

            $test_file = file_get_contents(__DIR__ . "/template/model/model_contract_tests");
            $test_file = str_replace('{{table}}', $val['TABLE_NAME'], $test_file);

            if (!is_dir("{$root}/tests/unit/model")) {
                mkdir("{$root}/tests/unit/model");
            }
            file_put_contents("{$root}/tests/unit/model/{$val['TABLE_NAME']}Test.php", $test_file);
        }
    }

    /**
     * @param $host
     * @param $port
     * @param $dbName
     * @param $user
     * @param $pass
     * @return PDO
     */
    public function GenerateMySQL($host, $port, $dbName, $user, $pass)
    {
        try {
            $pdoConnection = "mysql:host=$host;port=$port;dbname=$dbName";
            $dbi = new PDO($pdoConnection, $user, $pass);
            $dbi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $this->query = "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_NAME LIKE '%' AND TABLE_SCHEMA = '{$dbName}'";

            return $dbi;
        } catch (Exception $ex) {
            die(Echos::Prints("Failed to connect."));
        }

    }

    public function GenerateOracle()
    {
        die(Echos::Prints("Sorry, this option not yet supported."));
    }

    public function GenerateSqlServer()
    {
        die(Echos::Prints("Sorry, this option not yet supported."));
    }

    public function GenerateMongo()
    {
        die(Echos::Prints("Sorry, this option not yet supported."));
    }

    public function __toString()
    {
        return Echos::Prints('Database setting completed');
    }

}