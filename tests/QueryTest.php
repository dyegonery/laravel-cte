<?php

namespace Tests;

use DateTime;
use PHPUnit\Framework\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Connection;
use Staudenmeir\LaravelCte\Query\Builder;
use Illuminate\Database\Query\Processors\Processor;
use Staudenmeir\LaravelCte\DatabaseServiceProvider;
use Staudenmeir\LaravelCte\Connections;
use Illuminate\Database\Capsule\Manager as CapsuleManager;

class QueryTest extends TestCase
{
    public function testQuickTest()
    {
        $capsule= new CapsuleManager();
        
        $capsule->addConnection(
            [
              "driver" => "mysql",
              "host" => "127.0.0.1",
              "port" => "5724",
              "unix_socket" => null,
              "database" => "DataCollection_dyn",
              "username" => "msandbox",
              "password" => "msandbox",
              "charset" => "utf8",
              "collation" => "utf8_unicode_ci",
              "prefix" => "",
          ],
            'patata'
        );
        $builder = new Builder($capsule->getConnection('patata'));

        $result = $builder->select()
        ->from('NERGY_COLLECTION_TEST_1561718882_41652673_215805_D_5d15f07798b0b')
        ->withExpression('test', function ($query) {
            $query->from('NERGY_COLLECTION_TEST_1561718882_41652673_215805_D_5d15f07798b0b');
        })->toSql();

        $this->assertTrue(true);
        return '';
    }
}
