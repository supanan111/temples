<?php

namespace Tests\Unit;

use Tests\TestCase;

class DatabaseUrlParserTest extends TestCase
{
    /**
     * Helper ฟังก์ชันถอดรหัส DATABASE_URL แบบเดียวกับใน db_config.php
     */
    private function parseDatabaseUrl(string $url): array
    {
        $parts = parse_url($url);
        $driver = strtolower($parts['scheme'] ?? 'mysql');
        if ($driver === 'postgres' || $driver === 'postgresql') {
            $driver = 'pgsql';
        }
        $host = $parts['host'] ?? 'localhost';
        $port = $parts['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
        $user = $parts['user'] ?? '';
        $pass = $parts['pass'] ?? '';
        $dbname = ltrim($parts['path'] ?? '', '/');
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $sslmode = $query['sslmode'] ?? ($driver === 'pgsql' ? 'require' : null);

        if ($driver === 'pgsql') {
            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}" . ($sslmode ? ";sslmode={$sslmode}" : "");
        } else {
            $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        }

        return [
            'driver'  => $driver,
            'host'    => $host,
            'port'    => $port,
            'user'    => $user,
            'pass'    => $pass,
            'dbname'  => $dbname,
            'sslmode' => $sslmode,
            'dsn'     => $dsn,
        ];
    }

    public function testParseNeonPostgresUrl(): void
    {
        $neonUrl = 'postgres://neondb_owner:secretpass123@ep-cool-fog-123456.ap-southeast-1.aws.neon.tech/neondb?sslmode=require';
        $res = $this->parseDatabaseUrl($neonUrl);

        $this->assertEquals('pgsql', $res['driver']);
        $this->assertEquals('ep-cool-fog-123456.ap-southeast-1.aws.neon.tech', $res['host']);
        $this->assertEquals(5432, $res['port']);
        $this->assertEquals('neondb_owner', $res['user']);
        $this->assertEquals('secretpass123', $res['pass']);
        $this->assertEquals('neondb', $res['dbname']);
        $this->assertEquals('require', $res['sslmode']);
        $this->assertEquals('pgsql:host=ep-cool-fog-123456.ap-southeast-1.aws.neon.tech;port=5432;dbname=neondb;sslmode=require', $res['dsn']);
    }

    public function testParseMysqlConnectionUrl(): void
    {
        $mysqlUrl = 'mysql://appuser:dbpass999@mysql-host.cloud.com:3306/temple_platform';
        $res = $this->parseDatabaseUrl($mysqlUrl);

        $this->assertEquals('mysql', $res['driver']);
        $this->assertEquals('mysql-host.cloud.com', $res['host']);
        $this->assertEquals(3306, $res['port']);
        $this->assertEquals('appuser', $res['user']);
        $this->assertEquals('dbpass999', $res['pass']);
        $this->assertEquals('temple_platform', $res['dbname']);
        $this->assertEquals('mysql:host=mysql-host.cloud.com;port=3306;dbname=temple_platform;charset=utf8mb4', $res['dsn']);
    }
}
